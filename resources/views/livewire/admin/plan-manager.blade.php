<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">Paket & Aturan Kuota Langganan</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Atur harga, kuota token, grup WA, dan fitur kapabilitas per paket membership (D-05, D-52, D-53).
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">Paket</a>
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
                <h3 class="text-lg font-semibold text-[var(--erp-text)] mb-4">Edit Paket: {{ $name }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Harga (Rp/bulan)</label>
                        <input type="number" wire:model="price" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]">
                        @error('price') <span class="text-xs text-[var(--erp-error)]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Kuota Token Bulanan</label>
                        <input type="number" wire:model="monthlyTokenQuota" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]">
                        @error('monthlyTokenQuota') <span class="text-xs text-[var(--erp-error)]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-1">Maksimal Grup WA</label>
                        <input type="number" wire:model="maxWaGroups" class="w-full rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 py-2 text-sm text-[var(--erp-text)]">
                        @error('maxWaGroups') <span class="text-xs text-[var(--erp-error)]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-medium text-[var(--erp-text-secondary)] mb-2">Kapabilitas yang Terbuka (D-52)</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
                        @foreach ($availableCapabilities as $capKey => $capLabel)
                            <label class="flex items-center space-x-2 text-xs text-[var(--erp-text)] p-2 rounded border border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]/30">
                                <input type="checkbox" value="{{ $capKey }}" wire:model="features" class="rounded border-[var(--erp-border)]">
                                <span>{{ $capLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="save" class="rounded bg-[var(--erp-primary)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Simpan Perubahan</button>
                    <button wire:click="cancel" class="rounded border border-[var(--erp-border)] px-4 py-2 text-sm text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)]">Batal</button>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($plans as $p)
                <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start">
                            <h3 class="text-xl font-bold text-[var(--erp-text)]">{{ $p->name }}</h3>
                            <span class="rounded bg-[var(--erp-primary)]/10 px-2 py-0.5 text-xs font-medium text-[var(--erp-primary)]">{{ $p->slug }}</span>
                        </div>
                        <div class="mt-4">
                            <span class="text-2xl font-extrabold text-[var(--erp-text)]">Rp {{ number_format((float) $p->monthly_price, 0, ',', '.') }}</span>
                            <span class="text-xs text-[var(--erp-text-secondary)]">/bulan</span>
                        </div>
                        <ul class="mt-4 space-y-2 text-xs text-[var(--erp-text-secondary)] border-t border-[var(--erp-border)] pt-4">
                            <li class="flex items-center justify-between">
                                <span>Kuota Token:</span>
                                <span class="font-semibold text-[var(--erp-text)]">{{ number_format((int) $p->monthly_token_quota) }}</span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span>Maks Grup WA:</span>
                                <span class="font-semibold text-[var(--erp-text)]">{{ $p->max_wa_groups }}</span>
                            </li>
                            <li class="pt-2">
                                <span class="block mb-1 font-medium text-[var(--erp-text)]">Kapabilitas Terbuka:</span>
                                <div class="flex flex-wrap gap-1">
                                    @if(is_array($p->features))
                                        @foreach($p->features as $f)
                                            <span class="rounded bg-[var(--erp-surface-secondary)] px-1.5 py-0.5 text-[10px] text-[var(--erp-text)]">{{ $f }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-[10px] text-[var(--erp-text-secondary)]">-</span>
                                    @endif
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-6 pt-4 border-t border-[var(--erp-border)]">
                        <button wire:click="edit({{ $p->id }})" class="w-full rounded border border-[var(--erp-border)] py-1.5 text-xs font-medium text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)]">
                            Edit Pengaturan Paket
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-8 text-center text-sm text-[var(--erp-text-secondary)]">
                    Belum ada paket langganan terdaftar.
                </div>
            @endforelse
        </div>
    </div>
</div>
