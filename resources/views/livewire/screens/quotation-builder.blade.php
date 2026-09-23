<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
            <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
                Susun rincian penawaran lalu konversi yang disetujui menjadi proyek.
            </p>
        </div>

        @unless ($editing)
            <button type="button" wire:click="create" wire:loading.attr="disabled"
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                Tambah {{ $term }}
            </button>
        @endunless
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

    @if ($editing)
        <section aria-labelledby="quotation-form-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
            <h2 id="quotation-form-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                {{ $editingId === null ? 'Tambah' : 'Ubah' }} {{ $term }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="quotation-title" class="block text-sm font-medium text-[var(--erp-text-primary)]">Judul</label>
                        <input id="quotation-title" type="text" wire:model="form.title" required
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="quotation-contact" class="block text-sm font-medium text-[var(--erp-text-primary)]">Kontak</label>
                        <select id="quotation-contact" wire:model="form.contact_id"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            <option value="">Tanpa kontak</option>
                            @foreach ($contactOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="quotation-valid-until" class="block text-sm font-medium text-[var(--erp-text-primary)]">Berlaku sampai</label>
                        <input id="quotation-valid-until" type="date" wire:model="form.valid_until"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="quotation-notes" class="block text-sm font-medium text-[var(--erp-text-primary)]">Catatan</label>
                    <textarea id="quotation-notes" wire:model="form.notes" rows="2"
                        class="w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"></textarea>
                </div>

                <div class="space-y-3 border-t border-[var(--erp-border)] pt-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-[var(--erp-text-primary)]">Rincian (RAB)</h3>
                        <button type="button" wire:click="addLine"
                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-3 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            + Tambah baris
                        </button>
                    </div>

                    @foreach ($lines as $index => $line)
                        <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto_auto]">
                            <div class="space-y-1">
                                <label for="line-description-{{ $index }}" class="sr-only">Keterangan baris {{ $index + 1 }}</label>
                                <input id="line-description-{{ $index }}" type="text" wire:model="lines.{{ $index }}.description" placeholder="Keterangan"
                                    class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                            </div>
                            <div class="space-y-1">
                                <label for="line-quantity-{{ $index }}" class="sr-only">Jumlah baris {{ $index + 1 }}</label>
                                <input id="line-quantity-{{ $index }}" type="number" step="0.0001" wire:model="lines.{{ $index }}.quantity" placeholder="Jml"
                                    class="min-h-11 w-24 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                            </div>
                            <div class="space-y-1">
                                <label for="line-price-{{ $index }}" class="sr-only">Harga baris {{ $index + 1 }}</label>
                                <input id="line-price-{{ $index }}" type="number" step="0.01" wire:model="lines.{{ $index }}.unit_price" placeholder="Harga"
                                    class="min-h-11 w-32 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                            </div>
                            <button type="button" wire:click="removeLine({{ $index }})"
                                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-3 text-sm font-semibold text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                                Hapus
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="ml-auto max-w-xs space-y-1 border-t border-[var(--erp-border)] pt-4 text-sm">
                    <div class="flex justify-between gap-6">
                        <span class="text-[var(--erp-text-secondary)]">Subtotal</span>
                        <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) $totals['subtotal'], 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-6 border-t border-[var(--erp-border)] pt-1 font-bold">
                        <span>Total</span>
                        <span class="font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) $totals['grand_total'], 2, ',', '.') }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-[var(--erp-border)] pt-4">
                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Menyimpan…</span>
                    </button>
                    <button type="button" wire:click="cancel"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        Batal
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section aria-labelledby="quotation-list-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 id="quotation-list-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Daftar {{ $term }}</h2>

        @if ($quotations === [])
            <p class="mt-4 text-sm text-[var(--erp-text-secondary)]">Belum ada {{ $term }} yang tercatat.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Daftar penawaran</caption>
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                            <th scope="col" class="py-2 pr-3">Nomor</th>
                            <th scope="col" class="py-2 pr-3">Judul</th>
                            <th scope="col" class="py-2 pr-3 text-right">Total</th>
                            <th scope="col" class="py-2 pr-3">Status</th>
                            <th scope="col" class="py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotations as $quotation)
                            <tr class="border-b border-[var(--erp-border)] last:border-0">
                                <td class="py-2 pr-3 font-[family-name:var(--erp-font-mono)]">{{ $quotation['number'] ?? '' }}</td>
                                <td class="py-2 pr-3 text-[var(--erp-text-primary)]">{{ $quotation['title'] ?? '' }}</td>
                                <td class="py-2 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums">{{ number_format((float) ($quotation['grand_total'] ?? 0), 2, ',', '.') }}</td>
                                <td class="py-2 pr-3">
                                    @if (($quotation['project_id'] ?? null) !== null)
                                        <span class="inline-flex rounded-full bg-[var(--erp-success-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--erp-success)]">Jadi proyek</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-[var(--erp-info-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--erp-info)]">{{ ucfirst($quotation['stage'] ?? 'draft') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if (($quotation['project_id'] ?? null) === null)
                                            @if (($quotation['stage'] ?? 'draft') === 'draft')
                                                <button type="button" wire:click="edit({{ $quotation['id'] }})" class="text-xs font-semibold text-[var(--erp-accent)] hover:underline">Ubah</button>
                                                <button type="button" wire:click="markSent({{ $quotation['id'] }})" class="text-xs font-semibold text-[var(--erp-accent)] hover:underline">Kirim</button>
                                            @elseif (($quotation['stage'] ?? 'draft') === 'sent')
                                                <button type="button" wire:click="markApproved({{ $quotation['id'] }})" class="text-xs font-semibold text-[var(--erp-accent)] hover:underline">Setujui</button>
                                            @elseif (($quotation['stage'] ?? 'draft') === 'approved' && $isOwner)
                                                <button type="button" wire:click="requestConvert({{ $quotation['id'] }})" class="text-xs font-semibold text-[var(--erp-accent)] hover:underline">Jadikan proyek</button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($pendingConvertId !== null)
        <x-confirm-dialog
            level="simple"
            title="Jadikan penawaran ini proyek?"
            description="Sebuah proyek baru akan dibuat dengan nilai dari penawaran ini. Tindakan ini tidak dapat dibatalkan."
            confirm-label="Jadikan proyek"
            confirm="confirmConvert"
            cancel="cancelConvert"
        />
    @endif
</div>
