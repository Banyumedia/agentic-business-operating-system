<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
            <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
                Belum tertagih: <span class="font-[family-name:var(--erp-font-mono)] tabular-nums font-semibold text-[var(--erp-text-primary)]">{{ number_format($outstanding, 2, ',', '.') }}</span>
                &middot; dihitung dari tagihan yang sudah diterbitkan.
            </p>
        </div>

        <button
            type="button"
            data-contract-focus-fallback
            wire:click="create"
            wire:loading.attr="disabled"
            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
        >
            Buat {{ $term }}
        </button>
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
        <section aria-labelledby="contract-form-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
            <h2 id="contract-form-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                {{ $editingId === null ? 'Buat' : 'Ubah' }} {{ $term }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="contract-number" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                            Nomor <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                        </label>
                        <input id="contract-number" type="text" wire:model="form.number" required maxlength="64"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="contract-title" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                            Keterangan <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                        </label>
                        <input id="contract-title" type="text" wire:model="form.title" required maxlength="191"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="contract-issue-date" class="block text-sm font-medium text-[var(--erp-text-primary)]">
                            Tanggal terbit <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                        </label>
                        <input id="contract-issue-date" type="date" wire:model="form.issue_date" required
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="contract-due-date" class="block text-sm font-medium text-[var(--erp-text-primary)]">Jatuh tempo</label>
                        <input id="contract-due-date" type="date" wire:model="form.due_date"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="contract-contact" class="block text-sm font-medium text-[var(--erp-text-primary)]">{{ $contactLabel }}</label>
                        <select id="contract-contact" wire:model="form.contact_id"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            <option value="">Tanpa {{ $contactLabel }}</option>
                            @foreach ($contacts as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="contract-project" class="block text-sm font-medium text-[var(--erp-text-primary)]">{{ $projectLabel }}</label>
                        <select id="contract-project" wire:model="form.project_id"
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            <option value="">Tanpa {{ $projectLabel }}</option>
                            @foreach ($projects as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-[var(--erp-text-muted)]">Menempelkan tagihan ke {{ strtolower($projectLabel) }} membuat nilainya ikut terhitung pada laba-rugi {{ strtolower($projectLabel) }}.</p>
                    </div>

                    @if ($milestones !== [])
                        <div class="space-y-1.5 sm:col-span-2">
                            <label for="contract-milestone" class="block text-sm font-medium text-[var(--erp-text-primary)]">Tagih dari termin</label>
                            <div class="flex flex-wrap gap-2">
                                <select id="contract-milestone" wire:model="form.milestone_id"
                                    class="min-h-11 flex-1 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                                    <option value="">Tanpa termin</option>
                                    @foreach ($milestones as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="loadMilestone" wire:loading.attr="disabled"
                                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                                    Muat rincian
                                </button>
                            </div>
                            <p class="text-xs text-[var(--erp-text-muted)]">Hanya termin yang belum pernah ditagih yang tampil di sini.</p>
                        </div>
                    @endif
                </div>

                <fieldset class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] p-4">
                    <legend class="px-1 text-sm font-semibold text-[var(--erp-text-primary)]">Rincian</legend>

                    <ol role="list" class="space-y-3">
                        @foreach ($lines as $index => $line)
                            <li wire:key="line-{{ $index }}" class="grid gap-3 sm:grid-cols-[1fr_7rem_10rem_auto]">
                                <div class="space-y-1">
                                    <label for="line-desc-{{ $index }}" class="block text-xs font-medium text-[var(--erp-text-secondary)]">Keterangan</label>
                                    <input id="line-desc-{{ $index }}" type="text" wire:model="lines.{{ $index }}.description" maxlength="255"
                                        class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                                </div>
                                <div class="space-y-1">
                                    <label for="line-qty-{{ $index }}" class="block text-xs font-medium text-[var(--erp-text-secondary)]">Jumlah</label>
                                    <input id="line-qty-{{ $index }}" type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="lines.{{ $index }}.quantity"
                                        class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-right text-sm font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                                </div>
                                <div class="space-y-1">
                                    <label for="line-price-{{ $index }}" class="block text-xs font-medium text-[var(--erp-text-secondary)]">Harga satuan</label>
                                    <input id="line-price-{{ $index }}" type="number" step="0.01" min="0" wire:model.live.debounce.500ms="lines.{{ $index }}.unit_price"
                                        class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-right text-sm font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                                </div>
                                <div class="flex items-end">
                                    <button type="button" wire:click="removeLine({{ $index }})" wire:loading.attr="disabled"
                                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-3 text-sm font-medium text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                                        Hapus<span class="sr-only"> rincian baris {{ $index + 1 }}</span>
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <button type="button" wire:click="addLine" wire:loading.attr="disabled"
                        class="mt-3 inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                        Tambah rincian
                    </button>
                </fieldset>

                <dl class="grid gap-2 rounded-[var(--erp-radius-md)] bg-[var(--erp-bg-base)] p-4 sm:max-w-sm sm:justify-self-end">
                    <div class="flex items-baseline justify-between gap-6">
                        <dt class="text-sm text-[var(--erp-text-secondary)]">Subtotal</dt>
                        <dd class="font-[family-name:var(--erp-font-mono)] tabular-nums text-sm text-[var(--erp-text-primary)]">{{ number_format($totals['subtotal'], 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-6">
                        <dt class="text-sm text-[var(--erp-text-secondary)]">Dasar pengenaan</dt>
                        <dd class="font-[family-name:var(--erp-font-mono)] tabular-nums text-sm text-[var(--erp-text-primary)]">{{ number_format($totals['dpp'], 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-6">
                        <dt class="text-sm text-[var(--erp-text-secondary)]">Pajak</dt>
                        <dd class="font-[family-name:var(--erp-font-mono)] tabular-nums text-sm text-[var(--erp-text-primary)]">{{ number_format($totals['tax'], 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-6 border-t border-[var(--erp-border)] pt-2">
                        <dt class="text-sm font-semibold text-[var(--erp-text-primary)]">Total</dt>
                        <dd class="font-[family-name:var(--erp-font-mono)] tabular-nums text-base font-bold text-[var(--erp-text-primary)]">{{ number_format($totals['grand_total'], 2, ',', '.') }}</dd>
                    </div>
                </dl>

                <div class="flex flex-wrap gap-3 border-t border-[var(--erp-border)] pt-4">
                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove>Simpan draf</span>
                        <span wire:loading>Menyimpan…</span>
                    </button>
                    <button type="button" wire:click="cancel" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] transition hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                        Batal
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section aria-labelledby="contract-list-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <h2 id="contract-list-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">{{ $label }}</h2>

        <div class="mt-4 overflow-x-auto" wire:loading.class="opacity-50 pointer-events-none" wire:target="issue, save, edit">
            <table class="w-full text-sm">
                <caption class="sr-only">Daftar {{ $label }}</caption>
                <thead>
                    <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                        <th scope="col" class="py-2 pr-3">Nomor</th>
                        <th scope="col" class="py-2 pr-3">Keterangan</th>
                        <th scope="col" class="py-2 pr-3">Terbit</th>
                        <th scope="col" class="py-2 pr-3">Status</th>
                        <th scope="col" class="py-2 pr-3 text-right">Total</th>
                        <th scope="col" class="py-2 pr-3 text-right">Belum dibayar</th>
                        <th scope="col" class="py-2"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="invoice-{{ $row['id'] }}" class="border-b border-[var(--erp-border)] last:border-0">
                            <td class="py-3 pr-3 font-[family-name:var(--erp-font-mono)] text-[var(--erp-text-primary)]">{{ $row['number'] }}</td>
                            <td class="py-3 pr-3 text-[var(--erp-text-primary)]">{{ $row['title'] }}</td>
                            <td class="py-3 pr-3 text-[var(--erp-text-secondary)]">{{ $row['issue_date'] }}</td>
                            <td class="py-3 pr-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $row['status'] === 'paid' ? 'bg-[var(--erp-success-soft)] text-[var(--erp-success)]' : ($row['is_draft'] ? 'bg-[var(--erp-bg-inset)] text-[var(--erp-text-secondary)]' : 'bg-[var(--erp-warning-soft)] text-[var(--erp-warning)]') }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)]">{{ number_format($row['grand_total'], 2, ',', '.') }}</td>
                            <td class="py-3 pr-3 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-secondary)]">{{ $row['is_draft'] ? '—' : number_format($row['outstanding'], 2, ',', '.') }}</td>
                            <td class="py-3">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if ($row['is_draft'])
                                        <button type="button" wire:click="edit({{ $row['id'] }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-2 text-xs font-medium text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                                            Ubah<span class="sr-only"> {{ $row['number'] }}</span>
                                        </button>
                                        <button type="button" wire:click="requestIssue({{ $row['id'] }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-2 text-xs font-semibold text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                                            Terbitkan<span class="sr-only"> {{ $row['number'] }}</span>
                                        </button>
                                    @elseif ($row['outstanding'] > 0)
                                        <button type="button" wire:click="requestPayment({{ $row['id'] }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-2 text-xs font-semibold text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                                            Catat pembayaran<span class="sr-only"> {{ $row['number'] }}</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-[var(--erp-text-muted)]">
                                Belum ada {{ $term }}. Mulai dengan menekan "Buat {{ $term }}".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Pencatatan pembayaran menulis uang masuk ke buku kas, jadi nominalnya
         dikonfirmasi dulu (D-45 tingkat 2). --}}
    @if ($pendingPayment !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[color-mix(in_srgb,var(--erp-text-primary)_60%,transparent)] p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="payment-dialog-title"
                aria-describedby="payment-dialog-description"
                x-data="{ ready: false, opener: document.activeElement }"
                x-trap.inert.noscroll="true"
                x-init="setTimeout(() => ready = true, 400); $nextTick(() => $refs.amount?.focus())"
                x-on:keydown.escape.window="const target = opener; $wire.cancelPayment().then(() => target?.focus())"
                class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)] focus:outline-none"
                tabindex="-1"
            >
                <h2 id="payment-dialog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                    Catat pembayaran {{ $pendingPayment['number'] }}
                </h2>
                <p id="payment-dialog-description" class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                    Nominal ini dicatat sebagai uang masuk di Buku Kas dan ikut terhitung pada laba-rugi {{ strtolower($projectLabel) }} bila tagihan ini menempel ke sana.
                </p>

                <label for="payment-amount" class="mt-4 block text-sm font-medium text-[var(--erp-text-primary)]">
                    Nominal <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                </label>
                <input id="payment-amount" x-ref="amount" type="number" step="0.01" min="0" wire:model="paymentAmount"
                    class="mt-1 min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-right text-sm font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button"
                        x-on:click="const target = opener; $wire.cancelPayment().then(() => target?.focus())"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        Batal
                    </button>
                    <button type="button"
                        x-on:click="const target = opener; $wire.recordPayment().then(() => $nextTick(() => { if (target?.isConnected) { target.focus(); } else { document.querySelector('[data-contract-focus-fallback]')?.focus(); } }))"
                        disabled
                        x-bind:disabled="! ready"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove>Catat</span>
                        <span wire:loading>Mencatat…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{--
        Penerbitan mengubah dokumen draf menjadi tagihan resmi dan nilainya
        berhenti bisa diubah, jadi butuh konfirmasi dua tombol (D-45 tingkat 2).
    --}}
    @if ($pendingIssue !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[color-mix(in_srgb,var(--erp-text-primary)_60%,transparent)] p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="issue-dialog-title"
                aria-describedby="issue-dialog-description"
                x-data="{ ready: false, opener: document.activeElement }"
                x-trap.inert.noscroll="true"
                x-init="setTimeout(() => ready = true, 400); $nextTick(() => $refs.cancel?.focus())"
                x-on:keydown.escape.window="const target = opener; $wire.cancelIssue().then(() => target?.focus())"
                class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)] focus:outline-none"
                tabindex="-1"
            >
                <h2 id="issue-dialog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                    Terbitkan {{ $pendingIssue['number'] }}?
                </h2>
                <p id="issue-dialog-description" class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                    Setelah diterbitkan, rincian dan nilainya tidak dapat diubah lagi, dan tagihan ini mulai dihitung sebagai belum tertagih.
                </p>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" x-ref="cancel"
                        x-on:click="const target = opener; $wire.cancelIssue().then(() => target?.focus())"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        Batal
                    </button>
                    <button type="button"
                        x-on:click="const target = opener; $wire.issue().then(() => $nextTick(() => { if (target?.isConnected) { target.focus(); } else { document.querySelector('[data-contract-focus-fallback]')?.focus(); } }))"
                        disabled
                        x-bind:disabled="! ready"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove>Terbitkan</span>
                        <span wire:loading>Menerbitkan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
