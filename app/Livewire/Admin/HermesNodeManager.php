<?php

namespace App\Livewire\Admin;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HermesNodeManager extends Component
{
    public $nodes;

    public $profiles;

    public ?int $editingNodeId = null;

    public string $name = '';

    public string $apiUrl = '';

    public int $maxCapacity = 100;

    public string $status = 'active';

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->nodes = HermesNode::all();
        $this->profiles = HermesProfile::with(['owner', 'node', 'companies'])->latest()->get();
    }

    public function editNode(int $id): void
    {
        $node = HermesNode::findOrFail($id);
        $this->editingNodeId = $id;
        $this->name = $node->name;
        $this->apiUrl = $node->api_url;
        $this->maxCapacity = (int) $node->max_capacity;
        $this->status = $node->status;
    }

    public function cancelEdit(): void
    {
        $this->editingNodeId = null;
    }

    public function saveNode(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'apiUrl' => ['required', 'url', 'max:191'],
            'maxCapacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,maintenance,down'],
        ]);

        if ($this->editingNodeId) {
            $node = HermesNode::findOrFail($this->editingNodeId);
            $node->update([
                'name' => $this->name,
                'api_url' => $this->apiUrl,
                'max_capacity' => $this->maxCapacity,
                'status' => $this->status,
            ]);

            $this->editingNodeId = null;
            session()->flash('success', "Node {$node->name} berhasil diperbarui.");
            $this->loadData();
        }
    }

    public function render(): View
    {
        return view('livewire.admin.hermes-node-manager')
            ->layout('components.layouts.module', ['title' => 'Hermes Nodes & Fleet  Super Admin']);
    }
}
