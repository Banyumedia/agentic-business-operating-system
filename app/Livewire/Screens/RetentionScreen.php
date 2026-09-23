<?php

namespace App\Livewire\Screens;

use App\Contracts\CompanyContext;
use App\Models\Retention;
use App\Services\CompanyRoleResolver;
use App\Services\Domain\RetentionService;
use App\Services\DynamicMenuRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

/**
 * Pencairan retensi proyek (MP-09).
 *
 * `RetentionService::computeAndHold()`/`ensureCanBeInvoiced()` sudah ada dan
 * sudah teruji, tapi menu `projects/retention` sebelumnya cuma `list` generik
 * atas `project_milestones` - tidak ada satu pun jalur pencairan dari UI
 * (temuan §1 `docs/plans/module-parity-plan.md`). Layar ini menyambungkan
 * daftar retensi yang tertahan ke `RetentionService::disburse()` (baru,
 * ditambahkan bersama layar ini).
 *
 * Eloquent-only, pola yang sama dengan MP-02/MP-10: `retentions` tidak punya
 * schema JSON (D-42 tidak mencakupnya, tabel SQL murni sejak T-45/#1 di plan
 * paritas), jadi jalur JSON tidak punya cara membaca maupun mencairkan
 * retensi yang bisa diaudit.
 *
 * Konfirmasi D-45 tingkat 1 ("ketik YA") untuk `disburse()` - uang keluar
 * dari usaha, ireversibel setelah tercatat di buku kas, pola yang sama
 * dengan `CashierScreen::checkout()`. Owner-only (T-50), server-side.
 */
class RetentionScreen extends Component
{
    #[Locked]
    public string $module;

    #[Locked]
    public string $company;

    #[Locked]
    public ?int $pendingReleaseId = null;

    public string $confirmPhrase = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function mount(string $module, ?string $submodule = null): void
    {
        $this->module = $module;
        $this->company = app(CompanyContext::class)->current();
    }

    /** Membuka konfirmasi; tidak mencairkan apa pun. */
    public function requestRelease(int $id): void
    {
        $this->resetFeedback();

        $retention = $this->retentionFor($id);
        if ($retention === null) {
            $this->failure = 'Retensi tidak ditemukan pada usaha ini.';

            return;
        }

        if ($retention->status !== 'held') {
            $this->failure = 'Retensi ini sudah tidak berstatus tertahan.';

            return;
        }

        if ($retention->release_on && now()->startOfDay()->lt($retention->release_on)) {
            $this->failure = 'Retensi belum mencapai tanggal rilis ('.$retention->release_on->format('Y-m-d').').';

            return;
        }

        $this->pendingReleaseId = $id;
        $this->confirmPhrase = '';
    }

    public function cancelRelease(): void
    {
        $this->pendingReleaseId = null;
        $this->confirmPhrase = '';
    }

    public function confirmRelease(): void
    {
        $id = $this->pendingReleaseId;
        if ($id === null) {
            return;
        }

        $this->resetFeedback();

        if (! $this->phraseAccepted()) {
            $this->failure = 'Ketik YA tepat seperti tertulis untuk menegaskan.';

            return;
        }

        $this->pendingReleaseId = null;

        abort_unless($this->isOwner(), 403);

        $retention = $this->retentionFor($id);
        if ($retention === null) {
            $this->failure = 'Retensi tidak ditemukan pada usaha ini.';

            return;
        }

        try {
            app(RetentionService::class)->disburse($retention);
        } catch (LogicException $exception) {
            $this->failure = $exception->getMessage();

            return;
        }

        $this->notice = 'Retensi dicairkan dan tercatat di buku kas.';
    }

    public function render(): View
    {
        $definition = $this->definition();

        $retentions = Retention::query()
            ->where('company_id', (int) $this->company)
            ->with('project')
            ->orderByRaw("status = 'held' desc")
            ->orderBy('release_on')
            ->orderBy('id')
            ->get()
            ->map(fn (Retention $retention): array => [
                'id' => $retention->id,
                'project_name' => $retention->project?->name ?? ('Proyek #'.$retention->project_id),
                'amount' => (float) $retention->amount,
                'status' => $retention->status,
                'release_on' => $retention->release_on?->toDateString(),
                'released_at' => $retention->released_at?->toIso8601String(),
                'can_release' => $retention->status === 'held'
                    && (! $retention->release_on || now()->startOfDay()->gte($retention->release_on)),
            ])
            ->all();

        return view('livewire.screens.retention', [
            'label' => $definition['label'],
            'term' => $definition['term'] ?? $definition['label'],
            'retentions' => $retentions,
            'isOwner' => $this->isOwner(),
            'confirmLevel' => $this->pendingReleaseId === null ? null : 'type',
        ]);
    }

    private function retentionFor(int $id): ?Retention
    {
        return Retention::query()->where('company_id', (int) $this->company)->find($id);
    }

    private function phraseAccepted(): bool
    {
        return mb_strtoupper(trim($this->confirmPhrase)) === 'YA';
    }

    private function isOwner(): bool
    {
        return app(CompanyRoleResolver::class)->isOwnerOfCompany($this->company());
    }

    /** @return array{label: string, icon: string, route: string, screen: string, entity: string, term: string|null} */
    private function definition(): array
    {
        $registry = app(DynamicMenuRegistry::class);

        abort_unless($registry->hasPath($this->module, 'retention'), 404);
        abort_unless($registry->isModuleVisible($this->module), 403);

        $definition = $registry->routeDefinition($this->module, 'retention');
        abort_if($definition === null, 403);

        return $definition;
    }

    private function company(): string
    {
        abort_unless(app(CompanyContext::class)->current() === $this->company, 403);

        return $this->company;
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
