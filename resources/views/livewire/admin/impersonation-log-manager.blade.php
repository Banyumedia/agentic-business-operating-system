<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">Audit Log Impersonasi (Login As)</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Jejak kepatuhan dan audit saat Super Admin masuk ke akun tenant (D-47).
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Paket</a>
                <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tiket</a>
                <a href="{{ route('admin.impersonation-logs') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">Log Impersonasi</a>
                <a href="{{ route('admin.client-logs') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Log Klien</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4 text-xs text-[var(--erp-text-secondary)] flex items-center justify-between">
            <span><strong>Catatan Keamanan (D-47):</strong> Setiap sesi "Login As" dicatat dengan IP, waktu, dan User-Agent. Super Admin tetap tidak dapat mem-bypass aksi finansial (D-45).</span>
        </div>

        <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Waktu Mulai</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Super Admin</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Company Tujuan</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">IP Address</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Status Sesi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $item)
                        <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                            <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                {{ $item->created_at ? $item->created_at->format('d/m/Y H:i:s') : '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-[var(--erp-text)]">
                                {{ $item->admin->name ?? ($item->admin->email ?? 'Admin #' . $item->admin_user_id) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-[var(--erp-text)]">
                                <span class="font-bold block">{{ $item->targetCompany->name ?? 'N/A' }}</span>
                                <span class="text-xs text-[var(--erp-text-secondary)]">Owner: {{ $item->targetCompany->owner->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                {{ $item->ip_address ?: '127.0.0.1' }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($item->expires_at && $item->expires_at->isPast())
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800">
                                        Expired
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-green-100 text-green-800">
                                        Active / Closed
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada riwayat impersonasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
