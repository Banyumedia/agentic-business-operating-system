<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <!-- Header -->
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <h1 class="text-3xl font-bold text-[var(--erp-text)]">Konfirmasi Invoice Pembayaran</h1>
            <p class="mt-2 text-[var(--erp-text-secondary)]">
                Daftar invoice pending yang menunggu konfirmasi pembayaran
            </p>
        </div>
    </div>

    <!-- Content -->
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-[var(--erp-success)] bg-[var(--erp-success)]/10 p-4">
                <p class="text-sm text-[var(--erp-success)]">{{ session('success') }}</p>
            </div>
        @endif

        @if ($this->errors->any())
            @foreach ($this->errors->all() as $error)
                <div class="mb-6 rounded-lg border border-[var(--erp-error)] bg-[var(--erp-error)]/10 p-4">
                    <p class="text-sm text-[var(--erp-error)]">{{ $error }}</p>
                </div>
            @endforeach
        @endif

        @if ($pendingInvoices->count())
            <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--erp-text)]">Invoice ID</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--erp-text)]">Perusahaan</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--erp-text)]">Nominal</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--erp-text)]">Paket</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--erp-text)]">Dibuat</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-[var(--erp-text)]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingInvoices as $invoice)
                            <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                                <td class="px-6 py-4 font-mono text-sm text-[var(--erp-text)]">
                                    {{ $invoice->order_id }}
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">
                                    {{ $invoice->company->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-[var(--erp-text)]">
                                    Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">
                                    {{ $invoice->membership?->plan?->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text-secondary)]">
                                    {{ $invoice->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4">
                                    <button
                                        wire:click="confirmPayment({{ $invoice->id }})"
                                        wire:loading.attr="disabled"
                                        wire:confirm="Konfirmasi invoice ini sebagai lunas?"
                                        class="rounded-lg bg-[var(--erp-success)] px-4 py-2 text-sm font-semibold text-[var(--erp-success-foreground)] transition-opacity hover:opacity-90 disabled:opacity-50"
                                    >
                                        <span wire:loading.remove wire:target="confirmPayment">Konfirmasi</span>
                                        <span wire:loading wire:target="confirmPayment" class="flex items-center justify-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $pendingInvoices->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-[var(--erp-text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 12a5 5 0 1110 0A5 5 0 017 12z" />
                </svg>
                <h2 class="mt-4 text-lg font-semibold text-[var(--erp-text)]">Tidak ada invoice pending</h2>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Semua invoice sudah dikonfirmasi atau belum ada yang perlu diproses.
                </p>
            </div>
        @endif
    </div>
</div>
