<?php

namespace App\Services\Analytics;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Analisis kesehatan usaha dihitung dari data nyata company aktif
 * (cash_entries / orders), bukan template.
 *
 * D-26: seluruh akses data lewat EntityRepository::for($company, ...) yang
 * menolak lintas tenant. Jangan pernah query tanpa scope itu.
 * D-50: hasil analisis adalah data sensitif usaha - hanya untuk owner
 * company aktif; pemanggil bertanggung jawab membatasi tampilannya
 * (lihat juga GroupReportService untuk pola owner check).
 * D-31: istilah yang dihasilkan generik, tanpa nama industri.
 *
 * Fail-graceful: data kurang dari `analytics.min_data_points` atau
 * periode kosong menghasilkan `insufficient_data => true` tanpa angka karangan.
 */
class BusinessHealthAnalyzer
{
    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly EntityRepository $repository,
    ) {}

    /**
     * @param  string|null  $date  Titik akhir "periode ini" (format Y-m-d); null = hari ini.
     * @return array{
     *     insufficient_data: bool,
     *     company: string,
     *     period: array{start: string, end: string, previous_start: string, previous_end: string},
     *     revenue: float,
     *     expenses: float,
     *     margin: float,
     *     trend: array{direction: string, percent: float},
     *     highlights: list<array{type: string, label: string, detail: string}>
     * }
     */
    public function analyze(?string $date = null): array
    {
        $company = $this->companyContext->current();
        $anchor = $date !== null && strtotime($date) !== false
            ? CarbonImmutable::parse($date)
            : CarbonImmutable::today();

        [$start, $end] = $this->monthWindow($anchor);
        [$previousStart, $previousEnd] = $this->monthWindow($start->subDay());

        $cashEntries = collect($this->repository->for($company, 'cash_entries')->all());
        $current = $this->filterBetween($cashEntries, $start, $end);
        $previous = $this->filterBetween($cashEntries, $previousStart, $previousEnd);

        $shape = [
            'insufficient_data' => true,
            'company' => $company,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'previous_start' => $previousStart->toDateString(),
                'previous_end' => $previousEnd->toDateString(),
            ],
            'revenue' => 0.0,
            'expenses' => 0.0,
            'margin' => 0.0,
            'trend' => ['direction' => 'flat', 'percent' => 0.0],
            'highlights' => [],
        ];

        $totalPoints = $current->count() + $previous->count();
        if ($totalPoints < (int) config('analytics.min_data_points')) {
            return $shape;
        }

        $shape['insufficient_data'] = false;
        $shape['revenue'] = $this->sumDirection($current, 'in');
        $shape['expenses'] = $this->sumDirection($current, 'out');
        $shape['margin'] = $shape['revenue'] - $shape['expenses'];
        $shape['trend'] = $this->trend($current, $previous);
        $shape['highlights'] = $this->highlights($company, $current);

        return $shape;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthWindow(CarbonImmutable $anchor): array
    {
        return [$anchor->startOfMonth(), $anchor->endOfMonth()];
    }

    /** @param Collection<int, array<string, mixed>> $entries */
    private function filterBetween(Collection $entries, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $entries->filter(static function (array $entry) use ($start, $end): bool {
            $date = strtotime((string) ($entry['entry_date'] ?? ''));
            if ($date === false) {
                return false;
            }

            return $date >= $start->getTimestamp() && $date <= $end->getTimestamp();
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $entries */
    private function sumDirection(Collection $entries, string $direction): float
    {
        return (float) $entries
            ->filter(static fn (array $entry): bool => ($entry['direction'] ?? null) === $direction)
            ->sum(static fn (array $entry): float => (float) ($entry['amount'] ?? 0));
    }

    /**
     * Arah tren omzet terhadap periode sebelumnya. Perubahan di bawah
     * `analytics.trend_threshold_percent` persen dianggap datar.
     *
     * @param  Collection<int, array<string, mixed>>  $current
     * @param  Collection<int, array<string, mixed>>  $previous
     * @return array{direction: string, percent: float}
     */
    private function trend(Collection $current, Collection $previous): array
    {
        $currentRevenue = $this->sumDirection($current, 'in');
        $previousRevenue = $this->sumDirection($previous, 'in');

        if ($previousRevenue <= 0) {
            return ['direction' => $currentRevenue > 0 ? 'up' : 'flat', 'percent' => 0.0];
        }

        $percent = round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
        $threshold = (float) config('analytics.trend_threshold_percent');

        return [
            'direction' => abs($percent) < $threshold ? 'flat' : ($percent > 0 ? 'up' : 'down'),
            'percent' => $percent,
        ];
    }

    /**
     * Sorotan generik yang bisa ditindaklanjuti: pengeluaran terbesar dan
     * pelanggan paling aktif berdasarkan order terbanyak (D-31: istilah
     * generik, tanpa nama industri).
     *
     * @param  Collection<int, array<string, mixed>>  $current
     * @return list<array{type: string, label: string, detail: string}>
     */
    private function highlights(string $company, Collection $current): array
    {
        $highlights = [];

        $expenses = $current
            ->filter(static fn (array $entry): bool => ($entry['direction'] ?? null) === 'out')
            ->sortByDesc(static fn (array $entry): float => (float) ($entry['amount'] ?? 0))
            ->take((int) config('analytics.highlights.top_expenses'));
        foreach ($expenses as $entry) {
            $highlights[] = [
                'type' => 'top_expense',
                'label' => (string) ($entry['description'] ?? $entry['category'] ?? 'Pengeluaran'),
                'detail' => 'Pengeluaran terbesar: Rp '.number_format((float) ($entry['amount'] ?? 0), 0, ',', '.'),
            ];
        }

        $orders = collect($this->repository->for($company, 'orders')->all());
        $contactCounts = $orders
            ->filter(static fn (array $order): bool => ! empty($order['contact_id']))
            ->groupBy(static fn (array $order): string => (string) $order['contact_id'])
            ->map(static fn (Collection $group): int => $group->count());
        if ($contactCounts->isNotEmpty()) {
            $contacts = collect($this->repository->for($company, 'contacts')->all());
            $top = $contactCounts
                ->sortDesc()
                ->take((int) config('analytics.highlights.top_contacts'));
            foreach ($top as $contactId => $count) {
                // PHP array mengubah key numerik string menjadi int, jadi
                // bandingkan kedua sisi sebagai string.
                $contactId = (string) $contactId;
                $name = 'Kontak #'.$contactId;
                foreach ($contacts as $contact) {
                    if ((string) ($contact['id'] ?? '') === $contactId) {
                        $name = (string) ($contact['name'] ?? $name);
                        break;
                    }
                }
                $highlights[] = [
                    'type' => 'top_contact',
                    'label' => $name,
                    'detail' => 'Kontak paling aktif: '.$count.' pesanan periode tercatat',
                ];
            }
        }

        return $highlights;
    }
}
