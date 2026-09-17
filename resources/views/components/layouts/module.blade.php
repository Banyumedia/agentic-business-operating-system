<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme ?? 'a' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Agentic BOS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[var(--erp-bg-base)] font-sans text-[var(--erp-text-primary)] antialiased">
    <div wire:loading class="fixed left-0 right-0 top-0 z-[100] h-1 animate-pulse bg-[var(--erp-accent)]"></div>

    <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-2 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Lewati ke konten utama
    </a>

    <div
        x-data="{
            sidebarOpen: false,
            desktop: window.matchMedia('(min-width: 1024px)').matches,
            init() {
                const media = window.matchMedia('(min-width: 1024px)');
                media.addEventListener('change', event => {
                    this.desktop = event.matches;
                    if (event.matches) this.sidebarOpen = false;
                });
            },
            openSidebar() {
                this.sidebarOpen = true;
                this.$nextTick(() => this.$refs.sidebarClose?.focus());
            },
            closeSidebar() {
                this.sidebarOpen = false;
                this.$nextTick(() => this.$refs.sidebarTrigger?.focus());
            }
        }"
        x-on:keydown.escape.window="if (sidebarOpen) closeSidebar()"
    >
        <button
            x-ref="sidebarTrigger"
            type="button"
            x-bind:inert="!desktop && sidebarOpen"
            class="fixed left-4 top-4 z-30 inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] text-[var(--erp-text-primary)] shadow-[var(--erp-card-shadow)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] lg:hidden"
            x-on:click="openSidebar()"
            aria-controls="module-sidebar"
            x-bind:aria-expanded="sidebarOpen.toString()"
            aria-label="Buka navigasi modul"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <button
            type="button"
            x-bind:inert="!desktop && sidebarOpen"
            x-on:click="$dispatch('open-command-palette')"
            class="fixed right-4 top-4 z-30 inline-flex min-h-11 items-center gap-2 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-3 text-sm font-medium text-[var(--erp-text-primary)] shadow-[var(--erp-card-shadow)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
            aria-label="Buka pencarian universal"
            aria-keyshortcuts="Control+K Meta+K"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path stroke-linecap="round" d="m20 20-4-4" />
            </svg>
            <span class="hidden sm:inline">Cari</span>
        </button>

        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            x-on:click="closeSidebar()"
            class="fixed inset-0 z-30 bg-[color-mix(in_srgb,var(--erp-text-primary)_45%,transparent)] lg:hidden"
            aria-hidden="true"
        ></div>

        <livewire:sidebar />

        <main
            id="main-content"
            x-bind:inert="!desktop && sidebarOpen"
            class="min-h-screen px-4 pb-8 pt-20 sm:px-8 lg:ml-72 lg:px-10 lg:py-8"
        >
            <div class="mx-auto max-w-7xl">
                {{ $slot }}
            </div>
        </main>
    </div>

    <livewire:command-palette />
    @livewireScripts
</body>
</html>
