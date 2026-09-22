<?php

namespace App\Livewire\Settings;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Services\Billing\UserQuotaGate;
use App\Services\CompanyRoleResolver;
use App\Services\Team\TeamInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * Tab Tim & Akses (D-65, T-51).
 *
 * Seluruh aksi di sini owner-only dan diperiksa **server-side** setiap kali
 * dijalankan, bukan sekali saat mount: tab yang tersembunyi bukan pengamanan.
 * Kode undangan tidak pernah disimpan maupun ditampilkan ulang - ia dikirim ke
 * nomor WA tujuan lewat Hermes lalu dilupakan.
 */
class TeamAccess extends Component
{
    public string $waNumber = '';

    public ?string $notice = null;

    public ?string $failure = null;

    public function invite(): void
    {
        $this->resetFeedback();
        $company = $this->assertOwner();

        try {
            app(TeamInvitationService::class)->invite($company, $this->waNumber, Auth::user());
            $this->notice = 'Undangan terkirim lewat WhatsApp. Kode berlaku '.TeamInvitationService::VALID_HOURS.' jam.';
            $this->waNumber = '';
        } catch (Throwable $exception) {
            // Pesan layanan sudah ditulis untuk pemilik usaha, jadi diteruskan
            // apa adanya alih-alih diganti kalimat umum.
            $this->failure = $exception->getMessage();
        }
    }

    public function revoke(int $invitationId): void
    {
        $this->resetFeedback();
        $company = $this->assertOwner();

        $invitation = $company->invitations()->whereKey($invitationId)->first();

        if ($invitation === null) {
            $this->failure = 'Undangan tidak ditemukan.';

            return;
        }

        try {
            app(TeamInvitationService::class)->revoke($invitation);
            $this->notice = 'Undangan dicabut.';
        } catch (Throwable $exception) {
            $this->failure = $exception->getMessage();
        }
    }

    public function removeMember(int $userId): void
    {
        $this->resetFeedback();
        $company = $this->assertOwner();

        $member = $company->members()->whereKey($userId)->first();

        if ($member === null) {
            $this->failure = 'Anggota tidak ditemukan pada usaha ini.';

            return;
        }

        try {
            app(TeamInvitationService::class)->removeMember($company, $member);
            $this->notice = 'Akses anggota dicabut.';
        } catch (Throwable $exception) {
            $this->failure = $exception->getMessage();
        }
    }

    public function render(): View
    {
        $company = $this->company();
        $quota = app(UserQuotaGate::class);

        $members = $company === null ? collect() : $company->members()->get()->map(fn ($member): array => [
            'id' => (int) $member->id,
            'name' => (string) $member->name,
            'wa_number' => $member->wa_number,
            'role' => (string) ($member->pivot->role ?? CompanyRoleResolver::ROLE_STAFF),
            'is_owner' => (int) $company->owner_user_id === (int) $member->id,
        ]);

        $pending = $company === null ? collect() : $company->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get()
            ->map(fn (CompanyInvitation $invitation): array => [
                'id' => (int) $invitation->id,
                'wa_number' => (string) $invitation->wa_number,
                'expires_at' => $invitation->expires_at?->toDateTimeString(),
            ]);

        return view('livewire.settings.team-access', [
            'members' => $members,
            'pending' => $pending,
            'quota' => $company === null ? 1 : $quota->quota((string) $company->id),
            'used' => $company === null ? 0 : $quota->used((string) $company->id),
            'ownerName' => $company?->owner?->name,
        ]);
    }

    private function company(): ?Company
    {
        $id = app(CompanyContext::class)->current();

        return ctype_digit((string) $id) ? Company::find((int) $id) : null;
    }

    /**
     * Semua aksi tab ini owner-only. Peran dibaca dari sumber tepercaya
     * (kepemilikan company terautentikasi), bukan dari state komponen.
     */
    private function assertOwner(): Company
    {
        $company = $this->company();
        abort_if($company === null, 404);

        abort_unless(
            app(CompanyRoleResolver::class)->isOwnerOfCompany((string) $company->id),
            403,
        );

        return $company;
    }

    private function resetFeedback(): void
    {
        $this->notice = null;
        $this->failure = null;
    }
}
