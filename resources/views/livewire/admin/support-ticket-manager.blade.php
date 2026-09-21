<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">Pusat Bantuan & Tiket Master Bot</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Kelola tiket keluhan klien yang masuk melalui WhatsApp Master Bot / BOS Care (T-17).
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Paket</a>
                <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">Tiket</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        @if (session('success'))
            <div class="rounded-lg border border-[var(--erp-success)] bg-[var(--erp-success)]/10 p-4">
                <p class="text-sm text-[var(--erp-success)]">{{ session('success') }}</p>
            </div>
        @endif

        {{-- Resolving modal / form --}}
        @if ($resolvingId)
            <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--erp-text)] mb-2">Selesaikan Tiket #{{ $resolvingId }}</h3>
                <p class="text-xs text-[var(--erp-text-secondary)] mb-4">Catatan ini akan disimpan dan dapat dikirimkan ke WhatsApp klien sebagai notifikasi penyelesaian.</p>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Catatan Resolusi / Perbaikan</label>
                    <textarea wire:model="resolutionNote" rows="3" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]" placeholder="Contoh: Bug cetak struk kasir sudah diperbaiki pada versi terbaru."></textarea>
                    @error('resolutionNote') <span class="text-xs text-[var(--erp-error)]">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2">
                    <button wire:click="resolve" class="rounded bg-[var(--erp-success)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Tandai Selesai (Resolved)</button>
                    <button wire:click="cancelResolve" class="rounded border border-[var(--erp-border)] px-4 py-2 text-sm text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)]">Batal</button>
                </div>
            </div>
        @endif

        {{-- Filter Tabs --}}
        <div class="flex gap-2">
            <button wire:click="$set('filterStatus', 'all')" class="px-3 py-1 text-xs rounded-full font-medium {{ $filterStatus === 'all' ? 'bg-[var(--erp-text)] text-[var(--erp-surface)]' : 'border border-[var(--erp-border)] text-[var(--erp-text)]' }}">Semua</button>
            <button wire:click="$set('filterStatus', 'open')" class="px-3 py-1 text-xs rounded-full font-medium {{ $filterStatus === 'open' ? 'bg-amber-600 text-white' : 'border border-[var(--erp-border)] text-[var(--erp-text)]' }}">Terbuka (Open)</button>
            <button wire:click="$set('filterStatus', 'resolved')" class="px-3 py-1 text-xs rounded-full font-medium {{ $filterStatus === 'resolved' ? 'bg-emerald-600 text-white' : 'border border-[var(--erp-border)] text-[var(--erp-text)]' }}">Selesai (Resolved)</button>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">ID / Tanggal</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Perusahaan</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Subjek & Deskripsi</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Status</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $t)
                        <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                            <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                #{{ $t->id }}<br>
                                {{ $t->created_at->format('d/m H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-[var(--erp-text)]">
                                {{ $t->company?->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-sm max-w-md">
                                <span class="font-semibold text-[var(--erp-text)] block">{{ $t->subject }}</span>
                                <span class="text-xs text-[var(--erp-text-secondary)] line-clamp-2">{{ $t->description }}</span>
                                @if($t->resolution_notes)
                                    <div class="mt-2 text-xs bg-[var(--erp-surface-secondary)] p-2 rounded text-emerald-700">
                                        <strong>Solusi:</strong> {{ $t->resolution_notes }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $t->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst($t->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($t->status !== 'resolved')
                                    <button wire:click="startResolve({{ $t->id }})" class="rounded bg-[var(--erp-primary)]/10 text-[var(--erp-primary)] px-3 py-1 text-xs font-semibold hover:bg-[var(--erp-primary)]/20">
                                        Selesaikan
                                    </button>
                                @else
                                    <span class="text-xs text-[var(--erp-text-secondary)]">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Tidak ada tiket bantuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    </div>
</div>
