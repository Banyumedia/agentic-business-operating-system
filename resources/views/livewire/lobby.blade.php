<div class="flex min-h-screen items-center justify-center bg-[var(--erp-bg-base)] px-4 py-10 text-[var(--erp-text-primary)] sm:px-6">
    <div class="w-full max-w-5xl">
        <header class="mb-10 text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--erp-accent)]">Ruang kerja perusahaan</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Agentic BOS</h1>
            <p class="mt-3 text-[var(--erp-text-secondary)]">Pilih modul aktif untuk memulai hari.</p>
        </header>

        @if (count($this->apps) === 0)
            <div class="mx-auto max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 text-center shadow-[var(--erp-card-shadow)]">
                <p class="font-semibold text-[var(--erp-text-primary)]">Belum ada modul aktif</p>
                <p class="mt-2 text-sm text-[var(--erp-text-muted)]">Modul muncul di sini setelah diaktifkan pada pengaturan fitur bisnis.</p>
            </div>
        @endif

        <nav aria-label="Modul perusahaan" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 @if (count($this->apps) === 0) hidden @endif">
            @foreach ($this->apps as $app)
                <a
                    href="{{ $app['route'] }}"
                    wire:navigate
                    aria-label="Buka modul {{ $app['name'] }}"
                    class="group flex min-h-32 items-center gap-4 rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5 shadow-[var(--erp-card-shadow)] transition hover:-translate-y-0.5 hover:border-[var(--erp-accent)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)]" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A1.5 1.5 0 0 1 5.5 4h5A1.5 1.5 0 0 1 12 5.5v5a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 4 10.5v-5ZM12 13.5a1.5 1.5 0 0 1 1.5-1.5h5a1.5 1.5 0 0 1 1.5 1.5v5a1.5 1.5 0 0 1-1.5 1.5h-5a1.5 1.5 0 0 1-1.5-1.5v-5ZM14 4h4a2 2 0 0 1 2 2v4M4 16v2a2 2 0 0 0 2 2h2" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-lg font-bold">{{ $app['name'] }}</span>
                        <span class="mt-1 block text-sm text-[var(--erp-text-muted)]">Buka ruang kerja</span>
                    </span>
                </a>
            @endforeach
        </nav>

        <div class="mt-10 text-center">
            <button
                type="button"
                x-data
                x-on:click="$dispatch('open-command-palette')"
                aria-label="Buka pencarian universal"
                aria-keyshortcuts="Control+K Meta+K"
                class="mx-auto inline-flex min-h-11 items-center gap-3 rounded-full border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-5 py-3 text-sm text-[var(--erp-text-secondary)] shadow-[var(--erp-card-shadow)] hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="m20 20-4-4" />
                </svg>
                <span>Cari data atau menu</span>
                <kbd class="rounded bg-[var(--erp-bg-base)] px-2 py-1 font-mono text-xs" aria-hidden="true">Ctrl K</kbd>
            </button>
            <a
                href="/app/settings"
                wire:navigate
                class="mx-auto mt-4 block w-fit text-sm font-semibold text-[var(--erp-text-secondary)] underline decoration-[var(--erp-border)] underline-offset-4 hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                Pengaturan perusahaan
            </a>

            <livewire:branch-switcher />
        </div>
    </div>
</div>
