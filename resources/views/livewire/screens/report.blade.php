<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Arus kas per bulan, dihitung <strong class="text-[var(--erp-text-primary)]">basis kas</strong>: hanya uang yang sudah diterima dan sudah dikeluarkan.
        </p>
    </header>

    {{-- Peringatan ini menjaga arti angkanya: tanpa itu saldo bisa dibaca
         sebagai laba akrual yang memuat tagihan belum dibayar. --}}
    <p role="note" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-info)] bg-[var(--erp-info-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
        Tagihan yang sudah terbit tapi belum dibayar <strong>tidak</strong> masuk ke laporan ini, dan biaya yang belum dibayar belum dihitung sebagai biaya.
    </p>

    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Uang masuk</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-success)]">{{ number_format($totalIncome, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Uang keluar</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-danger)]">{{ number_format($totalExpense, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Saldo kas</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold {{ $balance < 0 ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-text-primary)]' }}">{{ number_format($balance, 2, ',', '.') }}</dd>
        </div>
    </dl>

    <section aria-labelledby="report-period-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <h2 id="report-period-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Per periode</h2>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">Arus kas basis kas per bulan, dengan saldo berjalan</caption>
                <thead>
                    <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                        <th scope="col" class="py-2 pr-3">Periode</th>
                        <th scope="col" class="py-2 pr-3 text-right">Uang masuk</th>
                        <th scope="col" class="py-2 pr-3 text-right">Uang keluar</th>
                        <th scope="col" class="py-2 pr-3 text-right">Selisih</th>
                        <th scope="col" class="py-2 text-right">Saldo berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="report-{{ $row['period'] }}" class="border-b border-[var(--erp-border)] last:border-0">
                            <td class="py-3 pr-3 text-[var(--erp-text-primary)]">{{ $row['label'] }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-secondary)]">{{ number_format($row['income'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-secondary)]">{{ number_format($row['expense'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold {{ $row['net'] < 0 ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-text-primary)]' }}">{{ number_format($row['net'], 2, ',', '.') }}</td>
                            <td class="py-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums {{ $row['balance'] < 0 ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-text-muted)]' }}">{{ number_format($row['balance'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-[var(--erp-text-muted)]">
                                Belum ada uang masuk atau keluar yang tercatat, jadi belum ada periode untuk dilaporkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
