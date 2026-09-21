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

        {{-- Bottom Navigation Bar untuk Mobile (<768px) --}}
        @unless(request()->is('app/pos*'))
            <nav
                aria-label="Navigasi cepat ponsel"
                class="fixed bottom-0 left-0 right-0 z-40 flex h-16 items-center justify-around border-t border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-2 shadow-lg md:hidden"
            >
                <a
                    href="{{ route('app.dashboard') }}"
                    class="flex flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none {{ request()->routeIs('app.dashboard') ? 'font-semibold text-[var(--erp-accent)]' : '' }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                    <span class="mt-1 text-[11px]">Beranda</span>
                </a>

                <a
                    href="{{ url('/app/pos') }}"
                    class="flex flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none {{ request()->is('app/pos*') ? 'font-semibold text-[var(--erp-accent)]' : '' }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="mt-1 text-[11px]">Kasir</span>
                </a>

                <a
                    href="{{ url('/app/accounting') }}"
                    class="flex flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none {{ request()->is('app/accounting*') ? 'font-semibold text-[var(--erp-accent)]' : '' }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="mt-1 text-[11px]">Buku Kas</span>
                </a>

                <button
                    type="button"
                    x-on:click="openSidebar()"
                    class="flex flex-1 flex-col items-center justify-center py-1 text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] focus:outline-none"
                    aria-label="Buka menu navigasi lengkap"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span class="mt-1 text-[11px]">Menu</span>
                </button>
            </nav>
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
