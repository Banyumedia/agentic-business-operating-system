<div class="min-h-screen bg-gradient-to-br from-[var(--erp-surface)] to-[var(--erp-surface-secondary)]">
    <div class="border-b border-[var(--erp-border)] bg-[var(--erp-surface)]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-[var(--erp-text)]">Pusat Dokumentasi &amp; Arsitektur Sistem</h1>
                <p class="mt-2 text-[var(--erp-text-secondary)]">
                    Panduan teknis, tata kelola keamanan Asisten AI Hermes WhatsApp, dan SOP operasional platform.
                </p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tenant</a>
                <a href="{{ route('admin.invoices') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Invoice</a>
                <a href="{{ route('admin.payment-settings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Pembayaran</a>
                <a href="{{ route('admin.ai-pricings') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">AI Pricing</a>
                <a href="{{ route('admin.plans') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Paket</a>
                <a href="{{ route('admin.support-tickets') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Tiket</a>
                <a href="{{ route('admin.hermes-nodes') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Hermes Nodes</a>
                <a href="{{ route('admin.client-logs') }}" class="px-3 py-1.5 rounded border border-[var(--erp-border)] hover:bg-[var(--erp-surface-secondary)] text-[var(--erp-text)]">Log Klien</a>
                <a href="{{ route('admin.docs') }}" class="px-3 py-1.5 rounded bg-[var(--erp-primary)] text-white">Dokumentasi</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {{-- Sidebar Navigasi Dokumen --}}
            <div class="space-y-2">
                <button
                    wire:click="$set('activeDoc', 'architecture')"
                    class="w-full text-left px-4 py-3 rounded-lg text-sm font-semibold transition {{ $activeDoc === 'architecture' ? 'bg-[var(--erp-surface)] border-l-4 border-[var(--erp-primary)] shadow text-[var(--erp-primary)]' : 'text-[var(--erp-text-secondary)] hover:bg-[var(--erp-surface)]' }}"
                >
                     Arsitektur Dua Nomor WhatsApp
                </button>
                <button
                    wire:click="$set('activeDoc', 'guardrails')"
                    class="w-full text-left px-4 py-3 rounded-lg text-sm font-semibold transition {{ $activeDoc === 'guardrails' ? 'bg-[var(--erp-surface)] border-l-4 border-[var(--erp-primary)] shadow text-[var(--erp-primary)]' : 'text-[var(--erp-text-secondary)] hover:bg-[var(--erp-surface)]' }}"
                >
                     Pagar Keamanan & Tool Scoping
                </button>
                <button
                    wire:click="$set('activeDoc', 'nlu')"
                    class="w-full text-left px-4 py-3 rounded-lg text-sm font-semibold transition {{ $activeDoc === 'nlu' ? 'bg-[var(--erp-surface)] border-l-4 border-[var(--erp-primary)] shadow text-[var(--erp-primary)]' : 'text-[var(--erp-text-secondary)] hover:bg-[var(--erp-surface)]' }}"
                >
                    💬 Perintah Bahasa Manusia (NLU)
                </button>
                <button
                    wire:click="$set('activeDoc', 'setup')"
                    class="w-full text-left px-4 py-3 rounded-lg text-sm font-semibold transition {{ $activeDoc === 'setup' ? 'bg-[var(--erp-surface)] border-l-4 border-[var(--erp-primary)] shadow text-[var(--erp-primary)]' : 'text-[var(--erp-text-secondary)] hover:bg-[var(--erp-surface)]' }}"
                >
                     Panduan Buat Profil Hermes
                </button>
            </div>

            {{-- Isi Konten Dokumen --}}
            <div class="lg:col-span-3 rounded-xl border border-[var(--erp-border)] bg-[var(--erp-surface)] p-8 shadow-sm">
                @if ($activeDoc === 'architecture')
                    <div class="space-y-6">
                        <div class="border-b border-[var(--erp-border)] pb-4">
                            <h2 class="text-2xl font-bold text-[var(--erp-text)]">Arsitektur Dua Nomor WhatsApp (D-37, D-53)</h2>
                            <p class="text-sm text-[var(--erp-text-secondary)] mt-1">Pemisahan fisik nomor untuk keamanan operasional dan kenyamanan tim.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                            <div class="p-5 rounded-lg border border-indigo-200 bg-indigo-50/30 space-y-3">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">Nomor 1: Operasional Internal (`primary`)</span>
                                <h3 class="font-bold text-base text-[var(--erp-text)]">Bot ERP Tim & Owner</h3>
                                <ul class="text-xs space-y-2 text-[var(--erp-text-secondary)]">
                                    <li> <strong>Japri:</strong> Hanya merespons nomor HP Owner yang terdaftar. Nomor tak dikenal diabaikan (fail-closed).</li>
                                    <li> <strong>Grup:</strong> Masuk grup tim internal (Kasir/Gudang/Keuangan). Mode default hanya menjawab jika di-tag (`@bot`).</li>
                                    <li> <strong>Kewenangan:</strong> Otak konsultan bisnis bebas, tangan dibatasi API ERP. Zero OS tools (tanpa terminal/shell/file).</li>
                                </ul>
                            </div>

                            <div class="p-5 rounded-lg border border-emerald-200 bg-emerald-50/30 space-y-3">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Nomor 2: CS Publik (`addon`)</span>
                                <h3 class="font-bold text-base text-[var(--erp-text)]">Bot Layanan Pelanggan Toko</h3>
                                <ul class="text-xs space-y-2 text-[var(--erp-text-secondary)]">
                                    <li> <strong>Japri:</strong> Melayani calon pelanggan dan masyarakat umum secara ramah 24/7.</li>
                                    <li> <strong>Grup:</strong> Ditolak masuk grup (khusus 1-on-1 customer service).</li>
                                    <li> <strong>Kewenangan:</strong> Read-only total (hanya cek katalog & status order sendiri). Dilarang mutasi data atau ubah setting.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @elseif ($activeDoc === 'guardrails')
                    <div class="space-y-6">
                        <div class="border-b border-[var(--erp-border)] pb-4">
                            <h2 class="text-2xl font-bold text-[var(--erp-text)]">Pagar Keamanan & Tool Scoping (Zero-OS)</h2>
                            <p class="text-sm text-[var(--erp-text-secondary)] mt-1">Pencegahan jailbreak, kebocoran server, dan manipulasi transaksi.</p>
                        </div>

                        <div class="space-y-4 text-sm text-[var(--erp-text)]">
                            <div class="p-4 rounded-lg bg-[var(--erp-surface-secondary)] space-y-2">
                                <h4 class="font-bold text-red-600"> 1. Isolasi Zero OS Tools (Platform Hard Lock)</h4>
                                <p class="text-xs text-[var(--erp-text-secondary)]">
                                    Kedua bot (Primary & CS) <strong>tidak pernah dibekali</strong> akses ke alat eksekusi server: <code>terminal</code>, <code>shell</code>, <code>write_file</code>, <code>git</code>, atau <code>process</code>. Upaya manipulasi sistem di chat tidak dapat dieksekusi.
                                </p>
                            </div>

                            <div class="p-4 rounded-lg bg-[var(--erp-surface-secondary)] space-y-2">
                                <h4 class="font-bold text-amber-600"> 2. Middleware Runtime Enforcement (EnforceBotToolScoping)</h4>
                                <p class="text-xs text-[var(--erp-text-secondary)]">
                                    Setiap panggilan webhook dari bot divalidasi di layer HTTP middleware. Profil <code>addon</code> (CS) yang mencoba mengirim payload pembaruan pengaturan, pembuatan pesanan, atau aksi destruktif langsung ditolak dengan kode <strong>403 Forbidden</strong>.
                                </p>
                            </div>

                            <div class="p-4 rounded-lg bg-[var(--erp-surface-secondary)] space-y-2">
                                <h4 class="font-bold text-blue-600"> 3. Batas Diskon Kasir Otomatis & Human-in-the-Loop</h4>
                                <p class="text-xs text-[var(--erp-text-secondary)]">
                                    Diskon di atas persentase yang ditentukan Owner di web UI otomatis ditahan dan dialihkan menjadi tiket persetujuan (<code>approval_tickets</code>). Bot dilarang menyetujui transaksi tersebut secara mandiri.
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif ($activeDoc === 'nlu')
                    <div class="space-y-6">
                        <div class="border-b border-[var(--erp-border)] pb-4">
                            <h2 class="text-2xl font-bold text-[var(--erp-text)]">Perintah Bahasa Manusia (NLU Intent Router)</h2>
                            <p class="text-sm text-[var(--erp-text-secondary)] mt-1">Daftar pola chat natural yang otomatis dikenali sistem dari WhatsApp.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                                        <th class="p-3 font-bold">Intent</th>
                                        <th class="p-3 font-bold">Contoh Pesan WhatsApp</th>
                                        <th class="p-3 font-bold">Aksi Backend yang Dijalankan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--erp-border)]">
                                    <tr>
                                        <td class="p-3 font-mono font-bold text-indigo-600">approval</td>
                                        <td class="p-3 italic">"ACC tiket #42" atau "Tolak diskon meja 3"</td>
                                        <td class="p-3">Memperbarui status tiket di <code>approval_tickets</code> menjadi approved/rejected.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-mono font-bold text-amber-600">reminder</td>
                                        <td class="p-3 italic">"Ingatkan besok jam 08:30 cek stok obat"</td>
                                        <td class="p-3">Membuat jadwal pengingat di <code>ai_reminders</code> dan mengirimkan alert saat tiba waktunya.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-mono font-bold text-emerald-600">setup</td>
                                        <td class="p-3 italic">"Ubah batas diskon kasir jadi 15%"</td>
                                        <td class="p-3">Memperbarui konfigurasi tenant di <code>module_settings</code> (khusus chat dari nomor Owner).</td>
                                    </tr>
                                    <tr>
                                        <td class="p-3 font-mono font-bold text-blue-600">report</td>
                                        <td class="p-3 italic">"Berapa total omzet hari ini?" atau "Cek sisa stok barang"</td>
                                        <td class="p-3">Mengambil metrik ringkasan dari buku kas atau stok lalu menyusun laporan ringkas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($activeDoc === 'setup')
                    <div class="space-y-6">
                        <div class="border-b border-[var(--erp-border)] pb-4">
                            <h2 class="text-2xl font-bold text-[var(--erp-text)]">Panduan Provisioning Profil Hermes (Node Cluster)</h2>
                            <p class="text-sm text-[var(--erp-text-secondary)] mt-1">Langkah teknis setup profil Hermes baru di terminal server.</p>
                        </div>

                        <div class="space-y-4 text-xs font-mono">
                            <div class="p-4 rounded-lg bg-gray-900 text-gray-100 space-y-2">
                                <p class="text-gray-400"># 1. Buat profil terisolasi via Hermes CLI</p>
                                <p>hermes profile create bos-wa-primary</p>
                                <p>hermes profile create bos-wa-cs</p>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-900 text-gray-100 space-y-2">
                                <p class="text-gray-400"># 2. Hubungkan gateway WhatsApp Baileys di config profil</p>
                                <p>WHATSAPP_ENABLED=true</p>
                                <p>BOS_API_URL=http://127.0.0.1:8010/api/bot/tenant</p>
                                <p>BOS_WEBHOOK_SECRET=token_secret_dari_tabel_hermes_profiles</p>
                            </div>

                            <div class="p-4 rounded-lg bg-gray-900 text-gray-100 space-y-2">
                                <p class="text-gray-400"># 3. Jalankan Gateway Service di background (PM2)</p>
                                <p>hermes gateway --profile bos-wa-primary</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
