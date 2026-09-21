<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">AI Model Pricing & Multipliers</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Atur pengali BOS Token per model AI untuk menjaga margin profit platform (D-05, D-28).
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Paket</a>
                <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tiket</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        @if (session('success'))
            <div class="rounded-lg border border-[var(--erp-success)] bg-[var(--erp-success)]/10 p-4">
                <p class="text-sm text-[var(--erp-success)]">{{ session('success') }}</p>
            </div>
        @endif

        @if ($editingId)
            <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--erp-text)] mb-4">Edit Multiplier Model</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Input Multiplier</label>
                        <input type="number" step="0.1" wire:model="inputMultiplier" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]">
                        @error('inputMultiplier') <span class="text-xs text-[var(--erp-error)]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Output Multiplier</label>
                        <input type="number" step="0.1" wire:model="outputMultiplier" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]">
                        @error('outputMultiplier') <span class="text-xs text-[var(--erp-error)]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Status Aktif</label>
                        <select wire:model="isActive" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]">
                            <option value="1">Aktif</option>
                            <option value="0">Non-aktif</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button wire:click="save" class="rounded bg-[var(--erp-primary)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Simpan Perubahan</button>
                    <button wire:click="cancel" class="rounded border border-[var(--erp-border)] px-4 py-2 text-sm text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)]">Batal</button>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Nama Model</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Input Multiplier</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Output Multiplier</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Status</th>
                        <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pricings as $p)
                        <tr class="border-b border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)]/50">
                            <td class="px-6 py-4 font-mono text-sm font-medium text-[var(--erp-text)]">{{ $p->model_name }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--erp-text)]">{{ $p->input_multiplier }}x</td>
                            <td class="px-6 py-4 text-sm text-[var(--erp-text)]">{{ $p->output_multiplier }}x</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $p->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $p->is_active ? 'Aktif' : 'Non-aktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm flex gap-2">
                                <button wire:click="edit({{ $p->id }})" class="text-[var(--erp-text-link)] hover:underline">Edit</button>
                                <button wire:click="toggleActive({{ $p->id }})" class="text-xs text-[var(--erp-text-secondary)] hover:underline">
                                    {{ $p->is_active ? 'Non-aktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada model AI terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
