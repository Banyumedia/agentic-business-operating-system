<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
            <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]" aria-live="polite">{{ $rangeLabel }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div role="group" aria-label="Rentang tampilan" class="inline-flex rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] p-0.5">
                @foreach (['day' => 'Harian', 'week' => 'Mingguan'] as $mode => $modeLabel)
                    <button
                        type="button"
                        wire:click="setView('{{ $mode }}')"
                        aria-pressed="{{ $view === $mode ? 'true' : 'false' }}"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ $view === $mode ? 'bg-[var(--erp-accent)] text-[var(--erp-text-inverse)]' : 'text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)]' }}"
                    >
                        {{ $modeLabel }}
                    </button>
                @endforeach
            </div>

            <nav aria-label="Navigasi periode" class="flex items-center gap-1">
                <button
                    type="button"
                    wire:click="shift(-1)"
                    aria-label="Periode sebelumnya"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    &larr;
                </button>
                <button
                    type="button"
                    wire:click="today"
                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-3 text-sm font-medium text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    Hari ini
                </button>
                <button
                    type="button"
                    wire:click="shift(1)"
                    aria-label="Periode berikutnya"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    &rarr;
                </button>
            </nav>
        </div>
    </header>

    <ol role="list" class="grid gap-3 {{ count($days) > 1 ? 'lg:grid-cols-7' : '' }}">
        @foreach ($days as $day)
            <li class="flex flex-col rounded-[var(--erp-radius-lg)] border bg-[var(--erp-bg-secondary)] {{ $day['is_today'] ? 'border-[var(--erp-accent)]' : 'border-[var(--erp-border)]' }}">
                <h2 class="flex items-baseline justify-between gap-2 border-b border-[var(--erp-border)] px-3 py-2">
                    <span class="text-sm font-semibold text-[var(--erp-text-primary)]">{{ $day['label'] }}</span>
                    <span class="font-[family-name:var(--erp-font-mono)] text-xs text-[var(--erp-text-muted)]">{{ count($day['slots']) }}</span>
                </h2>

                <div class="flex flex-1 flex-col gap-2 p-2">
                    @forelse ($day['slots'] as $slot)
                        <article class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] p-2">
                            <p class="font-[family-name:var(--erp-font-mono)] text-xs text-[var(--erp-text-secondary)]">
                                {{ $slot['from'] }}@if ($slot['to'] !== null)&ndash;{{ $slot['to'] }}@endif
                            </p>
                            <p class="mt-1 text-sm font-medium text-[var(--erp-text-primary)]">{{ $slot['title'] }}</p>

                            @if ($slot['resource'] !== null)
                                <p class="mt-0.5 text-xs text-[var(--erp-text-muted)]">{{ $slot['resource'] }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="px-2 py-4 text-center text-xs text-[var(--erp-text-muted)]">Belum ada {{ $term }}.</p>
                    @endforelse
                </div>
            </li>
        @endforeach
    </ol>
</div>
