<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
            <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
                {{ $total }} data tercatat.
            </p>
        </div>

        <button
            type="button"
            data-list-focus-fallback
            wire:click="create"
            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
        >
            Tambah {{ $term }}
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
        <section aria-labelledby="entity-form-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
            <h2 id="entity-form-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                {{ $editingId === null ? 'Tambah' : 'Ubah' }} {{ $term }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $field)
                        <x-form-field :field="$field" :model="'form.'.$field['field']" />
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-3 border-t border-[var(--erp-border)] pt-4">
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    >
                        Simpan
                    </button>
                    <button
                        type="button"
                        wire:click="cancel"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] transition hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section aria-labelledby="entity-list-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="entity-list-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">{{ $label }}</h2>

            <div class="w-full sm:w-72">
                <label for="entity-search" class="block text-sm font-medium text-[var(--erp-text-primary)]">Cari {{ $term }}</label>
                <input
                    id="entity-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Ketik untuk mencari"
                    class="mt-1 min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] placeholder:text-[var(--erp-text-muted)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                />
            </div>
        </div>

        <div class="mt-4 transition-opacity duration-200" wire:loading.class="opacity-50 pointer-events-none" wire:target="search, goToPage, delete">
            <x-data-table
                :columns="$columns"
                :rows="$rows"
                :sort="$sort"
                :direction="$direction"
                :caption="$label"
                :empty-message="$search === '' ? 'Belum ada '.$term.' yang tercatat.' : 'Tidak ada '.$term.' yang cocok dengan pencarian.'"
                :row-actions="[
                    ['label' => 'Ubah', 'method' => 'edit'],
                    ['label' => 'Hapus', 'method' => 'confirmDelete', 'variant' => 'danger'],
                ]"
            />
        </div>

        @if ($lastPage > 1)
            <nav aria-label="Navigasi halaman" class="mt-4 flex items-center justify-between gap-3 border-t border-[var(--erp-border)] pt-4">
                <button
                    type="button"
                    wire:click="goToPage({{ $page - 1 }})"
                    @disabled($page <= 1)
                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-medium text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:opacity-50"
                >
                    Sebelumnya
                </button>

                <p aria-live="polite" class="text-sm text-[var(--erp-text-secondary)]">
                    Halaman <span class="font-[family-name:var(--erp-font-mono)]">{{ $page }}</span> dari
                    <span class="font-[family-name:var(--erp-font-mono)]">{{ $lastPage }}</span>
                </p>

                <button
                    type="button"
                    wire:click="goToPage({{ $page + 1 }})"
                    @disabled($page >= $lastPage)
                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-medium text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:opacity-50"
                >
                    Berikutnya
                </button>
            </nav>
        @endif
    </section>

    {{--
        Konfirmasi hapus = D-45 tingkat 2: dua tombol, tanpa ketik "YA", karena
        data ini belum berdampak fiskal. Tombol aksi inert 400 ms sejak dialog
        muncul agar tap pembuka tidak ikut menekan tombol merah.
    --}}
    @if ($pendingDeletion !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-dialog-title"
                aria-describedby="delete-dialog-description"
                x-data="{ ready: false, opener: document.activeElement }"
                x-trap.inert.noscroll="true"
                x-init="setTimeout(() => ready = true, 400); $nextTick(() => $refs.cancel?.focus())"
                x-on:keydown.escape.window="const target = opener; $wire.cancelDelete().then(() => target?.focus())"
                class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)] ring-2 ring-[var(--erp-focus)] ring-offset-4 ring-offset-black/60 focus:outline-none"
                tabindex="-1"
            >
                <h2 id="delete-dialog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Hapus {{ $term }}?</h2>

                <p id="delete-dialog-description" class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                    {{ $term }} <strong class="text-[var(--erp-text-primary)]">{{ $pendingDeletion['name'] ?? ('#'.$pendingDeletion['id']) }}</strong>
                    akan dihapus dari daftar {{ $label }}. Baris ini hilang dari semua layar yang memakainya.
                </p>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        x-ref="cancel"
                        x-on:click="const target = opener; $wire.cancelDelete().then(() => target?.focus())"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        x-on:click="const target = opener; $wire.delete().then(() => $nextTick(() => { if (target?.isConnected) { target.focus(); } else { document.querySelector('[data-list-focus-fallback]')?.focus(); } }))"
                        disabled
                        x-bind:disabled="! ready"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-danger)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Hapus permanen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
