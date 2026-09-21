<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">Laba-Rugi {{ $label }}</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Dihitung <strong class="text-[var(--erp-text-primary)]">basis kas</strong>: hanya uang yang sudah diterima dan sudah dikeluarkan.
        </p>
    </header>

    {{-- Peringatan ini bukan hiasan: tanpa itu angka laba bisa dibaca sebagai
         akrual saat pemilik menetapkan harga. --}}
    <p role="note" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-info)] bg-[var(--erp-info-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
        Tagihan yang sudah terbit tapi belum dibayar <strong>tidak</strong> dihitung sebagai pendapatan, dan biaya yang belum dibayar belum dihitung sebagai biaya. Kolom "Belum dibayar" berada di luar perhitungan laba.
    </p>

    <dl class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Uang masuk</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-success)]">{{ number_format($totalIncome, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Uang keluar</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-danger)]">{{ number_format($totalExpense, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Laba kas</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold {{ $totalProfit < 0 ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-text-primary)]' }}">{{ number_format($totalProfit, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Belum dibayar <span class="font-normal normal-case">(di luar laba)</span></dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-warning)]">{{ number_format($totalReceivable, 2, ',', '.') }}</dd>
        </div>
    </dl>

    <section aria-labelledby="margin-list-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <h2 id="margin-list-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Per {{ $term }}</h2>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">Laba-rugi basis kas per {{ $term }}</caption>
                <thead>
                    <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                        <th scope="col" class="py-2 pr-3">{{ $term }}</th>
                        <th scope="col" class="py-2 pr-3 text-right">Uang masuk</th>
                        <th scope="col" class="py-2 pr-3 text-right">Uang keluar</th>
                        <th scope="col" class="py-2 pr-3 text-right">Laba kas</th>
                        <th scope="col" class="py-2 text-right">Belum dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="margin-{{ $row['id'] }}" class="border-b border-[var(--erp-border)] last:border-0">
                            <td class="py-3 pr-3 text-[var(--erp-text-primary)]">{{ $row['name'] }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-secondary)]">{{ number_format($row['income'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-secondary)]">{{ number_format($row['expense'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold {{ $row['profit'] < 0 ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-text-primary)]' }}">{{ number_format($row['profit'], 2, ',', '.') }}</td>
                            <td class="py-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-muted)]">{{ number_format($row['receivable'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-[var(--erp-text-muted)]">
                                Belum ada {{ $term }} yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($unassigned['income'] > 0 || $unassigned['expense'] > 0)
            <p class="mt-4 rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] px-4 py-3 text-sm text-[var(--erp-text-secondary)]">
                Ada uang masuk <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format($unassigned['income'], 2, ',', '.') }}</span>
                dan uang keluar <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format($unassigned['expense'], 2, ',', '.') }}</span>
                yang belum dibebankan ke {{ strtolower($term) }} mana pun, jadi belum terhitung di tabel ini. Bebankan lewat Entri Kas bila memang milik salah satu {{ strtolower($term) }}.
            </p>
        @endif
    </section>
</div>
