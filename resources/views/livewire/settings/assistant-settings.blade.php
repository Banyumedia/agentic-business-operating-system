<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--erp-border)] pb-4">
        <div>
            <h2 class="text-xl font-bold text-[var(--erp-text-primary)]">Karyawan AI (Hermes Control Center)</h2>
            <p class="mt-1 text-sm text-[var(--erp-text-secondary)]">
                Kendali asisten cerdas WhatsApp untuk tim internal dan layanan pelanggan.
            </p>
        </div>

        <div class="flex gap-2">
            <button
                type="button"
                wire:click="$set('activeSubTab', 'sop')"
                class="px-4 py-2 text-sm font-semibold rounded-lg transition {{ $activeSubTab === 'sop' ? 'bg-[var(--erp-accent)] text-[var(--erp-text-inverse)]' : 'border border-[var(--erp-border)] text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-hover)]' }}"
            >
                Aturan SOP (Markdown)
            </button>
            <button
                type="button"
                wire:click="$set('activeSubTab', 'groups')"
                class="px-4 py-2 text-sm font-semibold rounded-lg transition {{ $activeSubTab === 'groups' ? 'bg-[var(--erp-accent)] text-[var(--erp-text-inverse)]' : 'border border-[var(--erp-border)] text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-hover)]' }}"
            >
                WhatsApp & Grup Tim
            </button>
        </div>
    </div>

    @if ($notice)
        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-success)] bg-[var(--erp-success)]/10 p-4 text-sm text-[var(--erp-success)]" role="status">
            {{ $notice }}
        </div>
    @endif

    @if ($failure)
        <div class="rounded-[var(--erp-radius-md)] border border-[var(--erp-danger)] bg-[var(--erp-danger)]/10 p-4 text-sm text-[var(--erp-danger)]" role="alert">
            {{ $failure }}
        </div>
    @endif

    {{-- SUB-TAB 1: SOP MARKDOWN & GUARDRAIL FORM --}}
    @if ($activeSubTab === 'sop')
        <form wire:submit="saveSop" class="space-y-6">
            <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 space-y-6">
                <div class="flex items-center justify-between border-b border-[var(--erp-border)] pb-4">
                    <div>
                        <h3 class="font-bold text-[var(--erp-text-primary)]">Pedoman Kerja Asisten Bisnis</h3>
                        <p class="text-xs text-[var(--erp-text-secondary)]">Tulis instruksi dalam bahasa manusia sehari-hari (format Markdown didukung).</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-[var(--erp-accent-soft)] px-2.5 py-0.5 text-xs font-semibold text-[var(--erp-accent)]">
                        Asisten Internal
                    </span>
                </div>

                <div>
                    <label for="sopMarkdown" class="block text-sm font-semibold text-[var(--erp-text-primary)] mb-2">
                        Dokumen SOP & Karakter Kerja
                    </label>
                    <textarea
                        id="sopMarkdown"
                        wire:model="sopMarkdown"
                        rows="8"
                        @disabled(! $isOwner)
                        class="w-full font-mono text-sm rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] p-3 text-[var(--erp-text-primary)] focus:border-[var(--erp-focus)] focus:outline-none focus:ring-1 focus:ring-[var(--erp-focus)]"
                        placeholder="Tulis SOP dalam format Markdown..."
                    ></textarea>
                    @error('sopMarkdown') <span class="text-xs text-[var(--erp-danger)] mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-[var(--erp-border)]">
                    <div>
                        <label for="maxDiscountPercent" class="block text-xs font-semibold text-[var(--erp-text-primary)] mb-1">
                            Batas Diskon Maksimal Kasir (%)
                        </label>
                        <input
                            type="number"
                            id="maxDiscountPercent"
                            wire:model="maxDiscountPercent"
                            min="0"
                            max="100"
                            @disabled(! $isOwner)
                            class="w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] px-3 py-2 text-sm text-[var(--erp-text-primary)]"
                        />
                        <p class="mt-1 text-[11px] text-[var(--erp-text-secondary)]">Jika kasir meminta lebih, bot wajib membuat tiket approval.</p>
                        @error('maxDiscountPercent') <span class="text-xs text-[var(--erp-danger)] mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="operatingHours" class="block text-xs font-semibold text-[var(--erp-text-primary)] mb-1">
                            Jam Operasional Usaha
                        </label>
                        <input
                            type="text"
                            id="operatingHours"
                            wire:model="operatingHours"
                            @disabled(! $isOwner)
                            class="w-full rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)] px-3 py-2 text-sm text-[var(--erp-text-primary)]"
                            placeholder="08:00 - 20:00"
                        />
                        <p class="mt-1 text-[11px] text-[var(--erp-text-secondary)]">Di luar jam ini bot memberi respons toko tutup.</p>
                        @error('operatingHours') <span class="text-xs text-[var(--erp-danger)] mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[var(--erp-text-primary)] mb-1">
                            Interaksi di Grup Tim Internal
                        </label>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="groupTagOnly"
                                wire:model="groupTagOnly"
                                @disabled(! $isOwner)
                                class="h-4 w-4 rounded border-[var(--erp-border)] text-[var(--erp-accent)] focus:ring-[var(--erp-focus)]"
                            />
                            <label for="groupTagOnly" class="text-xs text-[var(--erp-text-primary)] font-medium">
                                Hanya jawab jika di-tag (@bot)
                            </label>
                        </div>
                        <p class="mt-1 text-[11px] text-[var(--erp-text-secondary)]">Mencegah bot ikut nimbrung saat tim sedang mengobrol biasa.</p>
                    </div>
                </div>
            </div>

            @if ($isOwner)
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center rounded-[var(--erp-radius-md)] bg-[var(--erp-accent)] px-6 py-2 text-sm font-semibold text-[var(--erp-text-inverse)] shadow transition hover:bg-[var(--erp-accent-hover)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]"
                    >
                        Simpan Aturan SOP
                    </button>
                </div>
            @else
                <p class="text-xs text-[var(--erp-text-secondary)] italic">Hanya akun pemilik usaha (Owner) yang dapat memperbarui dokumen SOP asisten AI.</p>
            @endif
        </form>
    @else
        {{-- SUB-TAB 2: WHATSAPP NUMBER & GROUPS --}}
        <div class="space-y-8">
            {{-- Status Dua Nomor WhatsApp (Internal vs CS) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nomor 1: Operasional Internal --}}
                <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-5 space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="inline-flex rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 text-xs font-semibold">Nomor 1: Operasional Internal</span>
                            <h3 class="text-base font-bold text-[var(--erp-text-primary)] mt-1">Bot ERP Tim & Owner</h3>
                        </div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $primaryProfile?->status === 'connected' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ ucfirst($primaryProfile?->status ?? 'Siap Pairing') }}
                        </span>
                    </div>
                    <p class="text-xs text-[var(--erp-text-secondary)]">
                        <strong>Aturan Keamanan:</strong> Japri hanya merespons Owner. Di grup hanya memproses transaksi staf & mencatat laporan.
                    </p>
                    <div class="pt-2 border-t border-[var(--erp-border)] flex justify-between items-center text-xs">
                        <span class="text-[var(--erp-text-secondary)]">Status Profil:</span>
                        <span class="font-mono font-medium text-[var(--erp-text-primary)]">{{ $primaryProfile?->label ?? 'Primary Assistant' }}</span>
                    </div>
                </div>

                {{-- Nomor 2: CS Publik --}}
                <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-5 space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="inline-flex rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">Nomor 2: CS Publik (Add-on)</span>
                            <h3 class="text-base font-bold text-[var(--erp-text-primary)] mt-1">Bot Layanan Pelanggan Toko</h3>
                        </div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $csProfile?->status === 'connected' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $csProfile ? ucfirst($csProfile->status) : 'Belum Aktif' }}
                        </span>
                    </div>
                    <p class="text-xs text-[var(--erp-text-secondary)]">
                        <strong>Aturan Keamanan:</strong> Menjawab japri publik dari calon pembeli. Pagar sistem terkunci anti-jailbreak (read-only katalog).
                    </p>
                    <div class="pt-2 border-t border-[var(--erp-border)] flex justify-between items-center text-xs">
                        <span class="text-[var(--erp-text-secondary)]">Status Add-on:</span>
                        <span class="font-mono font-medium text-[var(--erp-text-primary)]">{{ $csProfile ? 'Aktif' : 'Tersedia di Menu Tambahan' }}</span>
                    </div>
                </div>
            </div>

            {{-- Kuota Grup WhatsApp Aktif --}}
            <div class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-border)] bg-[var(--erp-bg-elevated)] p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[var(--erp-border)] pb-4">
                    <div>
                        <h3 class="font-bold text-[var(--erp-text-primary)]">Grup WhatsApp Tim Internal Terhubung</h3>
                        <p class="text-xs text-[var(--erp-text-secondary)]">Daftar grup WhatsApp kerja yang dimasuki oleh Bot Operasional.</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-[var(--erp-text-secondary)]">Kuota Grup Paket:</span>
                        <div class="text-sm font-bold text-[var(--erp-text-primary)]">{{ $usedGroups }} dari {{ $maxWaGroups }} grup terpakai</div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-[var(--erp-radius-md)] border border-[var(--erp-border)] bg-[var(--erp-bg-base)]">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-bg-secondary)]">
                                <th class="px-4 py-2.5 text-xs font-semibold text-[var(--erp-text-primary)]">ID Grup / Nama</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-[var(--erp-text-primary)]">Mode Interaksi</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-[var(--erp-text-primary)]">Terakhir Aktif</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-[var(--erp-text-primary)]">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activeGroups as $group)
                                <tr class="border-b border-[var(--erp-border)]">
                                    <td class="px-4 py-3 text-xs font-mono text-[var(--erp-text-primary)]">
                                        {{ $group->chat_id }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-[var(--erp-text-primary)]">
                                        <span class="inline-flex rounded bg-[var(--erp-bg-secondary)] px-2 py-0.5 font-medium">
                                            {{ $groupTagOnly ? 'Hanya saat di-tag (@bot)' : 'Merespons bebas' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono text-[var(--erp-text-secondary)]">
                                        {{ $group->updated_at ? $group->updated_at->diffForHumans() : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="inline-flex items-center rounded-full bg-green-100 text-green-800 px-2 py-0.5 font-semibold">
                                            Aktif Terhubung
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-xs text-[var(--erp-text-secondary)]">
                                        Belum ada grup WhatsApp tim yang terhubung. Masukkan bot ke grup kerja Anda (Grup Kasir/Gudang) untuk mulai berkolaborasi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
