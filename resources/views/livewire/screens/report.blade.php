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

    @if ($periods !== [])
        <section aria-labelledby="report-filter-title" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4">
            <h2 id="report-filter-title" class="sr-only">Penyaring periode</h2>
            <div class="flex flex-wrap items-end gap-3">
                <div class="grow sm:grow-0">
                    <label for="report-period" class="block text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Periode</label>
                    <select id="report-period" wire:model.live="period"
                        class="mt-1 w-full rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-base)] px-3 py-2 text-sm text-[var(--erp-text-primary)] sm:w-56">
                        <option value="all">Semua periode</option>
                        @foreach ($periods as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($filtered)
                    <button type="button" wire:click="resetPeriod"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] px-3 py-2 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)]">
                        Semua periode
                    </button>
                @endif
            </div>
        </section>
    @endif

    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Uang masuk{{ $filtered ? ' (periode ini)' : '' }}</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-success)]">{{ number_format($totalIncome, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Uang keluar{{ $filtered ? ' (periode ini)' : '' }}</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-xl font-bold text-[var(--erp-danger)]">{{ number_format($totalExpense, 2, ',', '.') }}</dd>
        </div>
        <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-4 py-3">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Saldo kas seluruh riwayat</dt>
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

    <section aria-labelledby="report-composition-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <h2 id="report-composition-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
            Komposisi uang keluar{{ $filtered ? ' — periode ini' : '' }}
        </h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">Ke mana uang keluar, dikelompokkan menurut kategori yang Anda catat.</p>

        @if ($expenseComposition === [])
            <p class="mt-4 text-sm text-[var(--erp-text-muted)]">Belum ada uang keluar yang tercatat untuk dikelompokkan.</p>
        @else
            <ul role="list" class="mt-4 space-y-3">
                @foreach ($expenseComposition as $slice)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium text-[var(--erp-text-primary)]">{{ $slice['label'] }}</span>
                            <span class="font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-secondary)]">
                                {{ number_format($slice['amount'], 2, ',', '.') }}
                                <span class="ml-1 text-xs text-[var(--erp-text-muted)]">{{ number_format($slice['share'], 1, ',', '.') }}%</span>
                            </span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-[var(--erp-bg-inset)]" aria-hidden="true">
                            <div class="h-full rounded-full bg-[var(--erp-danger)]" style="width: {{ $slice['share'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
