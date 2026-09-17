<div class="space-y-8" aria-busy="false" wire:loading.attr="aria-busy">
    @if ($loadError !== null)
        <section role="alert" aria-labelledby="dashboard-error-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] p-6">
            <h1 id="dashboard-error-title" class="text-xl font-bold text-[var(--erp-text-primary)]">Dashboard belum dapat dimuat</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--erp-text-secondary)]">{{ $loadError }}</p>
            <button
                type="button"
                wire:click="reload"
                wire:loading.attr="disabled"
                class="mt-5 inline-flex min-h-11 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 py-2 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
            >Coba lagi</button>
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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($dashboard['kpis'] as $kpi)
                <article class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5 shadow-[var(--erp-card-shadow)]">
                    <p class="text-sm font-medium text-[var(--erp-text-secondary)]">{{ $kpi['label'] }}</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-[var(--erp-text-primary)]">{{ $kpi['value'] }}</p>
                    <p class="mt-2 text-sm text-[var(--erp-text-muted)]">{{ $kpi['meta'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="assistant-report-title" class="overflow-hidden rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] shadow-[var(--erp-card-shadow)]">
        <div class="border-b border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-5 py-4 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[var(--erp-accent)]">{{ $dashboard['assistant_report']['period'] }}</p>
                    <h2 id="assistant-report-title" class="mt-1 text-lg font-semibold text-[var(--erp-text-primary)]">Laporan Asisten AI</h2>
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
                @forelse ($dashboard['assistant_report']['recommended_actions'] as $action)
                    <p class="mt-3 border-l-2 border-[var(--erp-accent)] pl-3 text-sm leading-6 text-[var(--erp-text-secondary)]">{{ $action }}</p>
                @empty
                    <p class="mt-3 text-sm text-[var(--erp-text-muted)]">Belum ada tindakan yang disarankan.</p>
                @endforelse
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

    <div wire:loading.flex class="fixed inset-0 z-50 items-center justify-center bg-[color-mix(in_srgb,var(--erp-bg-inset)_72%,transparent)]" role="status" aria-live="polite">
        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-5 py-4 text-sm font-medium text-[var(--erp-text-primary)] shadow-[var(--erp-card-shadow)]">
            Memuat dashboard…
        </div>
    </div>
</div>
