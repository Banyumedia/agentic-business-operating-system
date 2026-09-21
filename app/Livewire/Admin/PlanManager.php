<?php

namespace App\Livewire\Admin;

use App\Models\MembershipPlan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PlanManager extends Component
{
    public $plans;

    public $editingId = null;

    public $name = '';

    public $price = 0;

    public $monthlyTokenQuota = 0;

    public $maxWaGroups = 1;

    public $features = [];

    public array $availableCapabilities = [
        'contacts' => 'Kontak / Pelanggan',
        'deals' => 'Peluang / Pipeline',
        'scheduling' => 'Jadwal & Kalender',
        'bookings' => 'Reservasi / Booking',
        'inventory' => 'Stok & Gudang',
        'pos' => 'Kasir / POS',
        'quotations' => 'Penawaran / SPH',
        'invoices' => 'Penagihan / Invoice',
        'purchasing' => 'Pembelian & Vendor',
        'expenses' => 'Pencatatan Biaya',
        'manufacturing' => 'Produksi & SPK',
        'projects' => 'Manajemen Proyek',
        'memberships' => 'Member & Pelanggan Tetap',
        'hrm' => 'Karyawan & Presensi',
        'reports' => 'Laporan & Analisis',
    ];

    public function mount(): void
    {
        $this->loadPlans();
    }

    public function loadPlans(): void
    {
        $this->plans = MembershipPlan::orderBy('monthly_price')->get();
    }

    public function edit(int $id): void
    {
        $plan = MembershipPlan::findOrFail($id);
        $this->editingId = $id;
        $this->name = $plan->name;
        $this->price = (float) $plan->monthly_price;
        $this->monthlyTokenQuota = (int) $plan->monthly_token_quota;
        $this->maxWaGroups = (int) $plan->max_wa_groups;
        $this->features = is_array($plan->features) ? $plan->features : [];
    }

    public function cancel(): void
    {
        $this->editingId = null;
    }

    public function save(): void
    {
        $this->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'monthlyTokenQuota' => ['required', 'integer', 'min:0'],
            'maxWaGroups' => ['required', 'integer', 'min:1'],
            'features' => ['array'],
        ]);

        if ($this->editingId) {
            $plan = MembershipPlan::findOrFail($this->editingId);
            $plan->update([
                'monthly_price' => $this->price,
                'monthly_token_quota' => $this->monthlyTokenQuota,
                'max_wa_groups' => $this->maxWaGroups,
                'features' => $this->features,
            ]);
            $this->editingId = null;
            session()->flash('success', "Paket {$plan->name} berhasil diperbarui.");
            $this->loadPlans();
        }
    }

    public function render(): View
    {
        return view('livewire.admin.plan-manager')
            ->layout('components.layouts.module', ['title' => 'Membership Plans  Super Admin']);
    }
}
