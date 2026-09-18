<div class="flex min-h-screen items-center justify-center bg-[var(--erp-bg-base)] px-4 py-10 text-[var(--erp-text-primary)] sm:px-6">
    <div class="w-full max-w-5xl">
        <header class="mb-10 text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--erp-accent)]">Solusi Bisnis</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Cocok untuk bisnis apa?</h1>
            <p class="mt-3 text-[var(--erp-text-secondary)]">Agentic BOS menyediakan preset sistem untuk berbagai model bisnis. Pilih yang sesuai dengan bisnis Anda.</p>
        </header>

        <nav aria-label="Daftar Industri" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($presets as $preset)
                <div class="group flex flex-col justify-between min-h-32 rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5 shadow-[var(--erp-card-shadow)] transition hover:-translate-y-0.5 hover:border-[var(--erp-accent)]">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent-soft)] text-[var(--erp-accent)]" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A1.5 1.5 0 0 1 5.5 4h5A1.5 1.5 0 0 1 12 5.5v5a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 4 10.5v-5ZM12 13.5a1.5 1.5 0 0 1 1.5-1.5h5a1.5 1.5 0 0 1 1.5 1.5v5a1.5 1.5 0 0 1-1.5 1.5h-5a1.5 1.5 0 0 1-1.5-1.5v-5ZM14 4h4a2 2 0 0 1 2 2v4M4 16v2a2 2 0 0 0 2 2h2" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-lg font-bold">{{ $preset->name }}</span>
                        </span>
                    </div>

                    <div class="flex-grow">
                        <p class="text-sm text-[var(--erp-text-secondary)] line-clamp-3 mb-4">
                            {{ $preset->definition['description'] ?? 'Modul sistem bisnis terintegrasi.' }}
                        </p>
                    </div>

                    <a
                        href="/onboarding?preset={{ $preset->key }}"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[var(--erp-accent)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[var(--erp-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] focus-visible:ring-offset-2"
                    >
                        Gunakan Preset Ini
                    </a>
                </div>
            @endforeach
        </nav>

        <div class="mt-10 text-center">
            <a
                href="/"
                wire:navigate
                class="mx-auto block w-fit text-sm font-semibold text-[var(--erp-text-secondary)] underline decoration-[var(--erp-border)] underline-offset-4 hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            >
                Kembali ke Beranda
            </a>
        </div>
    </div>
</div>