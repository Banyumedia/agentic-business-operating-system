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
        <ul class="mt-5 divide-y divide-[var(--erp-border)]" aria-label="Rincian {{ $widget['title'] }}">
            @foreach ($widget['items'] as $item)
                <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <span class="text-sm font-medium text-[var(--erp-text-primary)]">{{ $item['primary'] }}</span>
                    <span class="text-right text-sm text-[var(--erp-text-muted)]">{{ $item['secondary'] }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-5 rounded-[var(--erp-radius-md)] bg-[var(--erp-bg-inset)] px-4 py-3 text-sm text-[var(--erp-text-muted)]">
            Tidak ada item yang memerlukan tindakan.
        </p>
    @endif
</article>
