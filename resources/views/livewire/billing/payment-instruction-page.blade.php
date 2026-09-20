<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <!-- Header -->
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <h1 class="text-3xl font-bold text-[var(--erp-text)]">Instruksi Pembayaran</h1>
            <p class="mt-2 text-[var(--erp-text-secondary)]">
                Nomor invoice: <span class="font-mono font-semibold text-[var(--erp-text)]">{{ $this->orderId }}</span>
            </p>
        </div>
    </div>

    <!-- Content -->
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="space-y-8">
            <!-- Nominal Pembayaran -->
            <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6">
                <h2 class="text-lg font-semibold text-[var(--erp-text)]">Nominal Pembayaran</h2>
                <div class="mt-4 text-5xl font-bold text-[var(--erp-primary)]">
                    {{ $this->amount }}
                </div>
                <p class="mt-2 text-sm text-[var(--erp-text-secondary)]">
                    Pastikan nominal transfer sama persis dengan di atas
                </p>
            </div>

            <!-- Transfer Bank Manual -->
            @if ($this->bankAccount && $this->bankName)
                <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--erp-text)]">Transfer ke Rekening</h2>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="text-sm text-[var(--erp-text-secondary)]">Bank</label>
                            <div class="mt-1 rounded bg-[var(--erp-surface-secondary)] px-3 py-2 font-mono text-[var(--erp-text)]">
                                {{ $this->bankName }}
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-[var(--erp-text-secondary)]">Nomor Rekening</label>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="flex-1 rounded bg-[var(--erp-surface-secondary)] px-3 py-2 font-mono text-[var(--erp-text)]">
                                    {{ $this->bankAccount }}
                                </div>
                                <button
                                    @click="navigator.clipboard.writeText('{{ $this->bankAccount }}')"
                                    class="rounded bg-[var(--erp-secondary)] px-3 py-2 text-sm font-semibold text-[var(--erp-secondary-foreground)] transition-opacity hover:opacity-90"
                                >
                                    Salin
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-[var(--erp-text-secondary)]">Atas Nama</label>
                            <div class="mt-1 rounded bg-[var(--erp-surface-secondary)] px-3 py-2 text-[var(--erp-text)]">
                                {{ $this->bankHolder }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg border border-[var(--erp-warning)] bg-[var(--erp-warning)]/10 p-4">
                        <p class="text-sm text-[var(--erp-warning)]">
                            <strong>Catatan:</strong> Pesankan nomor invoice ({{ $this->orderId }})
                            dalam deskripsi transfer agar admin dapat mengidentifikasi pembayaran Anda.
                        </p>
                    </div>
                </div>
            @endif

            <!-- QRIS Statis -->
            @if ($this->qrisPath)
                <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--erp-text)]">QRIS (Scan dan Bayar)</h2>

                    <div class="mt-4 flex justify-center">
                        <div class="rounded-lg bg-white p-4">
                            <img
                                src="{{ $this->qrisPath }}"
                                alt="QRIS"
                                class="h-64 w-64 object-contain"
                            />
                        </div>
                    </div>

                    <p class="mt-4 text-center text-sm text-[var(--erp-text-secondary)]">
                        Scan kode QRIS dengan aplikasi mobile banking Anda untuk melakukan pembayaran.
                    </p>
                </div>
            @endif

            <!-- Informasi Penting -->
            <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6">
                <h2 class="text-lg font-semibold text-[var(--erp-text)]">Informasi Penting</h2>

                <ul class="mt-4 space-y-3 text-sm text-[var(--erp-text-secondary)]">
                    <li class="flex gap-2">
                        <svg class="h-5 w-5 flex-shrink-0 text-[var(--erp-info)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                        </svg>
                        <span>Pembayaran akan diverifikasi oleh admin dalam 1-2 jam kerja</span>
                    </li>
                    <li class="flex gap-2">
                        <svg class="h-5 w-5 flex-shrink-0 text-[var(--erp-info)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                        </svg>
                        <span>Setelah konfirmasi, akses paket langsung tersedia di dashboard Anda</span>
                    </li>
                    <li class="flex gap-2">
                        <svg class="h-5 w-5 flex-shrink-0 text-[var(--erp-info)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" />
                        </svg>
                        <span>Nominal yang Anda transfer harus sama persis dengan nominal di atas</span>
                    </li>
                </ul>
            </div>

            <!-- Footer -->
            <div class="text-center">
                <a
                    href="{{ route('app.dashboard') }}"
                    class="inline-block rounded-lg bg-[var(--erp-secondary)] px-6 py-2 font-semibold text-[var(--erp-secondary-foreground)] transition-opacity hover:opacity-90"
                >
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
