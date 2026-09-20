<div class="space-y-8" aria-busy="false" wire:loading.attr="aria-busy">
    @if ($loadError !== null)
        <section role="alert" aria-labelledby="dashboard-error-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-danger)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)] sm:p-8">
            <div class="flex items-start gap-4">
                <span aria-hidden="true" class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--erp-danger-soft)] text-[var(--erp-danger)]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h1 id="dashboard-error-title" class="text-xl font-bold text-[var(--erp-text-primary)]">Dashboard belum dapat dimuat</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--erp-text-secondary)]">{{ $loadError }}</p>
                    <p class="mt-1 text-sm leading-6 text-[var(--erp-text-muted)]">Data lain di company tetap aman; sumber data hanya dibaca ulang saat Anda mencoba lagi.</p>
                    <button
                        type="button"
                        wire:click="reload"
                        wire:loading.attr="disabled"
                        class="mt-5 inline-flex min-h-11 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 py-2 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                    >Coba lagi</button>
                </div>
            </div>
        </section>
    @elseif ($dashboard !== null)
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--erp-accent)]">{{ $dashboard['company'] }}</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[var(--erp-text-primary)] sm:text-3xl">Ringkasan hari ini</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--erp-text-secondary)] sm:text-base">
                Prioritas operasional, posisi kas, dan tindakan yang membutuhkan perhatian.
            </p>
        </div>
        <button
            type="button"
            wire:click="reload"
            wire:loading.attr="disabled"
            class="inline-flex min-h-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-secondary)] px-4 py-2 text-sm font-semibold text-[var(--erp-text-primary)] transition hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
        >
            <span wire:loading.remove>Perbarui data</span>
            <span wire:loading>Memperbarui…</span>
        </button>
    </header>

    <section aria-labelledby="dashboard-kpi-title">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 id="dashboard-kpi-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Kinerja utama</h2>
            <span class="text-sm text-[var(--erp-text-muted)]">Data company aktif</span>
        </div>
        {{-- Skeleton menutup grid saat request Livewire berjalan; aria-busy di root. --}}
        <div wire:loading.class="hidden" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($dashboard['kpis'] as $kpi)
                <article class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5 shadow-[var(--erp-card-shadow)]">
                    <div class="flex items-center gap-2">
                        <span aria-hidden="true" @class([
                            'h-2.5 w-2.5 shrink-0 rounded-full',
                            'bg-[var(--erp-success)]' => $kpi['tone'] === 'success',
                            'bg-[var(--erp-warning)]' => $kpi['tone'] === 'warning',
                            'bg-[var(--erp-danger)]' => $kpi['tone'] === 'danger',
                            'bg-[var(--erp-info)]' => $kpi['tone'] === 'info',
                            'bg-[var(--erp-accent)]' => $kpi['tone'] === 'accent',
                        ])></span>
                        <p class="text-sm font-medium text-[var(--erp-text-secondary)]">{{ $kpi['label'] }}</p>
                    </div>
                    <p @class([
                        'mt-3 text-2xl font-bold tabular-nums',
                        'text-[var(--erp-success)]' => $kpi['tone'] === 'success',
                        'text-[var(--erp-danger)]' => $kpi['tone'] === 'danger',
                        'text-[var(--erp-text-primary)]' => true,
                    ])>{{ $kpi['value'] }}</p>
                    <p class="mt-2 text-sm text-[var(--erp-text-muted)]">{{ $kpi['meta'] }}</p>
                </article>
            @endforeach
        </div>
        {{-- Skeleton placeholder: bentuk kartu sama dengan grid asli. --}}
        <div wire:loading.class="grid" wire:loading aria-hidden="true" wire:target="reload" class="hidden grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([1, 2, 3] as $n)
                <article class="animate-pulse rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5 shadow-[var(--erp-card-shadow)]">
                    <div class="h-3 w-24 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)]"></div>
                    <div class="mt-4 h-7 w-28 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)]"></div>
                    <div class="mt-3 h-3 w-20 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)]"></div>
                </article>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="assistant-report-title" class="overflow-hidden rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] shadow-[var(--erp-card-shadow)]">
        <div class="border-b border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[var(--erp-accent)]">{{ $dashboard['assistant_report']['period'] }}</p>
                    <h2 id="assistant-report-title" class="mt-1 text-lg font-semibold text-[var(--erp-text-primary)]">Laporan AI</h2>
                </div>
                @if ($dashboard['assistant_report']['generated_at'] !== '')
                    <time datetime="{{ $dashboard['assistant_report']['generated_at'] }}" class="text-sm text-[var(--erp-text-muted)]">
                        Diperbarui {{ \Illuminate\Support\Carbon::parse($dashboard['assistant_report']['generated_at'])->translatedFormat('d M Y, H:i') }}
                    </time>
                @endif
            </div>
        </div>
        <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[1.35fr_1fr]">
            <div>
                <p class="text-base leading-7 text-[var(--erp-text-primary)]">{{ $dashboard['assistant_report']['summary'] }}</p>
                @if ($dashboard['assistant_report']['highlights'] !== [])
                    <ul class="mt-5 space-y-3" aria-label="Sorotan laporan">
                        @foreach ($dashboard['assistant_report']['highlights'] as $highlight)
                            <li class="flex gap-3 text-sm leading-6 text-[var(--erp-text-secondary)]">
                                <span aria-hidden="true" class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--erp-info)]"></span>
                                <span>{{ $highlight }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="rounded-[var(--erp-radius-md)] bg-[var(--erp-bg-inset)] p-4">
                <h3 class="text-sm font-semibold text-[var(--erp-text-primary)]">Tindakan disarankan</h3>
                <ul class="mt-3 space-y-3">
                    @forelse ($dashboard['assistant_report']['recommended_actions'] as $action)
                        <li class="flex items-start gap-3 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-secondary)] p-3 shadow-sm ring-1 ring-[var(--erp-border)] transition hover:bg-[var(--erp-bg-elevated)]">
                            <span aria-hidden="true" class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[var(--erp-accent-soft)] text-[var(--erp-accent)]">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium leading-6 text-[var(--erp-text-secondary)]">{{ $action }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-[var(--erp-text-muted)]">Belum ada tindakan yang disarankan.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>

    <section aria-labelledby="dashboard-widget-title">
        <div class="mb-4">
            <h2 id="dashboard-widget-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Fokus operasional</h2>
            <p class="mt-1 text-sm text-[var(--erp-text-muted)]">Panel yang tampil mengikuti kapabilitas company.</p>
        </div>
        @if ($dashboard['widgets'] !== [])
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($dashboard['widgets'] as $widget)
                    <livewire:widgets.dashboard-widget :widget="$widget" :key="$widget['key']" />
                @endforeach
            </div>
        @else
            <div class="rounded-[var(--erp-radius-lg)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-secondary)] px-6 py-10 text-center">
                <h3 class="font-semibold text-[var(--erp-text-primary)]">Belum ada panel operasional</h3>
                <p class="mt-2 text-sm text-[var(--erp-text-muted)]">Aktifkan kapabilitas yang relevan melalui pengaturan company.</p>
            </div>
        @endif
    </section>
    @endif

    {{-- Overlay hanya untuk request non-reload (mis. mount pertama); reload menampilkan skeleton KPI. --}}
    <div wire:loading.flex wire:target="reload" wire:loading.remove wire:target.exclude class="fixed inset-0 z-50 items-center justify-center bg-[color-mix(in_srgb,var(--erp-bg-inset)_72%,transparent)]" role="status" aria-live="polite">
        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-5 py-4 text-sm font-medium text-[var(--erp-text-primary)] shadow-[var(--erp-card-shadow)]">
            Memuat dashboard…
        </div>
    </div>
</div>
