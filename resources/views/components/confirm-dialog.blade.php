@props([
    'level' => 'simple',
    'title',
    'description',
    'confirmLabel' => 'Lanjutkan',
    'confirm',
    'cancel',
    'phraseModel' => null,
])

{{--
    Dialog konfirmasi bertingkat (D-45).

    level = "type"   -> aksi ireversibel berdampak fiskal: wajib mengetik YA
    level = "simple" -> destruktif tapi dapat dipulihkan: cukup dua tombol

    Tombol aksi inert 400 ms sejak dialog muncul supaya tap yang membuka dialog
    tidak ikut menekan tombol di bawahnya. Tekan-dan-tahan dilarang di semua
    tingkat, jadi tidak ada varian itu di sini.
--}}
<div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center">
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        aria-describedby="confirm-dialog-description"
        x-data="{ ready: false, opener: document.activeElement }"
        x-trap.inert.noscroll="true"
        x-init="setTimeout(() => ready = true, 400); $nextTick(() => $refs.cancel?.focus())"
        x-on:keydown.escape.window="const target = opener; $wire.{{ $cancel }}().then(() => target?.focus())"
        class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)]"
    >
        <h2 id="confirm-dialog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">{{ $title }}</h2>
        <p id="confirm-dialog-description" class="mt-2 text-sm text-[var(--erp-text-secondary)]">{{ $description }}</p>

        @if ($level === 'type')
            <label for="confirm-dialog-phrase" class="mt-4 block text-sm font-medium text-[var(--erp-text-primary)]">
                Ketik <strong>YA</strong> untuk menegaskan
            </label>
            <input
                id="confirm-dialog-phrase"
                type="text"
                inputmode="text"
                autocapitalize="characters"
                autocorrect="off"
                autocomplete="off"
                spellcheck="false"
                wire:model="{{ $phraseModel }}"
                class="mt-1 min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 font-[family-name:var(--erp-font-mono)] text-sm uppercase text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            />
        @endif

        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <button
                type="button"
                x-ref="cancel"
                x-on:click="const target = opener; $wire.{{ $cancel }}().then(() => target?.focus())"
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                Batal
            </button>
            <button
                type="button"
                x-on:click="const target = opener; $wire.{{ $confirm }}().then(() => target?.focus())"
                disabled
                x-bind:disabled="! ready"
                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-danger)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ $confirmLabel }}
            </button>
        </div>
    </div>
</div>
