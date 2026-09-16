<?php

namespace App\Services\Dashboard;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use InvalidArgumentException;

class WidgetRegistry
{
    /** @var array<string, list<string>> */
    private const REQUIREMENTS = [
        'upcoming_schedule' => ['scheduling'],
        'low_stock' => ['inventory'],
        'kpi_cashflow' => ['finance.cashbook'],
        'deals_pipeline' => ['deals'],
        'pending_approvals' => ['approval_flow'],
    ];

    public function __construct(
        private readonly EntityRepository $repository,
        private readonly CompanyContext $companyContext,
        private readonly FeatureResolver $features,
        private readonly TerminologyResolver $terms,
    ) {}

    public function available(string $key): bool
    {
        if (! isset(self::REQUIREMENTS[$key])) {
            return false;
        }

        foreach (self::REQUIREMENTS[$key] as $capability) {
            if (! $this->features->enabled($capability)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
    public function compose(string $key): array
    {
        if (! $this->available($key)) {
            throw new InvalidArgumentException("Widget tidak tersedia untuk company aktif: {$key}");
        }

        return match ($key) {
            'upcoming_schedule' => $this->upcomingSchedule(),
            'low_stock' => $this->lowStock(),
            'kpi_cashflow' => $this->cashFlow(),
            'deals_pipeline' => $this->dealsPipeline(),
            'pending_approvals' => $this->pendingApprovals(),
        };
    }

    /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
    private function upcomingSchedule(): array
    {
        $rows = $this->rows('bookings');
        usort($rows, static fn (array $left, array $right): int => ($left['starts_at'] ?? '') <=> ($right['starts_at'] ?? ''));
        $items = array_map(fn (array $row): array => [
            'primary' => $this->terms->resolve('booking').' #'.($row['id'] ?? '—'),
            'secondary' => $this->formatDateTime($row['starts_at'] ?? null),
        ], array_slice($rows, 0, 3));

        return $this->card('upcoming_schedule', 'Agenda mendatang', (string) count($rows), $this->terms->resolve('bookings').' tercatat', $items, 'info');
    }

    /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
    private function lowStock(): array
    {
        $quantities = [];
        foreach ($this->rows('item_batches') as $batch) {
            $itemId = (string) ($batch['item_id'] ?? '');
            $quantities[$itemId] = ($quantities[$itemId] ?? 0) + (float) ($batch['qty_on_hand'] ?? 0);
        }

        $low = [];
        foreach ($this->rows('items') as $item) {
            $minimum = (float) ($item['min_stock'] ?? 0);
            $onHand = $quantities[(string) ($item['id'] ?? '')] ?? 0;
            if ($minimum > 0 && $onHand <= $minimum) {
                $low[] = [
                    'primary' => (string) ($item['name'] ?? $this->terms->resolve('item')),
                    'secondary' => $this->formatNumber($onHand).' tersedia · minimum '.$this->formatNumber($minimum),
                ];
            }
        }

        return $this->card('low_stock', 'Stok perlu perhatian', (string) count($low), $this->terms->resolve('items').' di bawah batas', array_slice($low, 0, 3), 'warning');
    }

    /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
    private function cashFlow(): array
    {
        $incoming = 0.0;
        $outgoing = 0.0;
        foreach ($this->rows('cash_entries') as $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            if (($entry['direction'] ?? null) === 'in') {
                $incoming += $amount;
            } else {
                $outgoing += $amount;
            }
        }

        return $this->card(
            'kpi_cashflow',
            'Arus kas',
            $this->currency($incoming - $outgoing),
            'Masuk '.$this->currency($incoming).' · keluar '.$this->currency($outgoing),
            [],
            $incoming >= $outgoing ? 'success' : 'danger',
        );
    }

    /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
    private function dealsPipeline(): array
    {
        $stages = [];
        foreach ($this->rows('deals') as $deal) {
            $stage = trim((string) ($deal['stage'] ?? '')) ?: 'Belum ditentukan';
            $stages[$stage] = ($stages[$stage] ?? 0) + 1;
        }

        $items = [];
        foreach ($stages as $stage => $count) {
            $items[] = ['primary' => str($stage)->replace('_', ' ')->title()->toString(), 'secondary' => $count.' '.$this->terms->resolve('deals')];
        }

        return $this->card('deals_pipeline', 'Pipeline '.$this->terms->resolve('deals'), (string) array_sum($stages), 'Di seluruh tahap', array_slice($items, 0, 3), 'accent');
    }

    /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
    private function pendingApprovals(): array
    {
        $rows = $this->rows('quotations');
        $items = array_map(static fn (array $row): array => [
            'primary' => (string) ($row['title'] ?? 'Dokumen'),
            'secondary' => (string) ($row['number'] ?? 'Menunggu tinjauan'),
        ], array_slice($rows, 0, 3));

        return $this->card('pending_approvals', 'Perlu persetujuan', (string) count($rows), 'Dokumen menunggu tinjauan', $items, 'warning');
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $entity): array
    {
        return $this->repository->for($this->companyContext->current(), $entity)->all();
    }

    /** @param list<array{primary: string, secondary: string}> $items
     * @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string}
     */
    private function card(string $key, string $title, string $value, string $meta, array $items, string $tone): array
    {
        return compact('key', 'title', 'value', 'meta', 'items', 'tone');
    }

    private function currency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function formatNumber(float $number): string
    {
        return number_format($number, 0, ',', '.');
    }

    private function formatDateTime(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'Waktu belum ditentukan';
        }

        return date('d M Y · H:i', strtotime($value));
    }
}
