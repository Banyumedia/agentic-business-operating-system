<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Selisih hasil opname dicatat sebagai mutasi, bukan menimpa saldo langsung.
        </p>
    </header>

    @if (! $isOwner)
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            Hanya owner yang dapat menyesuaikan stok.
        </p>
    @endif

    @if ($notice !== null)
        <p role="status" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-success)] bg-[var(--erp-success-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $notice }}
        </p>
    @endif

    @if ($failure !== null)
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $failure }}
        </p>
    @endif

    <section aria-labelledby="adjustment-form-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 id="adjustment-form-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Catat penyesuaian</h2>

        <form wire:submit="adjust" class="mt-4 space-y-4">
            <div class="space-y-1.5">
                <label for="adjustment-item" class="block text-sm font-medium text-[var(--erp-text-primary)]">Barang</label>
                <select id="adjustment-item" wire:model.live="itemId" required
                    class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                    <option value="">Pilih barang</option>
                    @foreach ($items as $item)
                        <option value="{{ $item['id'] }}">{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </div>

            @if ($currentBalance !== null)
                <p class="text-sm text-[var(--erp-text-secondary)]">
                    Saldo saat ini:
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold text-[var(--erp-text-primary)]">{{ rtrim(rtrim(number_format($currentBalance, 3, ',', '.'), '0'), ',') }}</span>
                </p>
            @endif

            <div class="space-y-1.5">
                <label for="adjustment-delta" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                    Selisih <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                </label>
                <input id="adjustment-delta" type="number" step="0.001" wire:model="delta" required
                    placeholder="mis. -2 (berkurang 2) atau 5 (bertambah 5)"
                    class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                <p class="text-xs text-[var(--erp-text-muted)]">Positif menambah, negatif mengurangi.</p>
            </div>

            <div class="space-y-1.5">
                <label for="adjustment-reason" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                    Alasan <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                </label>
                <textarea id="adjustment-reason" wire:model="reason" rows="2" required
                    placeholder="mis. hasil opname fisik, barang rusak, dsb."
                    class="w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"></textarea>
            </div>

            <button type="submit" wire:loading.attr="disabled" @disabled(! $isOwner)
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove>Catat penyesuaian</span>
                <span wire:loading>Menyimpan…</span>
            </button>
        </form>
    </section>

    @if ($history !== [])
        <section aria-labelledby="adjustment-history-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
            <h2 id="adjustment-history-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Riwayat mutasi</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Riwayat mutasi stok</caption>
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                            <th scope="col" class="py-2 pr-3">Waktu</th>
                            <th scope="col" class="py-2 pr-3">Arah</th>
                            <th scope="col" class="py-2 pr-3 text-right">Jumlah</th>
                            <th scope="col" class="py-2 pr-3">Alasan</th>
                            <th scope="col" class="py-2 text-right">Saldo setelah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $entry)
                            <tr class="border-b border-[var(--erp-border)] last:border-0">
                                <td class="py-2 pr-3 text-[var(--erp-text-secondary)]">{{ $entry['occurred_at'] ?? '—' }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $entry['direction'] === 'in' ? 'bg-[var(--erp-success-soft)] text-[var(--erp-success)]' : 'bg-[var(--erp-danger-soft)] text-[var(--erp-danger)]' }}">
                                        {{ $entry['direction'] === 'in' ? 'Masuk' : 'Keluar' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ rtrim(rtrim(number_format($entry['qty'], 3, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 pr-3 text-[var(--erp-text-secondary)]">{{ $entry['reason'] }}</td>
                                <td class="py-2 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ rtrim(rtrim(number_format($entry['balance_after'], 3, ',', '.'), '0'), ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
