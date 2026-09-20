<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Tahap diambil dari alur kerja usaha Anda. Tombol yang muncul hanya perpindahan yang sah dari tahap sekarang.
        </p>
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

    @if ($orphans > 0)
        {{-- Baris dengan tahap di luar alur tidak disembunyikan diam-diam. --}}
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-warning-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $orphans }} baris memiliki tahap di luar alur kerja aktif sehingga belum tampil di papan. Perbaiki tahapnya lewat daftar.
        </p>
    @endif

    <div class="overflow-x-auto pb-2 transition-opacity duration-200" wire:loading.class="opacity-50 pointer-events-none" wire:target="move">
        <ol role="list" class="flex min-w-full gap-4">
            @foreach ($columns as $column)
                <li class="flex w-72 shrink-0 flex-col rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)]">
                    <h2 class="flex items-baseline justify-between gap-2 border-b border-[var(--erp-border)] px-4 py-3">
                        <span class="text-sm font-semibold text-[var(--erp-text-primary)]">{{ $column['label'] }}</span>
                        <span class="font-[family-name:var(--erp-font-mono)] text-xs text-[var(--erp-text-muted)]">{{ count($column['cards']) }}</span>
                    </h2>

                    <div class="flex flex-1 flex-col gap-3 p-3">
                        @forelse ($column['cards'] as $card)
                            <article class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] p-3">
                                <h3 class="text-sm font-semibold text-[var(--erp-text-primary)]">{{ $card['title'] }}</h3>

                                @if ($card['meta'] !== [])
                                    <dl class="mt-2 space-y-1">
                                        @foreach ($card['meta'] as $meta)
                                            <div class="flex items-baseline justify-between gap-2">
                                                <dt class="text-xs text-[var(--erp-text-muted)]">{{ $meta['label'] }}</dt>
                                                <dd class="text-xs text-[var(--erp-text-secondary)]">{{ $meta['value'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif

                                @if ($card['transitions'] !== [])
                                    <div class="mt-3 flex flex-wrap gap-1 border-t border-[var(--erp-border)] pt-2">
                                        @foreach ($card['transitions'] as $transition)
                                            <button
                                                type="button"
                                                wire:click="move({{ $card['id'] }}, '{{ $transition['to'] }}')"
                                                wire:loading.attr="disabled"
                                                title="{{ $transition['label'] }}@if ($transition['requires_note']) (butuh alasan)@endif"
                                                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-2 text-xs font-medium text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                                            >
                                                &rarr; {{ $transition['label'] }}@if ($transition['requires_note'])<span aria-hidden="true"> *</span>@endif
                                                <span class="sr-only">untuk {{ $card['title'] }}@if ($transition['requires_note']) (butuh alasan)@endif</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] px-3 py-6 text-center text-xs text-[var(--erp-text-muted)]">
                                Belum ada {{ $term }} di tahap ini.
                            </p>
                        @endforelse
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    {{--
        Transisi mundur wajib beralasan (D-46). Ini bukan aksi fiskal, jadi cukup
        dialog beralasan - bukan ketik "YA" (D-45 tingkat 1).
    --}}
    @if ($pendingCard !== null && $pendingStageLabel !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[color-mix(in_srgb,var(--erp-text-primary)_60%,transparent)] p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="move-dialog-title"
                aria-describedby="move-dialog-description"
                x-data="{ opener: document.activeElement }"
                x-trap.inert.noscroll="true"
                x-init="$nextTick(() => $refs.note?.focus())"
                x-on:keydown.escape.window="$wire.cancelMove().then(() => opener?.focus())"
                class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                tabindex="-1"
            >
                <h2 id="move-dialog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                    Pindahkan ke {{ $pendingStageLabel }}?
                </h2>
                <p id="move-dialog-description" class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                    Perpindahan ini mundur dari tahap sekarang, jadi alasannya dicatat pada riwayat agar koreksi yang sah bisa dibedakan dari perubahan sepihak.
                </p>

                <label for="move-note" class="mt-4 block text-sm font-medium text-[var(--erp-text-primary)]">
                    Alasan <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                </label>
                <textarea
                    id="move-note"
                    x-ref="note"
                    wire:model="note"
                    rows="3"
                    required
                    class="mt-1 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                ></textarea>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        wire:click="cancelMove"
                        wire:loading.attr="disabled"
                        title="Batal"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="confirmMove"
                        wire:loading.attr="disabled"
                        title="Simpan alasan &amp; pindahkan"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                    >
                        <span wire:loading.remove>Simpan alasan &amp; pindahkan</span>
                        <span wire:loading>Memindahkan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
