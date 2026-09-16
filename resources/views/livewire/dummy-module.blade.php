<div class="space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--erp-text-muted)]">Ruang kerja</p>
        <h1 class="mt-2 text-3xl font-bold text-[var(--erp-text-primary)]">{{ $label }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-[var(--erp-text-secondary)]">
            Struktur layar sudah dipilih oleh konfigurasi perusahaan. Data operasional akan dirender oleh pola layar generik.
        </p>
    </header>

    <section aria-labelledby="screen-contract-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)]">
        <h2 id="screen-contract-title" class="text-lg font-semibold text-[var(--erp-text-primary)]">Kontrak layar aktif</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-[var(--erp-radius-md)] bg-[var(--erp-bg-base)] p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Pola layar</dt>
                <dd class="mt-1 font-mono text-sm text-[var(--erp-text-primary)]">{{ $screen }}</dd>
            </div>
            <div class="rounded-[var(--erp-radius-md)] bg-[var(--erp-bg-base)] p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-[var(--erp-text-muted)]">Sumber data</dt>
                <dd class="mt-1 font-mono text-sm text-[var(--erp-text-primary)]">{{ $entity }}</dd>
            </div>
        </dl>
        <p class="mt-5 text-sm text-[var(--erp-text-muted)]" role="status">
            Implementasi interaksi layar dilanjutkan pada task pola layar berikutnya.
        </p>
    </section>
</div>
