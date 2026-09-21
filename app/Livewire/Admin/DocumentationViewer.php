<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DocumentationViewer extends Component
{
    public string $activeDoc = 'architecture';

    public function render(): View
    {
        return view('livewire.admin.documentation-viewer')
            ->layout('components.layouts.module', ['title' => 'Dokumentasi Sistem & Asisten AI  Super Admin']);
    }
}
