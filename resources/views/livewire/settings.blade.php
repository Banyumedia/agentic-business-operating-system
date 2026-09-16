<div
    x-data="{ activeTab: @js($activeTab), focusedTab: {{ array_search($activeTab, array_column($tabs, 'id'), true) }} }"
    x-on:theme-changed.window="document.documentElement.dataset.theme = $event.detail.theme"
>
    <header class="mb-8">
        <p class="text-sm font-semibold text-[var(--erp-accent)]">Pengaturan usaha</p>
        <h1 class="mt-1 text-3xl font-bold text-[var(--erp-text-primary)]">Atur Agentic BOS sesuai cara kerja tim</h1>
        <p class="mt-2 text-[var(--erp-text-secondary)]">Tema berlaku untuk seluruh staf di usaha ini.</p>
    </header>

    <div
        class="no-scrollbar flex snap-x gap-2 overflow-x-auto border-b border-[var(--erp-border)] scroll-smooth"
        role="tablist"
        aria-label="Bagian pengaturan"
        aria-orientation="horizontal"
        x-on:keydown.right.prevent="focusedTab = (focusedTab + 1) % {{ count($tabs) }}; $refs['tab-' + focusedTab].focus()"
        x-on:keydown.left.prevent="focusedTab = (focusedTab - 1 + {{ count($tabs) }}) % {{ count($tabs) }}; $refs['tab-' + focusedTab].focus()"
        x-on:keydown.home.prevent="focusedTab = 0; $refs['tab-0'].focus()"
        x-on:keydown.end.prevent="focusedTab = {{ count($tabs) - 1 }}; $refs['tab-' + focusedTab].focus()"
    >
        @foreach ($tabs as $index => $tab)
            <button
                type="button"
                id="tab-{{ $tab['id'] }}"
                role="tab"
                aria-controls="panel-{{ $tab['id'] }}"
                aria-selected="{{ $tab['id'] === $activeTab ? 'true' : 'false' }}"
                tabindex="{{ $tab['id'] === $activeTab ? '0' : '-1' }}"
                x-ref="tab-{{ $index }}"
                x-on:focus="focusedTab = {{ $index }}"
                x-on:click="activeTab = '{{ $tab['id'] }}'"
                x-on:keydown.enter.prevent="activeTab = '{{ $tab['id'] }}'"
                x-on:keydown.space.prevent="activeTab = '{{ $tab['id'] }}'"
                x-bind:aria-selected="activeTab === '{{ $tab['id'] }}' ? 'true' : 'false'"
                x-bind:tabindex="activeTab === '{{ $tab['id'] }}' ? 0 : -1"
                class="min-h-11 shrink-0 snap-start px-4 py-3 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                x-bind:class="activeTab === '{{ $tab['id'] }}'
                    ? 'border-b-2 border-[var(--erp-focus)] font-semibold text-[var(--erp-text-primary)]'
                    : 'text-[var(--erp-text-muted)] hover:text-[var(--erp-text-primary)]'"
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    @foreach ($tabs as $tab)
        <section
            id="panel-{{ $tab['id'] }}"
            role="tabpanel"
            aria-labelledby="tab-{{ $tab['id'] }}"
            tabindex="0"
            x-show="activeTab === '{{ $tab['id'] }}'"
            @if ($tab['id'] !== $activeTab) x-cloak @endif
            class="py-8 focus:outline-none"
        >
            @if ($tab['id'] === 'theme')
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[var(--erp-text-primary)]">Skema warna usaha</h2>
                    <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
                        Pilih satu tema. Perubahan disimpan di server dan digunakan seluruh staf usaha.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($themes as $key => $themeOption)
                        <button
                            type="button"
                            wire:key="theme-{{ $key }}"
                            wire:click="selectTheme('{{ $key }}')"
                            x-on:click="document.documentElement.dataset.theme = '{{ $key }}'"
                            aria-pressed="{{ $selectedTheme === $key ? 'true' : 'false' }}"
                            @disabled(! $canManageTheme)
                            class="min-h-44 rounded-[var(--erp-radius-md)] border p-5 text-left shadow-[var(--erp-card-shadow)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ $selectedTheme === $key ? 'border-[var(--erp-focus)] bg-[var(--erp-bg-active)]' : 'border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] hover:bg-[var(--erp-bg-hover)]' }}"
                        >
                            <span class="mb-4 flex gap-2" aria-hidden="true">
                                <span class="size-7 rounded-full border border-[var(--erp-border-strong)]" style="background: {{ $themeOption['tokens']['--erp-bg-base'] }}"></span>
                                <span class="size-7 rounded-full" style="background: {{ $themeOption['tokens']['--erp-accent'] }}"></span>
                                <span class="size-7 rounded-full" style="background: {{ $themeOption['tokens']['--erp-text-link'] }}"></span>
                            </span>
                            <span class="block font-semibold text-[var(--erp-text-primary)]">{{ $themeOption['name'] }}</span>
                            <span class="mt-1 block text-sm text-[var(--erp-text-secondary)]">{{ $themeOption['description'] }}</span>
                            @if ($selectedTheme === $key)
                                <span class="mt-4 inline-flex rounded-full bg-[var(--erp-accent)] px-3 py-1 text-xs font-semibold text-[var(--erp-text-inverse)]">Tema aktif</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @unless ($canManageTheme)
                    <p class="mt-5 rounded-[var(--erp-radius-sm)] bg-[var(--erp-info-soft)] p-3 text-sm text-[var(--erp-text-primary)]">
                        Hanya owner usaha yang dapat mengganti tema.
                    </p>
                @endunless

                <p class="mt-5 text-sm text-[var(--erp-success)]" aria-live="polite" wire:loading.remove wire:target="selectTheme">
                    Tema aktif: {{ $themes[$selectedTheme]['name'] }}
                </p>
                <p class="mt-5 text-sm text-[var(--erp-text-secondary)]" aria-live="polite" wire:loading wire:target="selectTheme">
                    Menyimpan tema usaha…
                </p>
            @else
                <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--erp-text-primary)]">{{ $tab['label'] }}</h2>
                    <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">Bagian ini akan dilengkapi pada task fitur terkait.</p>
                </div>
            @endif
        </section>
    @endforeach
</div>
