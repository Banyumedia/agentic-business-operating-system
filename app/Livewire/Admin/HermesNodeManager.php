<?php

namespace App\Livewire\Admin;

use App\Models\HermesNode;
use App\Models\HermesProfile;
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

    public int $maxCapacity = 100;

    public string $status = 'active';

    /** @var array<int, array{ok: bool, status: int|null, detail: string}> */
    public array $health = [];

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

    public function editNode(int $id): void
    {
        $node = HermesNode::findOrFail($id);
        $this->editingNodeId = $id;
        $this->name = $node->name;
        $this->apiUrl = $node->api_url;
        $this->apiSecretReference = (string) $node->api_secret_reference;
        $this->maxCapacity = (int) $node->max_capacity;
        $this->status = $node->status;
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
            'maxCapacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,maintenance,down'],
        ]);

        $attributes = [
            'name' => $this->name,
            'api_url' => $this->apiUrl,
            'api_secret_reference' => $this->apiSecretReference,
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
        $this->maxCapacity = 100;
        $this->status = 'active';
    }

    public function render(): View
    {
        return view('livewire.admin.hermes-node-manager')
            ->layout('components.layouts.module', ['title' => 'Hermes Nodes & Fleet  Super Admin']);
    }
}
