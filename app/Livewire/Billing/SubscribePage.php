<?php

namespace App\Livewire\Billing;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Models\MembershipPlan;
use App\Services\Manual\InvoiceCreationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman pilih paket dan buat invoice pembayaran manual (PAY-1)
 *
 * Ditampilkan ketika:
 * - Tier gratis (company tanpa membership aktif)
 * - Klien memilih untuk upgrade dari paywall
 *
 * Flow:
 * 1. Tampilkan daftar paket aktif dari membership_plans (config-driven, D-52)
 * 2. Klien klik "Pilih" → buat invoice type=subscription, payment_status=pending
 * 3. Redirect ke halaman instruksi pembayaran
 */
class SubscribePage extends Component
{
    /** @var list<array{id: int, name: string, slug: string, monthly_price: string, max_wa_groups: int, monthly_token_quota: int}> */
    #[Locked]
    public array $plans = [];

    #[Locked]
    public string $theme = 'a';

    #[Locked]
    public string $selectedPlanId = '';

    public function mount(CompanyContext $companyContext, CompanySettingsStore $settings): void
    {
        // Ambil tema company
        try {
            $companyId = $companyContext->current();
            $theme = (string) ($settings->read($companyId)['theme'] ?? 'a');
            $this->theme = $theme;
        } catch (\Throwable) {
            $this->theme = 'a';
        }

        // Load daftar paket aktif dari database
        if (Schema::hasTable('membership_plans')) {
            $this->plans = MembershipPlan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get()
                ->map(fn (MembershipPlan $plan): array => [
                    'id' => (int) $plan->id,
                    'name' => (string) $plan->name,
                    'slug' => (string) $plan->slug,
                    'monthly_price' => 'Rp '.number_format((float) $plan->monthly_price, 0, ',', '.'),
                    'max_wa_groups' => (int) $plan->max_wa_groups,
                    'monthly_token_quota' => (int) $plan->monthly_token_quota,
                ])
                ->all();
        }
    }

    /**
     * Action: Pilih paket dan buat invoice
     *
     * Validasi:
     * - plan_id harus valid (ada di database dan is_active=true)
     * - Hanya owner yang bisa membuat invoice
     * - Anti-spam: tidak boleh ada invoice pending untuk company ini
     */
    public function selectPlan(int $planId, CompanyContext $companyContext): void
    {
        // Validasi plan_id server-side (D-52)
        $plan = MembershipPlan::query()
            ->where('id', $planId)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan' => 'Paket yang dipilih tidak tersedia.',
            ]);
        }

        // Ambil company context (current() mengembalikan id string) lalu resolve
        // ke model Company karena service mengharapkan objek.
        $companyId = $companyContext->current();
        $company = Company::findOrFail($companyId);

        // Buat invoice dengan InvoiceCreationService
        $service = app(InvoiceCreationService::class);

        try {
            $invoice = $service->createSubscriptionInvoice(
                $company,
                $plan,
                (int) auth()->id(),
            );

            // Redirect ke halaman instruksi pembayaran
            $this->redirectRoute('billing.payment-instruction', ['invoice' => $invoice->id]);
        } catch (ValidationException $e) {
            $this->addError('plan_selection', $e->errors()['authorization'][0] ?? $e->errors()['pending_invoice'][0] ?? $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.billing.subscribe-page')->layout('components.layouts.module', [
            'title' => 'Pilih Paket · Agentic BOS',
            'theme' => $this->theme,
        ]);
    }
}
