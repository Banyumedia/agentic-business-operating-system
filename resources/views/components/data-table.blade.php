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
    {{-- Pengumuman urutan aktif untuk pembaca layar: aria-sort hanya
        tersedia di <th>; nilai diputar di live region saat sortBy berjalan. --}}
    <p class="sr-only" aria-live="polite" role="status">
        @php
            $sortedColumn = collect($columns)->first(fn (array $column): bool => $column['field'] === $sort);
        @endphp
        @if ($sortedColumn !== null)
            Diurutkan berdasarkan {{ $sortedColumn['label'] }},
            {{ $direction === 'desc' ? 'menurun' : 'menaik' }}.
        @endif
    </p>

    @if ($rows === [])
        <div role="status" class="flex flex-col items-center justify-center rounded-[var(--erp-radius-lg)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] px-4 py-16 text-center">
            <div class="mb-4 rounded-full bg-[var(--erp-bg-secondary)] p-3 text-[var(--erp-text-muted)] shadow-[var(--erp-card-shadow)] border border-[var(--erp-border)]">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
            <p class="text-base font-medium text-[var(--erp-text-primary)]">Tidak ada data</p>
            <p class="mt-1 max-w-sm text-sm text-[var(--erp-text-secondary)]">
                {{ $emptyMessage }}
            </p>
        </div>
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
                                    wire:loading.attr="disabled"
                                    title="Urutkan berdasarkan {{ $column['label'] }}"
                                    class="inline-flex min-h-11 items-center gap-1 rounded-[var(--erp-radius-sm)] px-1 uppercase hover:text-[var(--erp-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
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
                                                wire:loading.attr="disabled"
                                                title="{{ $action['label'] }}"
                                                class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60 {{ ($action['variant'] ?? null) === 'danger' ? 'text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)]' : 'text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)]' }}"
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

        {{-- Kontrol urutan ringkas untuk layar sempit (Compact Mobile Sort Bar) --}}
        <div class="mb-3 flex items-center gap-2 md:hidden" role="group" aria-label="Urutkan data">
            <div class="relative min-w-0 flex-1">
                <label for="mobile-sort-select" class="sr-only">Urutkan berdasarkan kolom</label>
                <select
                    id="mobile-sort-select"
                    wire:change="sortBy($event.target.value)"
                    class="w-full min-h-11 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-3 py-2 text-sm text-[var(--erp-text-primary)] shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                >
                    <option value="" disabled {{ $sort === null ? 'selected' : '' }}>Pilih Kolom Urutan…</option>
                    @foreach ($columns as $column)
                        <option value="{{ $column['field'] }}" {{ $sort === $column['field'] ? 'selected' : '' }}>
                            Urut: {{ $column['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($sort !== null)
                <button
                    type="button"
                    wire:click="sortBy('{{ $sort }}')"
                    wire:loading.attr="disabled"
                    aria-label="Ubah arah urutan: saat ini {{ $direction === 'desc' ? 'menurun' : 'menaik' }}"
                    title="Ubah arah urutan ({{ $direction === 'desc' ? 'Menurun' : 'Menaik' }})"
                    class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] px-3 text-sm font-semibold text-[var(--erp-text-primary)] shadow-sm hover:bg-[var(--erp-bg-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60"
                >
                    <span aria-hidden="true" class="text-base">{{ $direction === 'desc' ? '↓' : '↑' }}</span>
                </button>
            @endif
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
                                    wire:loading.attr="disabled"
                                    title="{{ $action['label'] }}"
                                    class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] disabled:cursor-wait disabled:opacity-60 {{ ($action['variant'] ?? null) === 'danger' ? 'text-[var(--erp-danger)] hover:bg-[var(--erp-danger-soft)]' : 'text-[var(--erp-text-link)] hover:bg-[var(--erp-bg-active)]' }}"
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
