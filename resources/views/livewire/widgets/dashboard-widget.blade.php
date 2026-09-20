<article class="h-full rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5 shadow-[var(--erp-card-shadow)]">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-base font-semibold text-[var(--erp-text-primary)]">{{ $widget['title'] }}</h3>
            <p class="mt-1 text-sm text-[var(--erp-text-muted)]">{{ $widget['meta'] }}</p>
        </div>
        <span @class([
            'inline-flex min-w-12 items-center justify-center rounded-[var(--erp-radius-md)] px-3 py-2 text-lg font-bold tabular-nums',
            'bg-[var(--erp-success-soft)] text-[var(--erp-success)]' => $widget['tone'] === 'success',
            'bg-[var(--erp-warning-soft)] text-[var(--erp-warning)]' => $widget['tone'] === 'warning',
            'bg-[var(--erp-danger-soft)] text-[var(--erp-danger)]' => $widget['tone'] === 'danger',
            'bg-[var(--erp-info-soft)] text-[var(--erp-info)]' => $widget['tone'] === 'info',
            'bg-[var(--erp-accent-soft)] text-[var(--erp-accent)]' => $widget['tone'] === 'accent',
        ])>{{ $widget['value'] }}</span>
    </div>

    @if ($widget['items'] !== [])
        <ul class="mt-5 space-y-1" aria-label="Rincian {{ $widget['title'] }}">
            @foreach ($widget['items'] as $item)
                <li class="group flex items-start justify-between gap-4 rounded-[var(--erp-radius-md)] p-3 transition-colors hover:bg-[var(--erp-bg-inset)]">
                    <span class="text-sm font-medium text-[var(--erp-text-primary)]">{{ $item['primary'] }}</span>
                    <span class="text-right text-sm text-[var(--erp-text-muted)] group-hover:text-[var(--erp-text-secondary)] transition-colors">{{ $item['secondary'] }}</span>
                </li>
            @endforeach
        </ul>
    @elseif ($widget['key'] !== 'kpi_cashflow')
        {{-- Empty state kontekstual per widget, bukan pesan generik. --}}
        <div role="status" class="mt-5 flex flex-col items-center justify-center rounded-[var(--erp-radius-md)] border border-dashed border-[var(--erp-border-strong)] bg-[var(--erp-bg-inset)] px-4 py-8 text-center">
            <div class="mb-3 rounded-full bg-[var(--erp-bg-secondary)] p-2 text-[var(--erp-text-muted)] shadow-sm border border-[var(--erp-border)]">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
            @php
                $emptyStates = [
                    'upcoming_schedule' => 'Tidak ada agenda mendatang',
                    'low_stock' => 'Semua stok aman',
                    'deals_pipeline' => 'Belum ada tahap terisi',
                    'pending_approvals' => 'Tidak ada yang menunggu persetujuan',
                ];
                $emptyHints = [
                    'upcoming_schedule' => 'Agenda baru akan muncul di sini begitu dijadwalkan.',
                    'low_stock' => 'Tidak ada item di bawah batas minimum.',
                    'deals_pipeline' => 'Pipeline akan terisi saat data tahap mulai dicatat.',
                    'pending_approvals' => 'Semua tiket sudah diproses.',
                ];
                $key = $widget['key'];
            @endphp
            <p class="text-sm font-medium text-[var(--erp-text-primary)]">{{ $emptyStates[$key] ?? 'Tidak ada data' }}</p>
            <p class="mt-1 max-w-sm text-xs text-[var(--erp-text-secondary)]">{{ $emptyHints[$key] ?? 'Tidak ada item yang memerlukan tindakan.' }}</p>
        </div>
    @endif
</article>
