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
                        wire:loading.attr="disabled"
                        aria-pressed="{{ $view === $mode ? 'true' : 'false' }}"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60 {{ $view === $mode ? 'bg-[var(--erp-accent)] text-[var(--erp-text-inverse)]' : 'text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)]' }}"
                    >
                        {{ $modeLabel }}
                    </button>
                @endforeach
            </div>

            <nav aria-label="Navigasi periode" class="flex items-center gap-1">
                <button
                    type="button"
                    wire:click="shift(-1)"
                    wire:loading.attr="disabled"
                    aria-label="Periode sebelumnya"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                >
                    &larr;
                </button>
                <button
                    type="button"
                    wire:click="today"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-3 text-sm font-medium text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                >
                    Hari ini
                </button>
                <button
                    type="button"
                    wire:click="shift(1)"
                    wire:loading.attr="disabled"
                    aria-label="Periode berikutnya"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                >
                    &rarr;
                </button>
            </nav>
        </div>
    </header>

    @if ($notice !== null)
        <p role="status" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-success)] bg-[var(--erp-success-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $notice }}
        </p>
    @endif

    @if ($failure !== null)
        <p role="alert" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $failure }}
        </p>
    @endif

    <ol role="list" class="grid gap-3 transition-opacity duration-200 {{ count($days) > 1 ? 'lg:grid-cols-7' : '' }}" wire:loading.class="opacity-50 pointer-events-none" wire:target="setView, shift, today">
        @foreach ($days as $day)
            <li class="flex flex-col rounded-[var(--erp-radius-lg)] border bg-[var(--erp-bg-secondary)] {{ $day['is_today'] ? 'border-[var(--erp-accent)]' : 'border-[var(--erp-border)]' }}">
                <h2 class="flex items-baseline justify-between gap-2 border-b border-[var(--erp-border)] px-3 py-2">
                    <span class="text-sm font-semibold text-[var(--erp-text-primary)]">{{ $day['label'] }}</span>
                    <span class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-muted)]">{{ count($day['slots']) }}</span>
                </h2>

                @if ($isBookable)
                    <button type="button" wire:click="requestCreate('{{ $day['date'] }}')"
                        class="mx-2 mt-2 inline-flex min-h-9 items-center justify-center rounded-[var(--erp-radius-sm)] border border-dashed border-[var(--erp-border-strong)] text-xs font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        + Tambah janji
                    </button>
                @endif

                <div class="flex flex-1 flex-col gap-2 p-2">
                    @forelse ($day['slots'] as $slot)
                        <article class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] p-2">
                            <p class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-secondary)]">
                                {{ $slot['from'] }}@if ($slot['to'] !== null)&ndash;{{ $slot['to'] }}@endif
                            </p>
                            <p class="mt-1 text-sm font-medium text-[var(--erp-text-primary)]">{{ $slot['title'] }}</p>

                            @if ($slot['resource'] !== null)
                                <p class="mt-0.5 text-xs text-[var(--erp-text-muted)]">{{ $slot['resource'] }}</p>
                            @endif
                        </article>
                    @empty
                        <div role="status" class="flex flex-col items-center justify-center rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] px-2 py-6 text-center">
                            <div class="mb-2 rounded-full bg-[var(--erp-bg-secondary)] p-2 text-[var(--erp-text-muted)] shadow-[var(--erp-card-shadow)] border border-[var(--erp-border)]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-[var(--erp-text-primary)]">Tidak ada data</p>
                            <p class="mt-1 max-w-sm text-xs text-[var(--erp-text-secondary)]">
                                Belum ada {{ $term }}.
                            </p>
                        </div>
                    @endforelse
                </div>
            </li>
        @endforeach
    </ol>

    @if ($creating)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[color-mix(in_srgb,var(--erp-text-primary)_60%,transparent)] p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="calendar-create-title"
                x-data="{ opener: document.activeElement }"
                x-trap.inert.noscroll="true"
                x-init="$nextTick(() => $refs.resource?.focus())"
                x-on:keydown.escape.window="const target = opener; $wire.cancelCreate().then(() => target?.focus())"
                class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)] focus:outline-none"
                tabindex="-1"
            >
                <h2 id="calendar-create-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Tambah janji</h2>

                <form wire:submit="create" class="mt-4 space-y-4">
                    <div class="space-y-1.5">
                        <label for="calendar-resource" class="block text-sm font-medium text-[var(--erp-text-primary)]">Sumber daya</label>
                        <select id="calendar-resource" x-ref="resource" wire:model="form.resource_id" required
                            class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            <option value="">Pilih sumber daya</option>
                            @foreach ($resourceOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="calendar-starts-at" class="block text-sm font-medium text-[var(--erp-text-primary)]">Mulai</label>
                            <input id="calendar-starts-at" type="datetime-local" wire:model="form.starts_at" required
                                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="calendar-ends-at" class="block text-sm font-medium text-[var(--erp-text-primary)]">Selesai</label>
                            <input id="calendar-ends-at" type="datetime-local" wire:model="form.ends_at" required
                                class="min-h-11 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]" />
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-[var(--erp-border)] pt-4">
                        <button type="button" x-on:click="const target = opener; $wire.cancelCreate().then(() => target?.focus())"
                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Menyimpan…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
