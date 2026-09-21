<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">Hermes Nodes & Asisten AI Fleet</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Monitoring klaster server backend Hermes dan status profil bot WhatsApp klien (D-37, D-50).
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Paket</a>
                <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tiket</a>
                <a href="{{ route('admin.hermes-nodes') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">Hermes Nodes</a>
                <a href="{{ route('admin.client-logs') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Log Tenant</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        @if (session('success'))
            <div class="rounded-lg border border-[var(--erp-success)] bg-[var(--erp-success)]/10 p-4">
                <p class="text-sm text-[var(--erp-success)]">{{ session('success') }}</p>
            </div>
        @endif

        {{-- Node Cluster Cards --}}
        <div>
            <h2 class="text-xl font-bold text-[var(--erp-text)] mb-4">Klaster Server Node</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @forelse ($nodes as $node)
                    <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-5">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-lg text-[var(--erp-text)]">{{ $node->name }}</h3>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $node->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ ucfirst($node->status) }}
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-xs text-[var(--erp-text-secondary)] truncate">{{ $node->api_url }}</p>
                        
                        <div class="mt-4 pt-3 border-t border-[var(--erp-border)]">
                            <div class="flex justify-between text-xs text-[var(--erp-text-secondary)] mb-1">
                                <span>Kapasitas Profil:</span>
                                <span class="font-semibold text-[var(--erp-text)]">{{ $node->active_profiles }} / {{ $node->max_capacity }}</span>
                            </div>
                            <div class="w-full bg-[var(--erp-surface-secondary)] h-2 rounded-full overflow-hidden">
                                @php
                                    $pct = $node->max_capacity > 0 ? min(100, round(($node->active_profiles / $node->max_capacity) * 100)) : 0;
                                @endphp
                                <div class="bg-[var(--erp-primary)] h-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6 text-center text-sm text-[var(--erp-text-secondary)]">
                        Belum ada node Hermes yang terdaftar di sistem.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Active Profiles / Bot Fleet --}}
        <div>
            <h2 class="text-xl font-bold text-[var(--erp-text)] mb-4">Daftar Bot Asisten Tenant</h2>
            <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Owner & Perusahaan</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Tipe Bot</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Node Provider</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Status Bot</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Terakhir Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $prof)
                            <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">
                                    <span class="font-bold block">{{ $prof->owner->name ?? 'User #' . $prof->owner_user_id }}</span>
                                    <span class="text-xs text-[var(--erp-text-secondary)]">
                                        {{ $prof->companies->pluck('name')->join(', ') ?: '-' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-semibold {{ $prof->type === 'primary' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ strtoupper($prof->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">
                                    {{ $prof->node->name ?? 'Default Node' }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $prof->status === 'connected' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ ucfirst($prof->status ?? 'ready') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                    {{ $prof->last_ping_at ? $prof->last_ping_at->diffForHumans() : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada profil asisten yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
