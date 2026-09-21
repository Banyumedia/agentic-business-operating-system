<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">Log Aktivitas Klien</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Pantau aksi operasional tenant dan histori pemakaian token AI di seluruh sistem.
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Paket</a>
                <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tiket</a>
                <a href="{{ route('admin.client-logs') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">Log Klien</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex gap-2">
                <button wire:click="$set('activeTab', 'activities')" class="px-4 py-2 text-sm font-semibold rounded-lg {{ $activeTab === 'activities' ? 'bg-[var(--erp-text)] text-[var(--erp-surface)]' : 'border border-[var(--erp-border)] text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)]' }}">
                    Aktivitas Operasional
                </button>
                <button wire:click="$set('activeTab', 'tokens')" class="px-4 py-2 text-sm font-semibold rounded-lg {{ $activeTab === 'tokens' ? 'bg-[var(--erp-text)] text-[var(--erp-surface)]' : 'border border-[var(--erp-border)] text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)]' }}">
                    Pemakaian Token AI
                </button>
            </div>

            <div class="flex items-center gap-2">
                <label class="text-xs text-[var(--erp-text-secondary)] font-medium">Filter Company:</label>
                <select wire:model.live="selectedCompanyId" class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-1.5 text-sm text-[var(--erp-text)]">
                    <option value="all">Semua Perusahaan</option>
                    @foreach ($companies as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($activeTab === 'activities')
            <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Waktu</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Perusahaan</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Pelaku (User)</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Tipe Aksi</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Detail / Ringkasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                                <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                    {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-[var(--erp-text)]">
                                    {{ $log->company->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">
                                    {{ $log->user->name ?? ($log->user_id ? "User #{$log->user_id}" : 'Sistem / Bot') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex rounded bg-[var(--erp-surface-secondary)] px-2 py-0.5 text-xs font-mono text-[var(--erp-text)]">
                                        {{ $log->type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text-secondary)] max-w-md truncate">
                                    {{ $log->content ?: "{$log->subject_type} #{$log->subject_id}" }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada catatan aktivitas klien.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Waktu</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Perusahaan</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Arah</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Jumlah Token</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Sisa Saldo</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Model / Sumber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                                <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                    {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-[var(--erp-text)]">
                                    {{ $log->company->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $log->direction === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $log->direction === 'credit' ? '+ Topup / Credit' : '- Pemakaian / Debit' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono font-bold {{ $log->direction === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($log->amount) }}
                                </td>
                                <td class="px-6 py-4 text-sm font-mono text-[var(--erp-text)]">
                                    {{ number_format($log->balance_after) }}
                                </td>
                                <td class="px-6 py-4 text-xs text-[var(--erp-text-secondary)]">
                                    <div class="font-medium text-[var(--erp-text)]">{{ $log->model ?: '-' }}</div>
                                    <div>{{ $log->source ?: 'system' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada transaksi token.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
