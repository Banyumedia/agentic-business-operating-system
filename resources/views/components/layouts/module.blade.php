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
    @if(session()->has('admin_impersonation_id'))
        <div class="bg-[var(--erp-warning)] text-[var(--erp-bg-inset)] px-4 py-2 text-center font-bold sticky top-0 z-[110] flex justify-between items-center gap-3">
            <span>⚠️ Anda sedang dalam Sesi Bantuan Impersonasi. Segala perubahan akan dicatat.</span>
            <form action="{{ route('admin.impersonate.stop') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="min-h-11 px-3 rounded-[var(--erp-radius-sm)] text-sm bg-[var(--erp-bg-inset)] text-[var(--erp-warning)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">Akhiri Sesi Bantuan</button>
            </form>
        </div>
    @endif
    <div wire:loading class="fixed left-0 right-0 top-0 z-[100] h-1 animate-pulse bg-[var(--erp-accent)]"></div>

    <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-3 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:min-h-11">
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
            class="fixed left-4 top-4 z-30 inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] text-[var(--erp-text-primary)] shadow-[var(--erp-card-shadow)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ request()->is('app/pos*') ? 'hidden' : 'lg:hidden' }}"
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
            class="fixed right-4 top-4 z-30 inline-flex min-h-11 items-center gap-2 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-3 text-sm font-medium text-[var(--erp-text-primary)] shadow-[var(--erp-card-shadow)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ request()->is('app/pos*') ? 'hidden' : '' }}"
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
            class="min-h-screen px-4 pb-24 pt-20 sm:px-8 md:pb-8 lg:ml-72 lg:px-10 lg:py-8 {{ request()->is('app/pos*') ? '!pt-4 md:!pt-8' : '' }}"
        >
            <div class="mx-auto max-w-7xl">
                {{ $slot }}
            </div>
        </main>

        {{-- Navigasi bawah ponsel. Isinya diturunkan dari DynamicMenuRegistry
             lewat komponen tersendiri: versi pertama menanam tab Kasir dan Buku
             Kas tanpa memeriksa kapabilitas, jadi preset tanpa `pos` atau
             `finance.cashbook` mendapat tab yang berujung 403. --}}
        @unless(request()->is('app/pos*'))
            <livewire:mobile-quick-nav />
        @endunless
    </div>

    {{-- Global Floating Toast Notification System --}}
    <div
        x-data="{
            toasts: [],
            add(message, type = 'success') {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message, type });
                setTimeout(() => this.remove(id), 3500);
            },
            remove(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }
        }"
        x-on:notify.window="add($event.detail.message || $event.detail, $event.detail.type || 'success')"
        class="pointer-events-none fixed right-4 top-4 z-[120] flex w-full max-w-sm flex-col gap-2 sm:right-6 sm:top-6"
        aria-live="polite"
    >
        @if (session()->has('status'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 3500)"
                x-transition
                class="pointer-events-auto flex items-center justify-between gap-3 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-4 text-sm text-[var(--erp-text-primary)] shadow-lg"
                role="status"
            >
                <div class="flex items-center gap-2">
                    <span aria-hidden="true" class="h-2 w-2 rounded-full bg-[var(--erp-success)]"></span>
                    <span>{{ session('status') }}</span>
                </div>
                <button type="button" x-on:click="show = false" class="text-[var(--erp-text-muted)] hover:text-[var(--erp-text-primary)] focus:outline-none">✕</button>
            </div>
        @endif

        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="pointer-events-auto flex items-center justify-between gap-3 rounded-[var(--erp-radius-md)] border bg-[var(--erp-bg-elevated)] p-4 text-sm text-[var(--erp-text-primary)] shadow-lg"
                :class="toast.type === 'error' ? 'border-[var(--erp-danger)]' : 'border-[var(--erp-border)]'"
                role="status"
            >
                <div class="flex items-center gap-2">
                    <span aria-hidden="true" class="h-2 w-2 rounded-full" :class="toast.type === 'error' ? 'bg-[var(--erp-danger)]' : 'bg-[var(--erp-success)]'"></span>
                    <span x-text="toast.message"></span>
                </div>
                <button type="button" x-on:click="remove(toast.id)" class="text-[var(--erp-text-muted)] hover:text-[var(--erp-text-primary)] focus:outline-none">✕</button>
            </div>
        </template>
    </div>

    <livewire:command-palette />
    @livewireScripts
</body>
</html>
