<?php

namespace App\Livewire;

use App\Contracts\CompanyContext;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BranchSwitcher extends Component
{
    public function getBranchesProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        try {
            $currentCompanyId = app(CompanyContext::class)->current();
            $currentCompany = Company::find($currentCompanyId);

            if (! $currentCompany) {
                return collect();
            }

            $rootId = $currentCompany->parent_company_id ?? $currentCompany->id;

            return Company::where(function ($query) use ($rootId) {
                $query->where('id', $rootId)
                    ->orWhere('parent_company_id', $rootId);
            })
                ->where('owner_user_id', $user->id)
                ->get();
        } catch (\LogicException $e) {
            \Log::error($e->getMessage());

            return collect();
        }
    }

    public function switchBranch($companyId)
    {
        $branches = $this->branches;

        if ($branches->contains('id', $companyId)) {
            app(CompanyContext::class)->setCurrent((string) $companyId);

            return $this->redirect('/app', navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.branch-switcher');
    }
}
