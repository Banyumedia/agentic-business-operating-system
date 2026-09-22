<?php

namespace App\Livewire\Admin;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Services\Hermes\FleetMonitor;
use App\Services\Hermes\ProfileMirror;
use App\Services\Hermes\ProfileStatusRefresher;
use App\Services\HermesNodeClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pengelola node Hermes dan armada bot.
 *
 * Sebelum T-70 komponen ini hanya bisa **menyunting** node yang sudah ada:
 * `saveNode()` berhenti bila `editingNodeId` kosong, sehingga tabel
 * `hermes_nodes` yang kosong tidak punya cara diisi dari UI sama sekali. Itu
 * jalan buntu nyata - seluruh integrasi Hermes bergantung pada satu baris yang
 * tidak bisa dibuat, dan `bos:hermes-ping` hanya bisa melaporkan "belum ada node
 * terdaftar".
 */
class HermesNodeManager extends Component
{
    public $nodes;

    /** Profil bot tenant. */
    public $profiles;

    /**
     * Profil milik platform (bot dev dan bot CS kita) yang melayani **nol**
     * company. Dipisahkan karena mencampurnya dengan profil tenant membuat
     * daftar itu menyesatkan: yang satu menandakan pelanggan, yang lain tidak.
     */
    public $platformProfiles;

    public ?int $editingNodeId = null;

    public string $name = '';

    public string $apiUrl = '';

    /**
     * **Nama** rahasia node, bukan nilainya. Nilainya dipetakan di
     * `config/hermes.php` dari environment, sehingga basis data tetap bebas
     * kredensial (COMMERCIAL §Hermes Profile).
     */
    public string $apiSecretReference = '';

    /**
     * Alamat **dashboard API** Hermes, bukan bridge (D-72 butir 4).
     *
     * `apiUrl` di atas adalah bridge WhatsApp: loopback, tanpa autentikasi, satu port
     * per nomor. Control plane adalah proses lain di port lain yang **butuh token**,
     * dan token itu setara terminal di host Hermes - karena port yang sama juga
     * menyajikan tulis-berkas dan eksekusi perintah. Karena itu keduanya tidak boleh
     * berbagi kolom: satu salah isi berarti token dikirim ke port yang tidak
     * memintanya.
     *
     * Boleh kosong: node yang hanya menjalankan bridge tetap sah, dan itu keadaan
     * hari ini selama H-05 belum mendarat.
     */
    public string $controlUrl = '';

    public string $controlSecretReference = '';

    public int $maxCapacity = 100;

    public string $status = 'active';

    /** @var array<int, array{ok: bool, status: int|null, detail: string}> */
    public array $health = [];

    /**
     * Cermin profil node (T-83) dan keadaan kanalnya (T-84), per node.
     *
     * **Tidak** dimuat di `mount()`: kalau dimuat otomatis, setiap kunjungan halaman
     * menembak seluruh armada, dan pada node yang mati operator menunggu seluruh
     * timeout sebelum satu piksel pun tampil. Dimuat saat diminta, dan setiap hasil
     * membawa stempel waktunya sendiri supaya angka basi tidak menyamar sebagai baru.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $mirror = [];

    /** @var array<int, list<array<string, mixed>>> */
    public array $fleet = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->nodes = HermesNode::all();

        $all = HermesProfile::with(['owner', 'node', 'companies'])->latest()->get();

        $this->platformProfiles = $all->filter(
            static fn (HermesProfile $profile): bool => (bool) $profile->is_platform_provided,
        )->values();

        $this->profiles = $all->reject(
            static fn (HermesProfile $profile): bool => (bool) $profile->is_platform_provided,
        )->values();
    }

    /**
     * Memeriksa kesehatan setiap node tanpa mengirim pesan apa pun.
     *
     * Node yang tidak terjangkau dilaporkan **sebagai gagal**, bukan melempar
     * dan menjatuhkan halaman: laman monitoring yang mati justru menghilangkan
     * satu-satunya cara melihat bahwa ada node bermasalah.
     */
    public function checkHealth(): void
    {
        $client = app(HermesNodeClient::class);
        $health = [];

        foreach (HermesNode::all() as $node) {
            $health[(int) $node->id] = $client->ping(
                (string) $node->api_url,
                (string) $node->api_secret_reference,
            );
        }

        $this->health = $health;
    }

    /**
     * Menyegarkan status setiap profil dari bridge WhatsApp-nya.
     *
     * Bedanya dengan `checkHealth()`: yang itu memeriksa **node** (host) dan tidak
     * menulis apa pun; yang ini memeriksa **profil** (nomor) dan menyimpan
     * hasilnya, sehingga status yang terlihat di halaman ini tidak bisa berbohong
     * tentang nomor yang sudah lepas.
     *
     * Profil tanpa alamat bridge dihitung terpisah, bukan dilaporkan sebagai gagal:
     * profil yang belum ditempatkan pada node mana pun memang belum punya apa pun
     * untuk diperiksa.
     */
    public function refreshProfileStatus(): void
    {
        $refresher = app(ProfileStatusRefresher::class);
        $paired = 0;
        $notReady = 0;
        $withoutBridge = 0;

        foreach (HermesProfile::with('node')->get() as $profile) {
            $result = $refresher->refresh($profile);

            if ($result['address'] === '') {
                $withoutBridge++;

                continue;
            }

            $result['ok'] ? $paired++ : $notReady++;
        }

        $this->loadData();

        session()->flash('success', "Status profil disegarkan: {$paired} tersambung, {$notReady} belum siap, {$withoutBridge} tanpa alamat bridge.");
    }

    /**
     * Memuat cermin profil + keadaan kanal untuk setiap node yang punya control plane.
     *
     * Node tanpa control plane dilewati tanpa permintaan apa pun: alamat bridge bukan
     * penggantinya, dan menembaknya hanya menghasilkan galat yang menyesatkan.
     *
     * Kegagalan **tidak** menjatuhkan halaman - `ProfileMirror` dan `FleetMonitor`
     * mengembalikan sebabnya sebagai data, dan sebab itu yang dirender. Keadaan hari
     * ini adalah `belum_berwenang` (H-05 belum terpasang di Hermes), dan itu harus
     * terbaca berbeda dari "node mati".
     */
    public function loadMirror(bool $fresh = false): void
    {
        $mirror = app(ProfileMirror::class);
        $monitor = app(FleetMonitor::class);

        $hasilCermin = [];
        $hasilArmada = [];

        foreach (HermesNode::query()->whereNotNull('control_url')->get() as $node) {
            $hasilCermin[(int) $node->id] = $mirror->forNode($node, $fresh);
            $hasilArmada[(int) $node->id] = $monitor->forNode($node);
        }

        $this->mirror = $hasilCermin;
        $this->fleet = $hasilArmada;
    }

    public function refreshMirror(): void
    {
        $this->loadMirror(fresh: true);
    }

    public function editNode(int $id): void
    {
        $node = HermesNode::findOrFail($id);
        $this->editingNodeId = $id;
        $this->name = $node->name;
        $this->apiUrl = $node->api_url;
        $this->apiSecretReference = (string) $node->api_secret_reference;
        $this->controlUrl = (string) ($node->control_url ?? '');
        $this->controlSecretReference = (string) ($node->control_secret_reference ?? '');
        $this->maxCapacity = (int) $node->max_capacity;
        $this->status = $node->status;
    }

    private function controlIsLoopback(): bool
    {
        return in_array(
            parse_url($this->controlUrl, PHP_URL_HOST),
            ['127.0.0.1', 'localhost', '::1', '[::1]'],
            true,
        );
    }

    public function cancelEdit(): void
    {
        $this->editingNodeId = null;
        $this->resetForm();
    }

    public function saveNode(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:191'],
            // `url:http,https` sejalan dengan `HermesNodeClient::endpoint()` yang
            // menolak alamat tanpa skema. Menolaknya di sini mencegah baris yang
            // tampak sah tapi mati saat dipakai.
            'apiUrl' => ['required', 'url:http,https', 'max:191'],
            // Tanpa referensi rahasia node tidak akan pernah bisa dipanggil, dan
            // kegagalannya baru terlihat jauh di belakang saat mengirim.
            'apiSecretReference' => ['required', 'string', 'max:191'],
            // Control plane opsional - node yang hanya menjalankan bridge tetap sah.
            'controlUrl' => ['nullable', 'url:http,https', 'max:191'],
            'controlSecretReference' => ['nullable', 'string', 'max:191'],
            'maxCapacity' => ['required', 'integer', 'min:1'],
            // `draining` (T-105): node yang sedang dikosongkan lewat
            // `POST /api/gateway/drain` - berhenti menerima penempatan baru
            // (`NodePlacement`) tetapi tetap melayani profil yang sudah ada
            // (`HermesNodeClient`). Bedanya dengan `maintenance`/`down` yang
            // menolak pengiriman sama sekali.
            'status' => ['required', 'in:active,maintenance,down,draining'],
        ]);

        // Aturan yang sama ditegakkan `HermesControlPlaneClient`: control plane tanpa
        // token hanya sah pada loopback. Menolaknya di sini bukan duplikasi yang
        // mubazir - tanpa ini operator menyimpan baris yang selalu gagal saat dipakai,
        // dan bisa menyangka control plane publik tanpa token itu keadaan yang wajar.
        if ($this->controlUrl !== '' && trim($this->controlSecretReference) === '' && ! $this->controlIsLoopback()) {
            $this->addError('controlSecretReference', 'Control plane non-loopback wajib punya referensi rahasia.');

            return;
        }

        $attributes = [
            'name' => $this->name,
            'api_url' => $this->apiUrl,
            'api_secret_reference' => $this->apiSecretReference,
            'control_url' => $this->controlUrl !== '' ? $this->controlUrl : null,
            'control_secret_reference' => trim($this->controlSecretReference) !== '' ? $this->controlSecretReference : null,
            'max_capacity' => $this->maxCapacity,
            'status' => $this->status,
        ];

        if ($this->editingNodeId) {
            $node = HermesNode::findOrFail($this->editingNodeId);
            $node->update($attributes);
            $this->editingNodeId = null;
            session()->flash('success', "Node {$node->name} berhasil diperbarui.");
        } else {
            $node = HermesNode::create($attributes + ['active_profiles' => 0]);
            session()->flash('success', "Node {$node->name} berhasil didaftarkan.");
        }

        $this->resetForm();
        $this->loadData();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->apiUrl = '';
        $this->apiSecretReference = '';
        $this->controlUrl = '';
        $this->controlSecretReference = '';
        $this->maxCapacity = 100;
        $this->status = 'active';
    }

    public function render(): View
    {
        return view('livewire.admin.hermes-node-manager')
            ->layout('components.layouts.module', ['title' => 'Hermes Nodes & Fleet  Super Admin']);
    }
}
