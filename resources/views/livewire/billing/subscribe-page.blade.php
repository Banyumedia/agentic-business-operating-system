<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <!-- Header -->
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <h1 class="text-3xl font-bold text-[var(--erp-text)]">Pilih Paket</h1>
            <p class="mt-2 text-[var(--erp-text-secondary)]">
                Tingkatkan layanan Anda dengan memilih paket yang sesuai kebutuhan bisnis.
            </p>
        </div>
    </div>

    <!-- Content -->
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($this->plans)
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->plans as $plan)
                    <div class="flex flex-col rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] shadow-sm">
                        <!-- Header -->
                        <div class="border-b border-[var(--erp-border)] px-6 py-4">
                            <h2 class="text-xl font-semibold text-[var(--erp-text)]">
                                {{ $plan['name'] }}
                            </h2>
                        </div>

                        <!-- Price -->
                        <div class="px-6 py-6">
                            <div class="text-4xl font-bold text-[var(--erp-text)]">
                                {{ $plan['monthly_price'] }}
                            </div>
                            <div class="mt-1 text-sm text-[var(--erp-text-secondary)]">per bulan</div>
                        </div>

                        <!-- Features -->
                        <div class="flex-1 border-t border-[var(--erp-border)] px-6 py-4">
                            <ul class="space-y-3">
                                <li class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-[var(--erp-success)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-sm text-[var(--erp-text)]">
                                        Grup WhatsApp: {{ $plan['max_wa_groups'] }}
                                    </span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-[var(--erp-success)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-sm text-[var(--erp-text)]">
                                        Kuota AI: {{ number_format($plan['monthly_token_quota']) }} token/bulan
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Action -->
                        <div class="border-t border-[var(--erp-border)] px-6 py-4">
                            <button
                                wire:click="selectPlan({{ $plan['id'] }})"
                                wire:loading.attr="disabled"
                                class="w-full rounded-lg bg-[var(--erp-primary)] px-4 py-2 text-center font-semibold text-[var(--erp-primary-foreground)] transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="selectPlan">Pilih Paket</span>
                                <span wire:loading wire:target="selectPlan" class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Memproses...
                                </span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('plan_selection')
                <div class="mt-6 rounded-lg border border-[var(--erp-error)] bg-[var(--erp-error)]/10 p-4">
                    <p class="text-sm text-[var(--erp-error)]">{{ $message }}</p>
                </div>
            @enderror
        @else
            <!-- Fallback: Tidak ada paket atau database belum ready -->
            <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-[var(--erp-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6m0 0h-6m0 0h-6" />
                </svg>
                <h2 class="mt-4 text-lg font-semibold text-[var(--erp-text)]">Tidak ada paket tersedia</h2>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Hubungi admin untuk meminta akses paket atau verifikasi konfigurasi sistem.
                </p>
            </div>
        @endif
    </div>
</div>
