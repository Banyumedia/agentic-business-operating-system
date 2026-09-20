<div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)]">
    <h2 class="text-lg font-semibold text-[var(--erp-text-primary)]">Penggunaan &amp; Paket</h2>
    <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
        Ringkasan kuota AI dan paket langganan usaha Anda. Semua fitur tetap bisa dipakai selama kuota masih tersisa.
    </p>

    {{-- Label tier: nama paket, atau "Tier Gratis" untuk tanpa membership (D-60). --}}
    @if ($isFreeTier)
        <p class="mt-4 inline-flex items-center rounded-full bg-[var(--erp-info-soft)] px-3 py-1 text-xs font-semibold text-[var(--erp-text-primary)]">
            Tier Gratis
        </p>
    @else
        <p class="mt-4 inline-flex items-center rounded-full bg-[var(--erp-accent)] px-3 py-1 text-xs font-semibold text-[var(--erp-text-inverse)]">
            {{ $planName ?? 'Paket' }}
        </p>
    @endif

    {{-- Peringatan dini sopan: sisa token < 20% kuota (W2), bahasa awam,
        tidak menakutkan, token warning lembut (U-05). --}}
    @if ($lowBalance)
        <div role="status" class="mt-4 rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-warning-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
            <p class="font-semibold">Sisa kuota AI menipis</p>
            <p class="mt-1">
                Jangan khawatir — asisten AI Anda masih jalan seperti biasa.
                Bila nanti kuotanya habis, Anda bisa isi ulang atau naik paket dari halaman Paket.
            </p>
        </div>
    @endif

    {{-- Indikator kuota: dl semantik + angka dari gate (bukan hardcode),
        tabular-nums untuk keterbacaan angka (kontrak P-B). --}}
    <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg)] p-4">
            <dt class="text-sm text-[var(--erp-text-secondary)]">Sisa token AI</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[var(--erp-text-primary)]">
                {{ number_format($tokenBalance, 0, ',', '.') }}
                <span class="text-sm font-normal text-[var(--erp-text-muted)]">dari {{ number_format($tokenQuota, 0, ',', '.') }}</span>
            </dd>
        </div>
        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg)] p-4">
            <dt class="text-sm text-[var(--erp-text-secondary)]">Grup WhatsApp</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[var(--erp-text-primary)]">
                {{ $maxWaGroups }}
                <span class="text-sm font-normal text-[var(--erp-text-muted)]">maksimal {{ $maxWaGroups }} grup</span>
            </dd>
        </div>
    </dl>
    <p class="mt-3 text-sm text-[var(--erp-text-muted)]">
        Angka grup menunjukkan batas paket Anda saat ini; jumlah grup yang sudah tersambung akan terhitung di sini begitu fitur asisten aktif.
    </p>

    {{-- Ajakan upgrade halus untuk tier gratis (U-05): satu kalimat,
        bukan CTA jualan. Tautan halaman paket menyusul bersama halaman
        Paket (lane W1) — jangan render link mati. --}}
    @if ($isFreeTier)
        <p class="mt-5 rounded-[var(--erp-radius-sm)] bg-[var(--erp-info-soft)] p-3 text-sm text-[var(--erp-text-primary)]">
            Anda memakai Tier Gratis. Butuh kuota lebih besar? Nanti di halaman Paket Anda bisa memilih langganan yang pas — tanpa biaya tersembunyi.
        </p>
    @endif
</div>
