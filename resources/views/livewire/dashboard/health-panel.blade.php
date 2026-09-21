{{--
    Panel Kesehatan Usaha (D-50, BI-B2).

    Dipisah ke partial sendiri supaya markup panjang ini tidak menumpuk di
    `dashboard.blade.php` dan agar rebase berikutnya tidak lagi bertabrakan di
    satu berkas besar. Seluruh string statis: tidak ada `term()` dan tidak ada
    kata industri (D-31). Angka datang dari `BusinessHealthAnalyzer`; view hanya
    menyajikan.

    Kehadiran panel ini sudah ditentukan di komponen: `$health` null untuk
    non-owner, jadi tidak ada percabangan peran di sini.
--}}
<section aria-labelledby="dashboard-health-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] shadow-[var(--erp-card-shadow)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] px-5 py-4 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--erp-accent)]">Bulan ini</p>
                <h2 id="dashboard-health-title" class="mt-1 text-lg font-semibold text-[var(--erp-text-primary)]">Kesehatan Usaha</h2>
            </div>
            <p class="text-sm text-[var(--erp-text-muted)]">{{ \Illuminate\Support\Carbon::parse($health['period']['start'])->translatedFormat('M Y') }}</p>
        </div>
    </div>

    @if ($health['insufficient_data'])
        {{-- Data belum cukup: kosong sopan, bukan nol dan bukan error. --}}
        <div class="px-5 py-10 text-center sm:px-6">
            <h3 class="font-semibold text-[var(--erp-text-primary)]">Analisis belum tersedia</h3>
            <p class="mt-2 text-sm leading-6 text-[var(--erp-text-muted)]">Belum cukup data untuk analisis — mulai catat transaksi.</p>
        </div>
    @else
        <div class="p-5 sm:p-6">
            {{-- Skeleton menutup grid saat reload berjalan. --}}
            <div wire:loading.class="hidden" wire:target="reload" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ([
                    ['label' => 'Omzet', 'value' => $health['revenue'], 'tone' => 'text-[var(--erp-success)]'],
                    ['label' => 'Biaya', 'value' => $health['expenses'], 'tone' => 'text-[var(--erp-danger)]'],
                    ['label' => 'Margin', 'value' => $health['margin'], 'tone' => 'text-[var(--erp-text-primary)]'],
                ] as $card)
                    <article class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5">
                        <p class="text-sm font-medium text-[var(--erp-text-secondary)]">{{ $card['label'] }}</p>
                        <p class="mt-3 font-[family-name:var(--erp-font-mono)] {{ $card['tone'] }} text-2xl font-bold tabular-nums">Rp {{ number_format($card['value'], 0, ',', '.') }}</p>
                    </article>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                @php
                    $direction = $health['trend']['direction'];
                    $trendClass = $direction === 'up'
                        ? 'bg-[var(--erp-success-soft)] text-[var(--erp-success)]'
                        : ($direction === 'down'
                            ? 'bg-[var(--erp-danger-soft)] text-[var(--erp-danger)]'
                            : 'bg-[var(--erp-bg-inset)] text-[var(--erp-text-muted)]');
                    $trendText = $direction === 'up' ? 'Naik' : ($direction === 'down' ? 'Turun' : 'Datar');
                @endphp
                <span @class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold tabular-nums', $trendClass])>
                    {{ $trendText }}
                    @if ($direction !== 'flat')
                        {{ number_format(abs($health['trend']['percent']), 2, ',', '.') }}%
                    @endif
                </span>
                <p class="text-sm text-[var(--erp-text-muted)]">Omzet dibanding bulan sebelumnya</p>
            </div>

            @if ($health['highlights'] !== [])
                <h3 class="mt-6 text-sm font-semibold text-[var(--erp-text-primary)]">Sorotan periode ini</h3>
                <ul class="mt-3 space-y-3">
                    @foreach ($health['highlights'] as $highlight)
                        <li class="rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)] p-3">
                            <p class="text-sm font-medium text-[var(--erp-text-secondary)]">{{ $highlight['label'] }}</p>
                            <p class="mt-0.5 text-sm text-[var(--erp-text-muted)]">{{ $highlight['detail'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Skeleton kesehatan: bentuk kartu sama dengan grid aslinya. --}}
        <div wire:loading.class="grid" wire:loading wire:target="reload" aria-hidden="true" class="hidden grid-cols-1 gap-4 p-5 sm:grid-cols-3 sm:p-6">
            @foreach ([1, 2, 3] as $placeholder)
                <article class="animate-pulse rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-5">
                    <div class="h-3 w-20 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)]"></div>
                    <div class="mt-4 h-7 w-28 rounded-[var(--erp-radius-sm)] bg-[var(--erp-bg-inset)]"></div>
                </article>
            @endforeach
        </div>
    @endif
</section>
