<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">{{ $term }}</p>
            <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $title }}</h1>
            @if ($stageLabel !== null)
                <span class="mt-2 inline-flex rounded-full bg-[var(--erp-bg-inset)] px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-secondary)]">
                    {{ $stageLabel }}
                </span>
            @endif
        </div>

        @if ($transitions !== [])
            <div class="relative" x-data="{ open: false }">
                <button
                    type="button"
                    x-on:click="open = !open"
                    x-on:click.outside="open = false"
                    class="inline-flex min-h-11 items-center gap-2 rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    aria-haspopup="true"
                    x-bind:aria-expanded="open.toString()"
                >
                    Pindahkan tahap
                </button>
                <div
                    x-cloak
                    x-show="open"
                    x-transition
                    class="absolute right-0 z-20 mt-2 w-56 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-1 shadow-[var(--erp-card-shadow)]"
                    role="menu"
                >
                    @foreach ($transitions as $transition)
                        <button
                            type="button"
                            role="menuitem"
                            wire:click="move('{{ $transition['to'] }}')"
                            wire:loading.attr="disabled"
                            x-on:click="open = false"
                            class="block w-full min-h-11 rounded-[var(--erp-radius-sm)] px-3 text-left text-sm text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                        >
                            {{ $transition['label'] }}
                            @if ($transition['requires_note'])
                                <span class="block text-xs text-[var(--erp-text-muted)]">Wajib disertai alasan</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
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

    {{-- D-46: perpindahan mundur wajib alasan singkat sebelum dijalankan. --}}
    @if ($pendingStageLabel !== null)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[color-mix(in_srgb,var(--erp-text-primary)_60%,transparent)] p-4 sm:items-center">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="move-dialog-title"
                x-data="{ opener: document.activeElement }"
                x-trap.inert.noscroll="true"
                x-init="$nextTick(() => $refs.note?.focus())"
                x-on:keydown.escape.window="const target = opener; $wire.cancelMove().then(() => target?.focus())"
                class="w-full max-w-md rounded-[var(--erp-radius-lg)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)] focus:outline-none"
                tabindex="-1"
            >
                <h2 id="move-dialog-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">
                    Pindah ke {{ $pendingStageLabel }}
                </h2>

                <label for="move-note" class="mt-4 block text-sm font-medium text-[var(--erp-text-primary)]">
                    Alasan <span class="text-[var(--erp-danger)]" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                </label>
                <textarea id="move-note" x-ref="note" wire:model="note" rows="3"
                    class="mt-1 w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-inset)] px-3 py-2 text-sm text-[var(--erp-text-primary)] focus:border-[var(--erp-border-focus)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"></textarea>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button"
                        x-on:click="const target = opener; $wire.cancelMove().then(() => target?.focus())"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border-strong)] px-4 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        Batal
                    </button>
                    <button type="button" wire:click="confirmMove" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 text-sm font-semibold text-[var(--erp-text-inverse)] hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove>Pindahkan</span>
                        <span wire:loading>Memindahkan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <section aria-labelledby="detail-fields-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <h2 id="detail-fields-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Ringkasan</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($fields as $field)
                @php($value = $row[$field['field']] ?? null)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">{{ $field['label'] }}</dt>
                    <dd class="mt-1 text-sm text-[var(--erp-text-primary)] {{ $field['numeric'] ? 'font-[family-name:var(--erp-font-mono)] tabular-nums' : '' }}">
                        @if (is_bool($value))
                            {{ $value ? 'Ya' : 'Tidak' }}
                        @elseif ($value === null || $value === '')
                            <span class="text-[var(--erp-text-muted)]">—</span>
                        @else
                            {{ $value }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    @foreach ($childRelations as $child)
        <section aria-labelledby="detail-child-{{ $child['entity'] }}-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
            <h2 id="detail-child-{{ $child['entity'] }}-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">{{ $child['term'] }}</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ $child['term'] }}</caption>
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] text-left text-xs uppercase tracking-wide text-[var(--erp-text-muted)]">
                            @foreach ($child['columns'] as $column)
                                <th scope="col" class="py-2 pr-3 {{ $column['numeric'] ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($child['rows'] as $childRow)
                            <tr class="border-b border-[var(--erp-border)] last:border-0">
                                @foreach ($child['columns'] as $column)
                                    @php($childValue = $childRow[$column['field']] ?? null)
                                    <td class="py-2 pr-3 {{ $column['numeric'] ? 'text-right font-[family-name:var(--erp-font-mono)] tabular-nums' : '' }}">
                                        @if (is_bool($childValue))
                                            {{ $childValue ? 'Ya' : 'Tidak' }}
                                        @elseif ($childValue === null || $childValue === '')
                                            <span class="text-[var(--erp-text-muted)]">—</span>
                                        @else
                                            {{ $childValue }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @if ($timeline !== [])
        <section aria-labelledby="detail-timeline-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
            <h2 id="detail-timeline-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Linimasa</h2>
            <ol role="list" class="mt-4 space-y-3 border-l border-[var(--erp-border)] pl-4">
                @foreach ($timeline as $entry)
                    <li>
                        <p class="text-sm font-medium text-[var(--erp-text-primary)]">
                            {{ $entry['from'] ?? '—' }} → {{ $entry['to'] ?? '—' }}
                        </p>
                        @if (! empty($entry['note']))
                            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">{{ $entry['note'] }}</p>
                        @endif
                        @if (! empty($entry['occurred_at']))
                            <p class="mt-1 text-xs text-[var(--erp-text-muted)]">{{ $entry['occurred_at'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</div>
