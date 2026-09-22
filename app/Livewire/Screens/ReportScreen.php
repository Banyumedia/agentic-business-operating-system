<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pola layar laporan keuangan, BASIS KAS (D-62).
 *
 * Satu-satunya sumber angkanya adalah buku kas: uang masuk, uang keluar, dan
 * saldo berjalan per periode bulanan. Tagihan yang belum dibayar tidak pernah
 * ikut, jadi laporan ini tidak bisa berubah menjadi laporan akrual karena salah
 * baca kolom.
 *
 * Pajak TIDAK dihitung di sini. Nilainya sudah ditetapkan saat transaksi
 * dicatat; menghitung ulang di lapisan laporan berarti dua tempat memegang
 * rumus yang sama dan bisa menyimpang.
 */
class ReportScreen extends Component
{
    /**
     * Nama bulan dipasang sebagai data, bukan lewat locale, karena aplikasi
     * berjalan dengan locale `en` sementara antarmukanya berbahasa Indonesia.
     *
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    #[Locked]
    public string $module;

    #[Locked]
    public ?string $submodule = null;

    /**
     * Company dipaku saat mount supaya komponen basi dari company lama tidak
     * pernah melaporkan uang company yang baru aktif.
     */
    #[Locked]
    public string $company;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->submodule = $submodule;
        $this->company = app(CompanyContext::class)->current();
    }

    public function render(): View
    {
        $definition = $this->definition();
        $entries = app(EntityRepository::class)
            ->for($this->company(), $definition['entity'])
            ->all();

        $periods = $this->periods($entries);
        $rows = [];
        $balance = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($periods as $period => $totals) {
            $balance += $totals['in'] - $totals['out'];
            $totalIn += $totals['in'];
            $totalOut += $totals['out'];

            $rows[] = [
                'period' => $period,
                'label' => $this->periodLabel($period),
                'income' => round($totals['in'], 2),
                'expense' => round($totals['out'], 2),
                'net' => round($totals['in'] - $totals['out'], 2),
                'balance' => round($balance, 2),
            ];
        }

        return view('livewire.screens.report', [
            'label' => $definition['label'],
            'rows' => $rows,
            'totalIncome' => round($totalIn, 2),
            'totalExpense' => round($totalOut, 2),
            'balance' => round($totalIn - $totalOut, 2),
        ]);
    }

    /**
     * Uang masuk dan keluar per bulan, urut naik.
     *
     * Bulan di antara transaksi pertama dan terakhir tetap muncul walau kosong:
     * bulan yang hilang dari tabel terbaca sebagai bulan yang belum ditutup,
     * padahal artinya tidak ada transaksi. Nol harus terlihat sebagai nol.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, array{in: float, out: float}>
     */
    private function periods(array $entries): array
    {
        $totals = [];

        foreach ($entries as $entry) {
            $direction = $entry['direction'] ?? null;
            $period = $this->periodOf($entry['entry_date'] ?? null);

            if ($period === null || ($direction !== 'in' && $direction !== 'out')) {
                continue;
            }

            $totals[$period] ??= ['in' => 0.0, 'out' => 0.0];
            $totals[$period][$direction] += (float) ($entry['amount'] ?? 0);
        }

        if ($totals === []) {
            return [];
        }

        $months = array_keys($totals);
        sort($months);

        foreach ($this->monthsBetween((string) reset($months), (string) end($months)) as $period) {
            $totals[$period] ??= ['in' => 0.0, 'out' => 0.0];
        }

        ksort($totals);

        return $totals;
    }

    /** Kunci periode `YYYY-MM` dari tanggal entri; null bila tanggalnya tidak terbaca. */
    private function periodOf(mixed $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^(\d{4})-(\d{2})/', $date, $match)) {
            return null;
        }

        $month = (int) $match[2];

        return $month >= 1 && $month <= 12 ? $match[1].'-'.$match[2] : null;
    }

    /**
     * @return list<string>
     */
    private function monthsBetween(string $first, string $last): array
    {
        $months = [];
        [$year, $month] = array_map('intval', explode('-', $first));
        [$lastYear, $lastMonth] = array_map('intval', explode('-', $last));

        while ($year < $lastYear || ($year === $lastYear && $month <= $lastMonth)) {
            $months[] = sprintf('%04d-%02d', $year, $month);

            if (++$month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $months;
    }

    private function periodLabel(string $period): string
    {
        [$year, $month] = array_map('intval', explode('-', $period));

        return (self::MONTHS[$month] ?? $period).' '.$year;
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string, term: string|null} */
    private function definition(): array
    {
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($this->module, $this->submodule), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, $this->submodule);
        abort_if($definition === null, 403);

        return $definition;
    }

    private function company(): string
    {
        // Fail-closed: layar menolak melayani company yang berbeda dari saat mount.
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }
}
