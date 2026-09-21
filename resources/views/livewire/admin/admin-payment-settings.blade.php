<div class="mx-auto max-w-4xl space-y-8 px-4 py-8">
    <header>
        <h1 class="text-2xl font-bold text-[var(--erp-text)]">Pengaturan Pembayaran & Statistik</h1>
        <p class="mt-1 text-sm text-[var(--erp-text-muted)]">Rekening, QRIS, dan ringkasan komersial platform. Perubahan langsung berlaku.</p>
    </header>

    @if (session('success'))
        <div class="rounded-lg border border-[var(--erp-success)] bg-[var(--erp-success)]/10 p-4">
            <p class="text-sm text-[var(--erp-success)]">{{ session('success') }}</p>
        </div>
    @endif

    {{-- Statistik --}}
    <section class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4">
            <p class="text-xs text-[var(--erp-text-muted)]">Invoice Pending</p>
            <p class="mt-1 text-2xl font-bold text-[var(--erp-text)]">{{ $stats['invoices_pending'] }}</p>
        </div>
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4">
            <p class="text-xs text-[var(--erp-text-muted)]">Invoice Lunas</p>
            <p class="mt-1 text-2xl font-bold text-[var(--erp-text)]">{{ $stats['invoices_paid'] }}</p>
        </div>
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4">
            <p class="text-xs text-[var(--erp-text-muted)]">Pendapatan Total</p>
            <p class="mt-1 text-2xl font-bold text-[var(--erp-text)]">Rp {{ number_format($stats['revenue_total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4">
            <p class="text-xs text-[var(--erp-text-muted)]">Pendapatan Bulan Ini</p>
            <p class="mt-1 text-2xl font-bold text-[var(--erp-text)]">Rp {{ number_format($stats['revenue_this_month'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4">
            <p class="text-xs text-[var(--erp-text-muted)]">Membership Aktif</p>
            <p class="mt-1 text-2xl font-bold text-[var(--erp-text)]">{{ $stats['memberships_active'] }}</p>
        </div>
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-4">
            <p class="text-xs text-[var(--erp-text-muted)]">Expiry &lt;= 7 Hari</p>
            <p class="mt-1 text-2xl font-bold text-[var(--erp-text)]">{{ $stats['memberships_expiring_7d'] }}</p>
        </div>
    </section>

    {{-- Invoice lunas terbaru --}}
    <section>
        <h2 class="text-lg font-semibold text-[var(--erp-text)]">Pembayaran Terbaru</h2>
        @if ($recentPaid->count())
            <div class="mt-3 overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] text-left text-xs text-[var(--erp-text-muted)]">
                            <th class="p-3">Company</th>
                            <th class="p-3">Nominal</th>
                            <th class="p-3">Tanggal Lunas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentPaid as $inv)
                            <tr class="border-b border-[var(--erp-border)]/50">
                                <td class="p-3 text-sm text-[var(--erp-text)]">{{ $inv->company?->name ?? '-' }}</td>
                                <td class="p-3 text-sm text-[var(--erp-text)]">Rp {{ number_format((float) $inv->amount, 0, ',', '.') }}</td>
                                <td class="p-3 text-sm text-[var(--erp-text-muted)]">{{ $inv->paid_at?->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-2 text-sm text-[var(--erp-text-muted)]">Belum ada pembayaran lunas.</p>
        @endif
    </section>

    {{-- Form rekening --}}
    <section class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-6">
        <h2 class="text-lg font-semibold text-[var(--erp-text)]">Rekening Pembayaran</h2>
        <form wire:submit="save" class="mt-4 space-y-4">
            <label class="block">
                <span class="text-sm text-[var(--erp-text-muted)]">Pembayaran manual aktif</span>
                <select wire:model="paymentEnabled" class="mt-1 w-full rounded-md border border-[var(--erp-border)] bg-[var(--erp-surface)] p-2 text-sm">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </label>
            <label class="block">
                <span class="text-sm text-[var(--erp-text-muted)]">Nama Bank</span>
                <input wire:model="bankName" type="text" class="mt-1 w-full rounded-md border border-[var(--erp-border)] bg-[var(--erp-surface)] p-2 text-sm">
                @error('bankName') <span class="text-sm text-[var(--erp-error)]">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm text-[var(--erp-text-muted)]">Nomor Rekening</span>
                <input wire:model="bankAccount" type="text" class="mt-1 w-full rounded-md border border-[var(--erp-border)] bg-[var(--erp-surface)] p-2 text-sm">
                @error('bankAccount') <span class="text-sm text-[var(--erp-error)]">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm text-[var(--erp-text-muted)]">Nama Pemilik Rekening</span>
                <input wire:model="bankHolder" type="text" class="mt-1 w-full rounded-md border border-[var(--erp-border)] bg-[var(--erp-surface)] p-2 text-sm">
                @error('bankHolder') <span class="text-sm text-[var(--erp-error)]">{{ $message }}</span> @enderror
            </label>

            {{-- QRIS --}}
            <div>
                <span class="text-sm text-[var(--erp-text-muted)]">QRIS (PNG/JPG, maks 2MB)</span>
                @if ($qrisCurrentPath)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ $qrisCurrentPath }}" alt="QRIS aktif" class="h-24 w-24 rounded border border-[var(--erp-border)]">
                        <button type="button" wire:click="removeQris" class="rounded-md border border-[var(--erp-error)] px-3 py-1.5 text-sm text-[var(--erp-error)]">Hapus QRIS</button>
                    </div>
                @endif
                <input wire:model="qrisUpload" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full text-sm text-[var(--erp-text-muted)]">
                @error('qrisUpload') <span class="text-sm text-[var(--erp-error)]">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="rounded-md bg-[var(--erp-primary)] px-4 py-2 text-sm font-semibold text-white">Simpan</button>
        </form>
    </section>
</div>
