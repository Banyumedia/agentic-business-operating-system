<?php

namespace App\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class LandingPage extends Component
{
    public function mount()
    {
        if (auth()->check()) {
            return redirect()->route('app.dashboard');
        }
    }

    public function render(): View
    {
        return view('livewire.public.landing-page')
            ->layout('components.layouts.guest');
    }
}
