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

        {{-- Pendaftaran / penyuntingan node. Sebelum T-70 form ini tidak ada,
             sehingga tabel hermes_nodes yang kosong tidak bisa diisi dari UI. --}}
        <div class="rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)] p-5">
            <h2 class="text-xl font-bold text-[var(--erp-text)] mb-1">
                {{ $editingNodeId ? 'Sunting Node' : 'Daftarkan Node Baru' }}
            </h2>
            <p class="text-sm text-[var(--erp-text-secondary)] mb-4">
                Referensi rahasia adalah <strong>nama</strong> rahasianya, bukan nilainya. Nilainya dipetakan di konfigurasi dari environment, sehingga basis data tetap bebas kredensial.
            </p>

            <form wire:submit="saveNode" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="node-name" class="block text-sm font-medium text-[var(--erp-text)]">Nama Node</label>
                    <input id="node-name" type="text" wire:model="name" class="mt-1 w-full min-h-11 rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 text-[var(--erp-text)]">
                    @error('name') <p class="mt-1 text-sm text-[var(--erp-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="node-url" class="block text-sm font-medium text-[var(--erp-text)]">Alamat API</label>
                    <input id="node-url" type="text" wire:model="apiUrl" placeholder="http://127.0.0.1:8642" class="mt-1 w-full min-h-11 rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 font-mono text-sm text-[var(--erp-text)]">
                    @error('apiUrl') <p class="mt-1 text-sm text-[var(--erp-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="node-secret-ref" class="block text-sm font-medium text-[var(--erp-text)]">Referensi Rahasia</label>
                    <input id="node-secret-ref" type="text" wire:model="apiSecretReference" placeholder="node_lokal" class="mt-1 w-full min-h-11 rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 font-mono text-sm text-[var(--erp-text)]">
                    @error('apiSecretReference') <p class="mt-1 text-sm text-[var(--erp-danger)]">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="node-capacity" class="block text-sm font-medium text-[var(--erp-text)]">Kapasitas Profil</label>
                        <input id="node-capacity" type="number" min="1" wire:model="maxCapacity" class="mt-1 w-full min-h-11 rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 text-[var(--erp-text)]">
                        @error('maxCapacity') <p class="mt-1 text-sm text-[var(--erp-danger)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="node-status" class="block text-sm font-medium text-[var(--erp-text)]">Status</label>
                        <select id="node-status" wire:model="status" class="mt-1 w-full min-h-11 rounded border border-[var(--erp-border)] bg-[var(--erp-surface)] px-3 text-[var(--erp-text)]">
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="down">Down</option>
                        </select>
                        @error('status') <p class="mt-1 text-sm text-[var(--erp-danger)]">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <button type="submit" class="min-h-11 rounded bg-[var(--erp-primary)] px-4 font-semibold text-white hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        {{ $editingNodeId ? 'Simpan Perubahan' : 'Daftarkan Node' }}
                    </button>
                    @if ($editingNodeId)
                        <button type="button" wire:click="cancelEdit" class="min-h-11 rounded border border-[var(--erp-border)] px-4 text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            Batal
                        </button>
                    @endif
                    <button type="button" wire:click="checkHealth" class="min-h-11 rounded border border-[var(--erp-border)] px-4 text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        Periksa Kesehatan Node
                    </button>
                    {{-- Status profil diturunkan dari bridge, bukan diketik tangan:
                         status yang diketik bisa berbohong tentang nomor yang sudah lepas. --}}
                    <button type="button" wire:click="refreshProfileStatus" class="min-h-11 rounded border border-[var(--erp-border)] px-4 text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                        Segarkan Status Profil
                    </button>
                </div>
            </form>
        </div>

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

                        @if (isset($health[$node->id]))
                            <p class="mt-2 text-xs {{ $health[$node->id]['ok'] ? 'text-[var(--erp-success)]' : 'text-[var(--erp-danger)]' }}">
                                {{ $health[$node->id]['ok'] ? 'Terjangkau' : 'Tidak terjangkau' }} — {{ $health[$node->id]['detail'] }}
                            </p>
                        @endif

                        <button type="button" wire:click="editNode({{ $node->id }})" class="mt-3 min-h-11 rounded border border-[var(--erp-border)] px-3 text-sm text-[var(--erp-text)] hover:bg-[var(--erp-surface-secondary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)]">
                            Sunting
                        </button>
                        
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

        {{-- Profil milik platform: bot dev dan bot CS kita. Melayani nol company,
             jadi dipisahkan supaya daftar tenant tidak menyesatkan. --}}
        <div>
            <h2 class="text-xl font-bold text-[var(--erp-text)] mb-1">Bot Milik Platform</h2>
            <p class="text-sm text-[var(--erp-text-secondary)] mb-4">Tidak melayani usaha mana pun. Dipakai untuk operasional internal dan layanan pelanggan platform.</p>
            <div class="overflow-x-auto rounded-lg border border-[var(--erp-border)] bg-[var(--erp-surface)]">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[var(--erp-border)] bg-[var(--erp-surface-secondary)]">
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Label</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Tipe</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Node</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Bridge</th>
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($platformProfiles as $prof)
                            <tr class="border-b border-[var(--erp-border)]">
                                <td class="px-6 py-4 text-sm font-semibold text-[var(--erp-text)]">{{ $prof->label ?? 'Tanpa label' }}</td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">{{ strtoupper($prof->type) }}</td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">{{ $prof->node->name ?? 'Belum ditempatkan' }}</td>
                                <td class="px-6 py-4 font-mono text-xs text-[var(--erp-text-secondary)]">{{ $prof->api_url ?: ($prof->node->api_url ?? '-') }}</td>
                                <td class="px-6 py-4 text-sm text-[var(--erp-text)]">{{ ucfirst($prof->status ?? 'ready') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada bot milik platform yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                            <th class="px-6 py-3 text-sm font-semibold text-[var(--erp-text)]">Bridge</th>
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
                                    {{ $prof->node->name ?? 'Belum ditempatkan' }}
                                </td>
                                {{-- Satu bridge = satu nomor = satu port, jadi alamatnya milik
                                     profil. Kosong berarti ia memakai alamat node-nya. --}}
                                <td class="px-6 py-4 font-mono text-xs text-[var(--erp-text-secondary)]">
                                    {{ $prof->api_url ?: ($prof->node->api_url ?? '-') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @php
                                        $siap = in_array((string) $prof->status, (array) config('hermes.delivery.ready_statuses', ['paired']), true);
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $siap ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ ucfirst($prof->status ?? 'ready') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-[var(--erp-text-secondary)]">
                                    {{ $prof->last_ping_at ? $prof->last_ping_at->diffForHumans() : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-[var(--erp-text-secondary)]">Belum ada profil asisten yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
