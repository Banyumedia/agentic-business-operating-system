<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
    </header>

    <section aria-labelledby="ledger-filter-title" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4">
        <h2 id="ledger-filter-title" class="text-sm font-semibold text-[var(--erp-text-primary)]">Penyaring</h2>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="ledger-period" class="block text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Periode</label>
                <select id="ledger-period" wire:model.live="period"
                    class="mt-1 w-full rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-base)] px-3 py-2 text-sm text-[var(--erp-text-primary)]">
                    <option value="all">Semua periode</option>
                    @foreach ($periods as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @if ($hasDirection)
                <div>
                    <label for="ledger-direction" class="block text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Arah</label>
                    <select id="ledger-direction" wire:model.live="direction"
                        class="mt-1 w-full rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-base)] px-3 py-2 text-sm text-[var(--erp-text-primary)]">
                        <option value="all">Masuk &amp; keluar</option>
                        <option value="in">Hanya masuk</option>
                        <option value="out">Hanya keluar</option>
                    </select>
                </div>
            @endif

            @foreach ($relationFilters as $filter)
                <div>
                    <label for="ledger-filter-{{ $filter['field'] }}" class="block text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">{{ $filter['label'] }}</label>
                    <select id="ledger-filter-{{ $filter['field'] }}" wire:model.live="relation.{{ $filter['field'] }}"
                        class="mt-1 w-full rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] bg-[var(--erp-bg-base)] px-3 py-2 text-sm text-[var(--erp-text-primary)]">
                        <option value="all">Semua</option>
                        @if ($filter['nullable'])
                            <option value="none">Tanpa {{ $filter['label'] }}</option>
                        @endif
                        @foreach ($filter['options'] as $id => $title)
                            <option value="{{ $id }}">{{ $title }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>

        @if ($filtered)
            <button type="button" wire:click="resetFilters"
                class="mt-3 inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] px-3 py-2 text-sm font-semibold text-[var(--erp-text-secondary)] hover:bg-[var(--erp-bg-hover)]">
                Hapus penyaring
            </button>
        @endif
    </section>

    @if ($rejected > 0)
        <p role="status" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-bg-inset)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            {{ $rejected }} entri tidak dihitung ke dalam saldo karena arah atau nominalnya tidak dapat dibaca.
            Entri itu tetap ditampilkan dengan penanda agar dapat diperbaiki.
        </p>
    @endif

    <dl class="grid gap-3 sm:grid-cols-3">
        @if ($hasDirection)
            <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Masuk{{ $filtered ? ' (tersaring)' : '' }}</dt>
                <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-lg text-[var(--erp-success)]">Rp {{ number_format($incoming, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Keluar{{ $filtered ? ' (tersaring)' : '' }}</dt>
                <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-lg text-[var(--erp-danger)]">Rp {{ number_format($outgoing, 0, ',', '.') }}</dd>
            </div>
        @else
            <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Nilai tercatat</dt>
                <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-lg text-[var(--erp-text-primary)]">Rp {{ number_format($incoming, 0, ',', '.') }}</dd>
            </div>
        @endif

        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4">
            <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">{{ $hasDirection ? 'Saldo' : 'Total' }} seluruh riwayat</dt>
            <dd class="mt-1 font-[family-name:var(--erp-font-mono)] tabular-nums text-lg font-semibold {{ $balance < 0 ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-text-primary)]' }}">
                Rp {{ number_format($balance, 0, ',', '.') }}
            </dd>
        </div>
    </dl>

    <section aria-labelledby="ledger-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6">
        <h2 id="ledger-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Riwayat</h2>

        @if ($entries === [])
            <div role="status" class="mt-4 flex flex-col items-center justify-center rounded-[var(--erp-radius-lg)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] px-4 py-16 text-center">
                <div class="mb-4 rounded-full bg-[var(--erp-bg-secondary)] p-3 text-[var(--erp-text-muted)] shadow-[var(--erp-card-shadow)] border border-[var(--erp-border)]">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                @if ($filtered)
                    <p class="text-base font-medium text-[var(--erp-text-primary)]">Tidak ada hasil untuk penyaring ini</p>
                    <p class="mt-1 max-w-sm text-sm text-[var(--erp-text-secondary)]">
                        Buku ini punya catatan, tetapi tidak ada yang cocok dengan penyaring yang aktif.
                    </p>
                    <button type="button" wire:click="resetFilters"
                        class="mt-4 inline-flex min-h-11 items-center rounded-[var(--erp-radius-sm)] border border-[var(--erp-border-strong)] px-4 py-2 text-sm font-semibold text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-hover)]">
                        Hapus penyaring
                    </button>
                @else
                    <p class="text-base font-medium text-[var(--erp-text-primary)]">Tidak ada data</p>
                    <p class="mt-1 max-w-sm text-sm text-[var(--erp-text-secondary)]">
                        Belum ada {{ $term }} yang tercatat.
                    </p>
                @endif
            </div>
        @else
            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="w-full border-collapse text-left text-sm">
                    <caption class="sr-only">{{ $label }} dengan saldo berjalan</caption>
                    <thead>
                        <tr class="border-b border-[var(--erp-border-strong)]">
                            <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Tanggal</th>
                            <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Keterangan</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Nilai</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-bg-hover)]">
                                <td class="px-3 py-2 font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-secondary)]">{{ $entry['date'] }}</td>
                                <td class="px-3 py-2 text-[var(--erp-text-primary)]">{{ $entry['description'] }}</td>
                                @if ($entry['rejected'])
                                    <td class="px-3 py-2 text-right text-xs font-semibold text-[var(--erp-warning)]">Perlu diperbaiki</td>
                                    <td class="px-3 py-2 text-right text-xs text-[var(--erp-text-muted)]">Tidak dihitung</td>
                                @else
                                    <td class="px-3 py-2 text-right font-[family-name:var(--erp-font-mono)] tabular-nums {{ $entry['outgoing'] ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-success)]' }}">
                                        {{ $entry['outgoing'] ? '-' : '+' }}Rp {{ number_format($entry['amount'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-[family-name:var(--erp-font-mono)] tabular-nums text-[var(--erp-text-primary)]">
                                        Rp {{ number_format($entry['balance'], 0, ',', '.') }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul role="list" class="mt-4 grid gap-3 md:hidden">
                @foreach ($entries as $entry)
                    <li class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] p-3">
                        <p class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-muted)]">{{ $entry['date'] }}</p>
                        <p class="mt-1 text-sm font-medium text-[var(--erp-text-primary)]">{{ $entry['description'] }}</p>
                        <div class="mt-2 flex items-baseline justify-between gap-3">
                            @if ($entry['rejected'])
                                <span class="text-xs font-semibold text-[var(--erp-warning)]">Perlu diperbaiki</span>
                                <span class="text-xs text-[var(--erp-text-muted)]">Tidak dihitung ke saldo</span>
                            @else
                                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums text-sm {{ $entry['outgoing'] ? 'text-[var(--erp-danger)]' : 'text-[var(--erp-success)]' }}">
                                    {{ $entry['outgoing'] ? '-' : '+' }}Rp {{ number_format($entry['amount'], 0, ',', '.') }}
                                </span>
                                <span class="font-[family-name:var(--erp-font-mono)] tabular-nums text-xs text-[var(--erp-text-secondary)]">
                                    Saldo Rp {{ number_format($entry['balance'], 0, ',', '.') }}
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
