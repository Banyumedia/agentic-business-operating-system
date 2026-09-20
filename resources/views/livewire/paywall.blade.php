<div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 sm:py-12" aria-busy="false" wire:loading.attr="aria-busy">
    <header class="mb-8">
        <p class="text-sm font-semibold text-[var(--erp-accent)]">Kuota gratis Anda sudah habis</p>
        <h1 class="mt-1 text-3xl font-bold text-[var(--erp-text-primary)]">Pilih paket untuk melanjutkan</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--erp-text-secondary)]">
            Selama masa gratis, semua fitur dan AI tetap terbuka tanpa batasan waktu.
            Aksi terakhir ditolak karena kuota gratis Anda sudah terpakai habis.
            Untuk melanjutkan memakai AI dan grup WhatsApp, silakan pilih salah satu paket berbayar di bawah ini.
        </p>
    </header>

    @if ($reason === 'token_quota')
        <section
            role="status" aria-live="polite"
            class="mb-8 rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-bg-elevated)] p-4 sm:p-6"
        >
            <h2 class="text-base font-semibold text-[var(--erp-text-primary)]">Kuota token AI habis</h2>
            <p class="mt-1 text-sm leading-6 text-[var(--erp-text-secondary)]">
                Saldo token AI Anda sudah habis, jadi permintaan AI ditolak agar tidak ada biaya tak terduga.
                Data Anda tetap aman dan tidak ada yang dihapus.
            </p>
        </section>
    @elseif ($reason === 'wa_group_quota')
        <section
            role="status" aria-live="polite"
            class="mb-8 rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-bg-elevated)] p-4 sm:p-6"
        >
            <h2 class="text-base font-semibold text-[var(--erp-text-primary)]">Kuota grup WhatsApp tercapai</h2>
            <p class="mt-1 text-sm leading-6 text-[var(--erp-text-secondary)]">
                Anda sudah memakai seluruh jatah grup WhatsApp di paket Anda sekarang,
                jadi penambahan grup baru ditolak sampai paket ditingkatkan.
            </p>
        </section>
    @endif

    <section aria-labelledby="paywall-quota-heading">
        <h2 id="paywall-quota-heading" class="text-lg font-semibold text-[var(--erp-text-primary)]">Yang Anda dapat gratis</h2>
        <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
            Paket gratis Anda mencakup {{ $freeTokenQuota }} token AI dan {{ $freeWaGroups }} grup WhatsApp.
        </p>
    </section>

    @if ($plans !== [])
        <section aria-labelledby="paywall-plans-heading" class="mt-8">
            <h2 id="paywall-plans-heading" class="text-lg font-semibold text-[var(--erp-text-primary)]">Paket berbayar yang tersedia</h2>

            <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($plans as $plan)
                    <li
                        class="flex flex-col rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-4 shadow-[var(--erp-card-shadow)] sm:p-6"
                    >
                        <h3 class="text-lg font-bold text-[var(--erp-text-primary)]">{{ $plan['name'] }}</h3>
                        <p class="mt-1 text-2xl font-bold text-[var(--erp-accent)]">{{ $plan['monthly_price'] }}</p>
                        <p class="mt-0.5 text-sm text-[var(--erp-text-muted)]">per bulan ({{ $plan['annual_price'] }}/tahun)</p>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-[var(--erp-text-secondary)]">Token AI per bulan</dt>
                                <dd class="font-semibold text-[var(--erp-text-primary)]">{{ number_format($plan['monthly_token_quota'], 0, ',', '.') }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-[var(--erp-text-secondary)]">Grup WhatsApp</dt>
                                <dd class="font-semibold text-[var(--erp-text-primary)]">{{ $plan['max_wa_groups'] }}</dd>
                            </div>
                        </dl>

                        <div class="mt-auto pt-5">
                            <button
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-4 py-2 text-sm font-semibold text-[var(--erp-text-inverse)] transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                            >
                                Pilih paket {{ $plan['name'] }}
                                <span class="sr-only">. Belum tersedia di jalur uji ini.</span>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 text-sm text-[var(--erp-text-muted)]">
                Tombol pilih paket masih dalam mode uji: pembayaran belum diproses di halaman ini.
            </p>
        </section>
    @else
        <section
            role="status" aria-live="polite"
            class="mt-8 rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-4 sm:p-6"
        >
            <h2 class="text-base font-semibold text-[var(--erp-text-primary)]">Belum ada paket yang bisa dipilih</h2>
            <p class="mt-1 text-sm leading-6 text-[var(--erp-text-secondary)]">
                Untuk saat ini belum ada paket berbayar yang terbuka untuk pendaftaran mandiri.
                Silakan hubungi admin platform untuk pengaturan langganan Anda.
            </p>
        </section>
    @endif

    <nav class="mt-10 text-sm" aria-label="Navigasi paywall">
        <a
            href="{{ route('app.dashboard') }}"
            class="inline-flex min-h-11 items-center rounded-[var(--erp-radius-md)] px-4 py-2 font-semibold text-[var(--erp-accent)] underline underline-offset-4 transition hover:text-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
        >
            Kembali ke dashboard
        </a>
    </nav>
</div>
