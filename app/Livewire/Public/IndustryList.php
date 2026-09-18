<?php

namespace App\Livewire\Public;

use App\Models\BusinessPreset;
use Livewire\Component;

class IndustryList extends Component
{
    public function render()
    {
        return view('livewire.public.industry-list', [
            'presets' => BusinessPreset::orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
