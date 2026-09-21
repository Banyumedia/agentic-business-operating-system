<div
    x-data="{ activeTab: @js($activeTab), focusedTab: {{ array_search($activeTab, array_column($tabs, 'id'), true) }} }"
    x-on:theme-changed.window="document.documentElement.dataset.theme = $event.detail.theme"
>
    <header class="mb-8">
        <p class="text-sm font-semibold text-[var(--erp-accent)]">Pengaturan usaha</p>
        <h1 class="mt-1 text-3xl font-bold text-[var(--erp-text-primary)]">Atur Agentic BOS sesuai cara kerja tim</h1>
        <p class="mt-2 text-[var(--erp-text-secondary)]">Semua perubahan di halaman ini berlaku untuk seluruh pengguna di usaha Anda, bukan hanya akun Anda sendiri.</p>
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
            @if ($tab['id'] === 'profile')
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[var(--erp-text-primary)]">Profil usaha & pajak</h2>
                    <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">Ringkasan identitas usaha yang tersimpan.</p>
                </div>

                @if ($businessSummary)
                    <dl class="grid gap-4 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-[var(--erp-text-secondary)]">Nama usaha</dt>
                            <dd class="mt-1 font-semibold text-[var(--erp-text-primary)]">{{ $businessSummary['name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-[var(--erp-text-secondary)]">Preset aktif</dt>
                            <dd class="mt-1 font-semibold text-[var(--erp-text-primary)]">{{ $businessSummary['preset'] }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-[var(--erp-text-secondary)]">Status PPN</dt>
                            @if ($businessSummary['taxable'])
                                <dd class="mt-1 font-semibold text-[var(--erp-text-primary)]">PKP (memungut PPN)</dd>
                            @else
                                <dd class="mt-1 font-semibold text-[var(--erp-text-primary)]">Non-PKP (tidak memungut PPN)</dd>
                            @endif
                        </div>
                    </dl>
                @else
                    <p class="rounded-[var(--erp-radius-sm)] bg-[var(--erp-info-soft)] p-3 text-sm text-[var(--erp-text-primary)]">
                        Identitas usaha belum lengkap. Lengkapi lewat onboarding.
                    </p>
                @endif
            @elseif ($tab['id'] === 'features')
                <div class="space-y-10">
                    <div>
                        <div class="mb-4">
                            <h2 class="text-xl font-semibold text-[var(--erp-text-primary)]">Fitur bisnis</h2>
                            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">Pilih preset yang paling sesuai dengan cara kerja usaha ini.</p>
                        </div>

                        @if ($featuresNotice)
                            <p class="mb-4 text-sm text-[var(--erp-success)]" role="status" aria-live="polite">{{ $featuresNotice }}</p>
                        @endif
                        @if ($featuresFailure)
                            <p class="mb-4 text-sm text-[var(--erp-danger)]" role="alert">{{ $featuresFailure }}</p>
                        @endif

                        <label for="preset-select" class="block text-sm font-medium text-[var(--erp-text-secondary)]">Preset bisnis</label>
                        <select
                            id="preset-select"
                            wire:change="updatePreset($event.target.value)"
                            wire:loading.attr="disabled"
                            @disabled(! $canManageTheme)
                            class="mt-2 min-h-11 w-full max-w-md rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-3 text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                        >
                            @foreach ($presets as $presetOption)
                                <option value="{{ $presetOption['key'] }}" @selected($presetOption['key'] === $selectedPreset)>{{ $presetOption['name'] }}</option>
                            @endforeach
                        </select>

                        @unless ($canManageTheme)
                            <p class="mt-3 rounded-[var(--erp-radius-sm)] bg-[var(--erp-info-soft)] p-3 text-sm text-[var(--erp-text-primary)]">
                                Hanya owner usaha yang dapat mengganti preset bisnis.
                            </p>
                        @endunless
                    </div>

                    <div>
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-[var(--erp-text-primary)]">Istilah</h3>
                            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">Sesuaikan label yang tampil di seluruh layar.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($terminologyPairs as $singular => $plural)
                                <div>
                                    <label for="term-{{ $singular }}" class="block text-sm font-medium text-[var(--erp-text-secondary)]">{{ ucfirst($singular) }} / {{ ucfirst($plural) }}</label>
                                    <div class="mt-2 flex gap-2">
                                        <input
                                            type="text"
                                            id="term-{{ $singular }}"
                                            wire:model="terminologyForm.{{ $singular }}"
                                            value="{{ $terminologyForm[$singular] ?? '' }}"
                                            @disabled(! $canManageTheme)
                                            class="min-h-11 flex-1 rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-3 text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                                        >
                                        @if ($canManageTheme)
                                            <button
                                                type="button"
                                                wire:click="updateTerminology('{{ $singular }}')"
                                                wire:loading.attr="disabled"
                                                class="min-h-11 rounded-[var(--erp-radius-sm)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-3 text-sm font-medium text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                                            >
                                                <span wire:loading.remove wire:target="updateTerminology">Simpan</span>
                                                <span wire:loading wire:target="updateTerminology" class="sr-only">Menyimpan…</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-[var(--erp-text-primary)]">Alur</h3>
                            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">Tahapan dan perpindahan yang berlaku untuk preset aktif. Bagian ini bersifat tampilan saja.</p>
                        </div>

                        @forelse ($workflows as $entity => $workflow)
                            <div class="mb-6 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6">
                                <h4 class="font-semibold text-[var(--erp-text-primary)]">{{ $entity }}</h4>
                                <ul class="mt-3 space-y-2 text-sm text-[var(--erp-text-secondary)]">
                                    @foreach ($workflow['stages'] as $stage)
                                        <li>
                                            <span class="font-medium text-[var(--erp-text-primary)]">{{ $stage['label'] }}</span>
                                            @if (in_array($stage['code'], $workflow['terminal'], true))
                                                <span class="ml-2 rounded-full bg-[var(--erp-accent-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--erp-accent)]">Tahap akhir</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <ul class="mt-4 space-y-2 text-sm text-[var(--erp-text-secondary)]">
                                    @foreach ($workflow['transitions'] as $transition)
                                        <li>
                                            <span class="font-medium text-[var(--erp-text-primary)]">{{ $transition['from'] }} → {{ $transition['to'] }}</span>
                                            <span class="ml-2 text-[var(--erp-text-muted)]">({{ implode(', ', $transition['roles']) }})</span>
                                            @if ($transition['requires_note'] ?? false)
                                                <span class="ml-2 rounded-full bg-[var(--erp-warning-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--erp-warning)]">Wajib catatan</span>
                                            @endif
                                            @if ($transition['requires_approval'] ?? false)
                                                <span class="ml-2 rounded-full bg-[var(--erp-info-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--erp-info)]">Wajib persetujuan</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--erp-text-secondary)]">Preset ini belum mendeklarasikan alur.</p>
                        @endforelse
                    </div>
                </div>
            @elseif ($tab['id'] === 'theme')
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[var(--erp-text-primary)]">Skema warna usaha</h2>
                    <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
                        Pilih satu tema. Perubahan disimpan di server dan digunakan seluruh pengguna usaha.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($themes as $key => $themeOption)
                        <button
                            type="button"
                            wire:key="theme-{{ $key }}"
                            wire:click="selectTheme('{{ $key }}')"
                            wire:loading.attr="disabled"
                            aria-pressed="{{ $selectedTheme === $key ? 'true' : 'false' }}"
                            @disabled(! $canManageTheme)
                            class="min-h-44 rounded-[var(--erp-radius-md)] border p-5 text-left shadow-[var(--erp-card-shadow)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60 {{ $selectedTheme === $key ? 'border-[var(--erp-focus)] bg-[var(--erp-bg-active)]' : 'border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] hover:bg-[var(--erp-bg-hover)]' }}"
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

                {{-- Keadaan simpan eksplisit (QA-UI-R C.16): sukses hanya karena
                    server mengonfirmasi, bukan karena wire:loading berakhir. --}}
                @if ($themeNotice)
                    <p class="mt-5 text-sm text-[var(--erp-success)]" role="status" aria-live="polite">{{ $themeNotice }}</p>
                @endif
                @if ($themeFailure)
                    <p class="mt-5 text-sm text-[var(--erp-danger)]" role="alert">{{ $themeFailure }}</p>
                @endif
            @elseif ($tab['id'] === 'assistant')
                @livewire(\App\Livewire\Settings\AssistantSettings::class)
            @elseif ($tab['id'] === 'usage')
                @livewire(\App\Livewire\Settings\UsageAndPlan::class)
            @elseif ($tab['id'] === 'export')
                @livewire(\App\Livewire\Settings\DataExport::class)
            @elseif ($tab['id'] === 'erasure')
                @livewire(\App\Livewire\Settings\DataErasure::class)
            @else
                @php
                    // Teks bantu per-tab untuk pemilik usaha awam, bahasa
                    // sederhana dan tanpa istilah teknis. Tab tanpa entri
                    // memakai kalimat umum di bawah.
                    $stubHelp = [
                        'team' => 'Di sini nanti Anda mengundang staf ke usaha ini dan mengatur bagian mana yang boleh mereka lihat.',
                    ];
                @endphp
                <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--erp-text-primary)]">
                        {{ $tab['label'] }}
                        <span class="ml-2 inline-flex items-center rounded-full bg-[var(--erp-warning-soft)] px-2.5 py-0.5 text-xs font-semibold text-[var(--erp-warning)]">Segera</span>
                    </h2>
                    <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">{{ $stubHelp[$tab['id']] ?? 'Pengaturan bagian ini menyusul bersama fiturnya.' }}</p>
                </div>
            @endif
        </section>
    @endforeach
</div>
