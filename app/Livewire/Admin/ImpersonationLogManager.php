<?php

namespace App\Livewire\Admin;

use App\Models\AdminImpersonationSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ImpersonationLogManager extends Component
{
    use WithPagination;

    public function render(): View
    {
        $logs = AdminImpersonationSession::with(['admin', 'targetCompany.owner'])
            ->latest()
            ->paginate(15);

        return view('livewire.admin.impersonation-log-manager', [
            'logs' => $logs,
        ])->layout('components.layouts.module', ['title' => 'Audit Log Impersonasi  Super Admin']);
    }
}
