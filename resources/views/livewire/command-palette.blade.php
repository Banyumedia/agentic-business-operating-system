<div
    x-data="{
        opener: null,
        previousOverflow: '',
        openPalette() {
            if (this.$refs.dialog.open) return;
            this.opener = document.activeElement;
            this.previousOverflow = document.documentElement.style.overflow;
            document.documentElement.style.overflow = 'hidden';
            if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
            this.$nextTick(() => this.$refs.searchInput.focus());
        },
        closePalette() {
            if (this.$refs.dialog.open) this.$refs.dialog.close();
        },
        restoreFocus() {
            document.documentElement.style.overflow = this.previousOverflow;
            this.$nextTick(() => this.opener?.focus());
        }
    }"
    x-on:open-command-palette.window="openPalette()"
    x-on:keydown.window.ctrl.k.prevent="openPalette()"
    x-on:keydown.window.meta.k.prevent="openPalette()"
>
    <dialog
        wire:ignore.self
        x-ref="dialog"
        x-on:cancel.prevent="closePalette()"
        x-on:close="restoreFocus()"
        x-on:click="if ($event.target === $refs.dialog) closePalette()"
        class="command-palette-dialog m-0 h-full max-h-none w-full max-w-none bg-transparent p-4 pt-20 sm:pt-32"
        role="dialog"
        aria-modal="true"
        aria-labelledby="command-palette-title"
    >
        <div class="mx-auto w-full max-w-2xl overflow-hidden rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] shadow-[var(--erp-card-shadow)]">
            <div class="flex items-center border-b border-[var(--erp-border)]">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-4 top-4 h-6 w-6 text-[var(--erp-text-muted)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path stroke-linecap="round" d="m20 20-4-4" />
                    </svg>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        x-ref="searchInput"
                        class="h-14 w-full bg-transparent pl-12 pr-4 text-[var(--erp-text-primary)] placeholder:text-[var(--erp-text-muted)] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--erp-focus)] sm:text-lg"
                        placeholder="Cari data atau menu"
                        aria-label="Cari data atau menu"
                    >
                </div>
                <button
                    type="button"
                    x-on:click="closePalette()"
                    class="mr-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-inset)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    aria-label="Tutup pencarian"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <h2 id="command-palette-title" class="sr-only">Pencarian universal</h2>
            <div class="max-h-96 overflow-y-auto p-2" aria-live="polite">
                @if (strlen($search) < 2)
                    <p class="p-8 text-center text-[var(--erp-text-muted)]">Ketik minimal 2 karakter untuk mulai mencari.</p>
                @elseif (count($results) === 0)
                    <p class="p-8 text-center text-[var(--erp-text-muted)]">
                        Tidak ditemukan hasil untuk “<span class="text-[var(--erp-text-primary)]">{{ $search }}</span>”.
                    </p>
                @else
                    <ul class="space-y-1" role="listbox" aria-label="Hasil pencarian">
                        @foreach ($results as $result)
                            <li role="option">
                                <a href="{{ $result['url'] }}" wire:navigate x-on:click="closePalette()" class="flex min-h-11 items-center gap-3 rounded-[var(--erp-radius-md)] p-3 hover:bg-[var(--erp-bg-inset)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)]" aria-hidden="true">{{ $result['icon'] }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-[var(--erp-text-primary)]">{{ $result['title'] }}</span>
                                        <span class="block text-xs text-[var(--erp-text-muted)]">{{ $result['module'] }} · {{ $result['type'] }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </dialog>
</div>
