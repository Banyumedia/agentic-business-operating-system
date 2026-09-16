@props([
    'columns',
    'rows',
    'sort' => null,
    'direction' => 'asc',
    'caption',
    'emptyMessage' => 'Belum ada data.',
    'rowActions' => [],
])

@php
    $present = static function (array $column, array $row): string {
        $value = $row[$column['field']] ?? null;

        if ($column['type'] === 'boolean') {
            return $value ? 'Ya' : 'Tidak';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    };

    $ariaSort = static fn (array $column): string => $sort === $column['field']
        ? ($direction === 'desc' ? 'descending' : 'ascending')
        : 'none';
@endphp

<div>
    @if ($rows === [])
        <p role="status" class="rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] px-4 py-10 text-center text-sm text-[var(--erp-text-secondary)]">
            {{ $emptyMessage }}
        </p>
    @else
        {{-- Tabel padat untuk layar lebar (D-20 Operator Grid) --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <caption class="sr-only">{{ $caption }}</caption>
                <thead>
                    <tr class="border-b border-[var(--erp-border-strong)]">
                        @foreach ($columns as $column)
                            <th
                                scope="col"
                                aria-sort="{{ $ariaSort($column) }}"
                                class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)] {{ $column['numeric'] ? 'text-right' : '' }}"
                            >
                                <button
                                    type="button"
                                    wire:click="sortBy('{{ $column['field'] }}')"
                                    class="inline-flex min-h-11 items-center gap-1 rounded-[var(--erp-radius-sm)] px-1 uppercase hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                                >
                                    {{ $column['label'] }}
                                    @if ($sort === $column['field'])
                                        <span aria-hidden="true">{{ $direction === 'desc' ? '▼' : '▲' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        @if ($rowActions !== [])
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-bg-hover)]">
                            @foreach ($columns as $column)
                                <td class="px-3 py-2 text-[var(--erp-text-primary)] {{ $column['numeric'] ? 'text-right font-[family-name:var(--erp-font-mono)]' : '' }}">
                                    {{ $present($column, $row) }}
                                </td>
                            @endforeach
                            @if ($rowActions !== [])
                                <td class="px-3 py-2 text-right">
                                    <div class="inline-flex gap-1">
                                        @foreach ($rowActions as $action)
                                            <button
                                                type="button"
                                                wire:click="{{ $action['method'] }}({{ $row['id'] }})"
                                                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ ($action['variant'] ?? null) === 'danger' ? 'text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)]' : 'text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)]' }}"
                                            >
                                                {{ $action['label'] }}<span class="sr-only"> {{ $caption }} baris {{ $row['id'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu untuk layar sempit: tabel padat tidak terbaca di ponsel --}}
        <ul role="list" class="grid gap-3 md:hidden">
            @foreach ($rows as $row)
                <li class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4">
                    <dl class="grid gap-2">
                        @foreach ($columns as $column)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">{{ $column['label'] }}</dt>
                                <dd class="text-sm text-[var(--erp-text-primary)] {{ $column['numeric'] ? 'font-[family-name:var(--erp-font-mono)]' : '' }}">{{ $present($column, $row) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($rowActions !== [])
                        <div class="mt-3 flex flex-wrap gap-2 border-t border-[var(--erp-border)] pt-3">
                            @foreach ($rowActions as $action)
                                <button
                                    type="button"
                                    wire:click="{{ $action['method'] }}({{ $row['id'] }})"
                                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] {{ ($action['variant'] ?? null) === 'danger' ? 'text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)]' : 'text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)]' }}"
                                >
                                    {{ $action['label'] }}<span class="sr-only"> {{ $caption }} baris {{ $row['id'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
