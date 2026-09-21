<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\TokenLedgerEntry;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ClientLogManager extends Component
{
    use WithPagination;

    public string $activeTab = 'activities'; // 'activities' or 'tokens'

    public string $selectedCompanyId = 'all';

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedCompanyId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $companies = Company::orderBy('name')->get();

        if ($this->activeTab === 'activities') {
            $query = ActivityLog::with(['company', 'user'])->latest();
            if ($this->selectedCompanyId !== 'all') {
                $query->where('company_id', $this->selectedCompanyId);
            }
            $logs = $query->paginate(15);
        } else {
            $query = TokenLedgerEntry::with('company')->latest();
            if ($this->selectedCompanyId !== 'all') {
                $query->where('company_id', $this->selectedCompanyId);
            }
            $logs = $query->paginate(15);
        }

        return view('livewire.admin.client-log-manager', [
            'companies' => $companies,
            'logs' => $logs,
        ])->layout('components.layouts.module', ['title' => 'Log Aktivitas Klien  Super Admin']);
    }
}
