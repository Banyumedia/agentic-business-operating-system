<?php

namespace App\Livewire\Admin;

use App\Models\AiModelPricing;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AiPricingManager extends Component
{
    public $pricings;

    public $editingId = null;

    public $inputMultiplier = 1.0;

    public $outputMultiplier = 3.0;

    public $isActive = true;

    public function mount(): void
    {
        $this->loadPricings();
    }

    public function loadPricings(): void
    {
        $this->pricings = AiModelPricing::orderBy('model_name')->get();
    }

    public function edit(int $id): void
    {
        $pricing = AiModelPricing::findOrFail($id);
        $this->editingId = $id;
        $this->inputMultiplier = (float) $pricing->input_multiplier;
        $this->outputMultiplier = (float) $pricing->output_multiplier;
        $this->isActive = (bool) $pricing->is_active;
    }

    public function cancel(): void
    {
        $this->editingId = null;
    }

    public function save(): void
    {
        $this->validate([
            'inputMultiplier' => ['required', 'numeric', 'min:0'],
            'outputMultiplier' => ['required', 'numeric', 'min:0'],
            'isActive' => ['required', 'boolean'],
        ]);

        if ($this->editingId) {
            $pricing = AiModelPricing::findOrFail($this->editingId);
            $pricing->update([
                'input_multiplier' => $this->inputMultiplier,
                'output_multiplier' => $this->outputMultiplier,
                'is_active' => $this->isActive,
            ]);
            $this->editingId = null;
            session()->flash('success', "Tarif {$pricing->model_name} berhasil diperbarui.");
            $this->loadPricings();
        }
    }

    public function toggleActive(int $id): void
    {
        $pricing = AiModelPricing::findOrFail($id);
        $pricing->update(['is_active' => ! $pricing->is_active]);
        $this->loadPricings();
    }

    public function render(): View
    {
        return view('livewire.admin.ai-pricing-manager')
            ->layout('components.layouts.module', ['title' => 'AI Pricing  Super Admin']);
    }
}
