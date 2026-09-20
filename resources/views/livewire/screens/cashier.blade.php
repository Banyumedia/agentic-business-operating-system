<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
    </header>

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

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section aria-labelledby="catalog-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
            <h2 id="catalog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Pilih</h2>

            @if ($catalog === [])
                <p role="status" class="mt-4 rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] px-4 py-10 text-center text-sm text-[var(--erp-text-secondary)]">
                    Belum ada data yang dapat dijual.
                </p>
            @else
                <ul role="list" class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($catalog as $entry)
                        <li>
                            <button
                                type="button"
                                wire:click="addItem({{ $entry['id'] }})"
                                wire:loading.attr="disabled"
                                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] px-3 py-2 text-left transition hover:border-[var(--erp-accent)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                            >
                                <span class="text-sm font-medium text-[var(--erp-text-primary)]">{{ $entry['name'] }}</span>
                                <span class="font-[family-name:var(--erp-font-mono)] text-xs text-[var(--erp-text-secondary)]">
                                    Rp {{ number_format($entry['price'], 0, ',', '.') }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="cart-title" class="flex flex-col rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6 transition-opacity duration-200" wire:loading.class="opacity-50 pointer-events-none" wire:target="addItem, removeLine, setQty, requestAction, cancelAction, confirmAction">
            <h2 id="cart-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Keranjang</h2>

            @if ($cart === [])
                <p role="status" class="mt-4 rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] px-3 py-8 text-center text-sm text-[var(--erp-text-secondary)]">
                    Keranjang masih kosong.
                </p>
            @else
                <ul role="list" class="mt-4 divide-y divide-[var(--erp-border)]">
                    @foreach ($cart as $index => $line)
                        <li class="flex items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-[var(--erp-text-primary)]">{{ $line['description'] }}</p>
                                <p class="mt-0.5 font-[family-name:var(--erp-font-mono)] text-xs text-[var(--erp-text-muted)]">
                                    Rp {{ number_format($line['unit_price'], 0, ',', '.') }}
                                </p>

                                <label for="qty-{{ $index }}" class="sr-only">Jumlah {{ $line['description'] }}</label>
                                <input
                                    id="qty-{{ $index }}"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value="{{ $line['qty'] }}"
                                    wire:change="setQty({{ $index }}, $event.target.value)"
                                    class="mt-2 min-h-11 w-20 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-2 py-1 text-right font-[family-name:var(--erp-font-mono)] text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                                />
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <span class="font-[family-name:var(--erp-font-mono)] text-sm text-[var(--erp-text-primary)]">
                                    Rp {{ number_format($line['qty'] * $line['unit_price'], 0, ',', '.') }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="removeLine({{ $index }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-2 text-xs font-medium text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                                >
                                    Hapus<span class="sr-only"> {{ $line['description'] }}</span>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{--
                D-44: baris DPP dan PPN hanya ada bila usaha memungut PPN.
                Untuk usaha non-PKP, kosakata pajak tidak dirender sama sekali,
                bukan ditampilkan Rp 0.
            --}}
            <dl class="mt-4 space-y-1 border-t border-[var(--erp-border)] pt-4 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-[var(--erp-text-secondary)]">Subtotal</dt>
                    <dd class="font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)]">Rp {{ number_format($totals['subtotal'], 0, ',', '.') }}</dd>
                </div>

                @if ($showsTax)
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-[var(--erp-text-secondary)]">DPP</dt>
                        <dd class="font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)]">Rp {{ number_format($totals['dpp'], 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-[var(--erp-text-secondary)]">PPN</dt>
                        <dd class="font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)]">Rp {{ number_format($totals['tax'], 2, ',', '.') }}</dd>
                    </div>
                @endif

                <div class="flex items-baseline justify-between gap-3 border-t border-[var(--erp-border)] pt-2">
                    <dt class="font-semibold text-[var(--erp-text-primary)]">Total</dt>
                    <dd class="font-[family-name:var(--erp-font-mono)] text-base font-semibold text-[var(--erp-text-primary)]">Rp {{ number_format($totals['grand_total'], 0, ',', '.') }}</dd>
                </div>
            </dl>

            <div class="mt-4 space-y-2">
                <label for="payment-method" class="block text-sm font-medium text-[var(--erp-text-primary)]">Metode pembayaran</label>
                <select
                    id="payment-method"
                    wire:model="paymentMethod"
                    class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    @foreach (['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS'] as $value => $methodLabel)
                        <option value="{{ $value }}">{{ $methodLabel }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    wire:click="requestAction('checkout')"
                    @disabled($cart === [])
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Selesaikan pembayaran
                </button>

                <button
                    type="button"
                    wire:click="requestAction('clearCart')"
                    @disabled($cart === [])
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Kosongkan keranjang
                </button>
            </div>
        </section>
    </div>

    @if ($confirmLevel !== null)
        <x-confirm-dialog
            :level="$confirmLevel"
            :title="$pendingAction === 'checkout' ? 'Selesaikan pembayaran?' : 'Kosongkan keranjang?'"
            :description="$pendingAction === 'checkout'
                ? 'Transaksi sebesar Rp '.number_format($totals['grand_total'], 0, ',', '.').' akan dicatat sebagai pembayaran yang sudah diterima dan tidak dapat dibatalkan dari layar ini.'
                : 'Seluruh '.count($cart).' baris di keranjang akan dibuang. Belum ada uang yang tercatat, jadi keranjang bisa disusun ulang.'"
            :confirm-label="$pendingAction === 'checkout' ? 'Catat pembayaran' : 'Kosongkan'"
            confirm="confirmAction"
            cancel="cancelAction"
            phrase-model="confirmPhrase"
            :invalid-phrase="$confirmLevel === 'type' && $failure !== null && str_contains($failure, 'Ketik YA') ? $failure : null"
        />
    @endif
</div>
