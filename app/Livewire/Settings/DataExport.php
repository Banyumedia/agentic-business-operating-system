<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Jobs\BuildCompanyExport;
use App\Models\AccessLog;
use App\Models\Company;
use App\Services\CompanyRoleResolver;
use Livewire\Component;

class DataExport extends Component
{
    public bool $isExporting = false;

    public ?string $downloadUrl = null;

    public function export(CompanyContext $companyContext): void
    {
        $companyId = $companyContext->current();
        if (! $companyId) {
            return;
        }

        // Fail-closed (QA-UI-R B.7): tab yang hanya tampil untuk owner tidak
        // mengamankan aksi. Ekspor data usaha revalidasi kepemilikan dari
        // pengguna terautentikasi, sama seperti penghapusan data.
        abort_unless(app(CompanyRoleResolver::class)->isOwnerOfCompany($companyId), 403);

        $this->isExporting = true;

        // Dispatch job and we would ideally poll or notify when done.
        // For simplicity, we can do it synchronously or async.
        // The task says "Tombol Ekspor CSV seluruh data per-tabel di Settings, wajib bisa diakses akun owner meskipun status membership canceled/past_due."
        // We will dispatch sync for the MVP or dispatch async and wait.

        $job = new BuildCompanyExport($companyId, auth()->id());
        dispatch_sync($job);

        // Assume the job writes to a known path
        $this->downloadUrl = route('settings.export.download', ['company' => $companyId]);
        $this->isExporting = false;

        AccessLog::create([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'subject_type' => Company::class,
            'subject_id' => $companyId,
            'action' => 'export',
            'ip' => request()->ip(),
        ]);
    }

    public function render()
    {
        return view('livewire.settings.data-export');
    }
}
