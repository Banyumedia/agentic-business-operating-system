<div>
    <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 class="text-lg font-semibold text-[var(--erp-text-primary)]">Penggunaan & Paket</h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Pantau sisa kuota AI usaha Anda di sini, sebelum habis.
        </p>

        <p class="mt-3 inline-flex items-center rounded-full bg-[var(--erp-accent-soft)] px-3 py-1 text-sm font-semibold text-[var(--erp-accent)]">
            {{ $tierLabel }}
        </p>

        @if ($membershipInactive)
            <p class="mt-3 rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]" role="alert">
                Langganan usaha sedang tidak aktif, jadi kuota sementara kosong.
                Aktivasi kembali agar asisten AI dan grup WhatsApp bisa dipakai lagi.
            </p>
        @endif

        @if ($isBalanceLow && ! $membershipInactive)
            <p class="mt-3 rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-warning-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]" role="status">
                Sisa kuota AI Anda mulai menipis (tersisa {{ $tokenPercent }}%).
                Agar asisten AI tidak berhenti nanti, pertimbangkan pilih paket yang pas.
                @if ($plansUrl !== '')
                    <a href="{{ $plansUrl }}" class="font-semibold text-[var(--erp-text-link)] underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">Lihat pilihan paket</a>.
                @endif
            </p>
        @endif

        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] p-4">
                <dt class="text-sm text-[var(--erp-text-secondary)]">Sisa token AI</dt>
                <dd class="mt-1">
                    <span class="text-2xl font-bold text-[var(--erp-text-primary)]" aria-live="polite">{{ number_format($tokenBalance, 0, ',', '.') }}</span>
                    <span class="text-sm text-[var(--erp-text-muted)]"> / {{ number_format($tokenQuota, 0, ',', '.') }} token bulan ini</span>
                </dd>
                <div
                    class="mt-3 h-2 rounded-full bg-[var(--erp-bg-secondary)]"
                    role="progressbar"
                    aria-label="Sisa token AI"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $tokenPercent }}"
                >
                    <div class="h-2 rounded-full {{ $isBalanceLow ? 'bg-[var(--erp-warning)]' : 'bg-[var(--erp-accent)]' }}" style="width: {{ $tokenPercent }}%"></div>
                </div>
                <p class="mt-2 text-xs text-[var(--erp-text-muted)]">Token dipakai tiap kali asisten AI menjawab atau mengingatkan pelanggan.</p>
            </div>

            <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] p-4">
                <dt class="text-sm text-[var(--erp-text-secondary)]">Grup WhatsApp terpakai</dt>
                <dd class="mt-1">
                    <span class="text-2xl font-bold text-[var(--erp-text-primary)]">{{ $waGroupsUsed }}</span>
                    <span class="text-sm text-[var(--erp-text-muted)]"> / {{ $maxWaGroups }} grup maksimal</span>
                </dd>
                <p class="mt-2 text-xs text-[var(--erp-text-muted)]">Asisten AI Anda bisa bergabung di grup WA (mis. Kasir, Gudang, Keuangan).</p>
            </div>
        </dl>

        @if ($isFreeTier)
            <p class="mt-6 rounded-[var(--erp-radius-md)] bg-[var(--erp-info-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
                Anda sedang memakai paket gratis. Semua fitur terbuka, hanya saja kuota AI dan grup WA lebih kecil.
                @if ($plansUrl !== '')
                    Ingin kuota lebih besar?
                    <a href="{{ $plansUrl }}" class="font-semibold text-[var(--erp-text-link)] underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">Lihat pilihan paket</a>.
                @endif
            </p>
        @endif
    </div>
</div>
