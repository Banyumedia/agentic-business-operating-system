<?php

namespace App\Livewire;

use Livewire\Component;

class DummyModule extends Component
{
    public $module;
    public $path;

    public function mount($module, $path = 'dashboard')
    {
        $this->module = $module;
        $this->path = $path;
    }

    public function render()
    {
        return view('livewire.dummy-module')->layout('components.layouts.module');
    }
}
