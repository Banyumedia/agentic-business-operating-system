<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\HermesConversationContext;
use App\Models\HermesProfile;
use App\Models\ModuleSetting;
use App\Services\Billing\WaGroupQuotaGate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AssistantSettings extends Component
{
    public string $activeSubTab = 'sop'; // 'sop' or 'groups'

    // Form SOP (Markdown)
    public string $sopMarkdown = '';

    public int $maxDiscountPercent = 10;

    public string $operatingHours = '08:00 - 20:00';

    public bool $groupTagOnly = true;

    // Notice & feedback
    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(CompanyContext $companyContext): void
    {
        $companyId = $companyContext->current();

        $data = [];
        if (Schema::hasTable('module_settings')) {
            $setting = ModuleSetting::where('company_id', $companyId)
                ->where('module_name', 'assistant')
                ->first();
            $data = $setting?->settings_json ?? [];
        }

        $this->sopMarkdown = $data['sop_markdown'] ?? $this->defaultSopMarkdown();
        $this->maxDiscountPercent = (int) ($data['max_discount_percent'] ?? 10);
        $this->operatingHours = (string) ($data['operating_hours'] ?? '08:00 - 20:00');
        $this->groupTagOnly = (bool) ($data['group_tag_only'] ?? true);
    }

    public function saveSop(CompanyContext $companyContext): void
    {
        $this->resetFeedback();
        $this->assertOwner();

        $this->validate([
            'sopMarkdown' => ['required', 'string', 'max:5000'],
            'maxDiscountPercent' => ['required', 'integer', 'min:0', 'max:100'],
            'operatingHours' => ['required', 'string', 'max:50'],
            'groupTagOnly' => ['required', 'boolean'],
        ]);

        $companyId = $companyContext->current();

        if (Schema::hasTable('module_settings')) {
            ModuleSetting::updateOrCreate(
                ['company_id' => $companyId, 'module_name' => 'assistant'],
                [
                    'settings_json' => [
                        'sop_markdown' => $this->sopMarkdown,
                        'max_discount_percent' => $this->maxDiscountPercent,
                        'operating_hours' => $this->operatingHours,
                        'group_tag_only' => $this->groupTagOnly,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]
            );
        }

        $this->notice = 'Aturan kerja dan SOP Karyawan AI berhasil disimpan.';
    }

    public function render(CompanyContext $companyContext, WaGroupQuotaGate $waGroups): View
    {
        $companyId = $companyContext->current();
        $company = $this->findCompanyModel($companyId);
        $ownerUser = $company?->owner;

        $primaryProfile = null;
        $csProfile = null;
        if ($ownerUser && Schema::hasTable('hermes_profiles')) {
            $primaryProfile = HermesProfile::where('owner_user_id', $ownerUser->id)->where('type', 'primary')->first();
            $csProfile = HermesProfile::where('owner_user_id', $ownerUser->id)->where('type', 'addon')->first();
        }

        $activeGroups = collect();
        if ($primaryProfile && Schema::hasTable('hermes_conversation_contexts')) {
            $activeGroups = HermesConversationContext::where('hermes_profile_id', $primaryProfile->id)
                ->where('channel', 'whatsapp')
                ->where('chat_id', 'like', '%@g.us')
                ->where('active_company_id', $companyId)
                ->get();
        }

        $maxWaGroups = $waGroups->maxAllowedGroups();
        $usedGroups = count($activeGroups);

        $isOwner = session('company_role') === 'owner'
            || ($company && Auth::user() && Auth::user()->id === $company->owner_user_id);

        return view('livewire.settings.assistant-settings', [
            'primaryProfile' => $primaryProfile,
            'csProfile' => $csProfile,
            'activeGroups' => $activeGroups,
            'usedGroups' => $usedGroups,
            'maxWaGroups' => $maxWaGroups,
            'isOwner' => $isOwner,
        ]);
    }

    private function findCompanyModel(string $companyId): ?Company
    {
        if (! Schema::hasTable('companies')) {
            return null;
        }

        return is_numeric($companyId)
            ? Company::find((int) $companyId)
            : Company::where('slug', $companyId)->first();
    }

    private function assertOwner(): void
    {
        if (session('company_role') !== 'owner') {
            $user = Auth::user();
            if (! $user || ! $user->is_platform_admin) {
                abort(403, 'Hanya pemilik usaha yang dapat mengubah pengaturan asisten AI.');
            }
        }
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }

    private function defaultSopMarkdown(): string
    {
        return <<<'MD'
### Standar Operasional Asisten Bisnis
1. **Peran Utama:**
   - Bantu tim dan owner mencatat transaksi kasir, mengecek stok barang, dan mengingatkan jadwal follow-up.
2. **Pedoman Respons Grup Tim:**
   - Di grup kasir/gudang/keuangan, utamakan respons singkat dan jelas.
   - Jangan menyetujui diskon melebihi batas kebijakan yang telah ditetapkan.
3. **Pengingat & Tindak Lanjut:**
   - Bila ditanya, sebutkan tagihan piutang yang sudah lewat jatuh tempo beserta umurnya. Pengiriman pengingat otomatis belum tersedia, jadi jangan menjanjikannya.
   - Kirim ringkasan kas masuk dan keluar setiap pergantian shift atau penutupan toko.
MD;
    }
}
