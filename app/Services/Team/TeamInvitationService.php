<?php

namespace App\Services\Team;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\Billing\UserQuotaGate;
use App\Services\CompanyRoleResolver;
use App\Services\HermesNodeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

/**
 * Undangan staf lewat WhatsApp (D-65).
 *
 * Kode dikirim ke nomor WA calon anggota lewat jalur Hermes yang company-scoped
 * dan fail-closed (D-63), lalu ditukar dengan keanggotaan. Kodenya hanya
 * disimpan sebagai hash: tabel undangan adalah pintu masuk ke data usaha, jadi
 * isinya tidak boleh langsung dapat dipakai bila bocor.
 */
class TeamInvitationService
{
    /** Masa berlaku undangan. Cukup lama untuk dijawab, cukup pendek untuk tidak menganggur. */
    public const VALID_HOURS = 72;

    public function __construct(
        private readonly UserQuotaGate $quotaGate,
        private readonly HermesNodeClient $hermes,
    ) {}

    /**
     * Menerbitkan undangan. Mengembalikan kode teks terang SEKALI - hanya untuk
     * dikirim, tidak pernah disimpan dan tidak dikembalikan lagi setelah ini.
     *
     * @return array{invitation: CompanyInvitation, code: string}
     */
    public function invite(Company $company, string $waNumber, User $invitedBy, string $role = CompanyRoleResolver::ROLE_STAFF): array
    {
        if ($role !== CompanyRoleResolver::ROLE_STAFF) {
            // Kepemilikan tidak dipindahkan lewat undangan; itu jalur lain.
            throw new InvalidArgumentException('Undangan hanya dapat memberi peran staf.');
        }

        $normalized = $this->normalizeNumber($waNumber);
        if ($normalized === '') {
            throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }

        // Kuota diperiksa di titik undangan (D-65), bukan saat login.
        $this->quotaGate->assertCanAddUser((string) $company->id);

        if ($this->isAlreadyMember($company, $normalized)) {
            throw new RuntimeException('Nomor ini sudah menjadi anggota usaha.');
        }

        $code = $this->generateCode();

        $invitation = DB::transaction(function () use ($company, $normalized, $invitedBy, $role, $code): CompanyInvitation {
            // Undangan tertunda sebelumnya untuk nomor yang sama dicabut supaya
            // tidak ada dua kode hidup untuk satu orang.
            $company->invitations()
                ->where('wa_number', $normalized)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $company->invitations()->create([
                'invited_by_user_id' => $invitedBy->id,
                'wa_number' => $normalized,
                'role' => $role,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addHours(self::VALID_HOURS),
            ]);
        });

        $this->hermes->sendWhatsAppMessage(
            (string) $company->id,
            $normalized,
            sprintf(
                'Anda diundang bergabung sebagai staf di %s. Kode undangan: %s (berlaku %d jam).',
                $company->name,
                $code,
                self::VALID_HOURS,
            ),
        );

        return ['invitation' => $invitation, 'code' => $code];
    }

    /**
     * Menukar kode dengan keanggotaan.
     *
     * Kecocokan kode saja tidak cukup: nomor WA pengguna harus sama dengan yang
     * diundang. Tanpa itu kode yang bocor dapat dipakai siapa pun.
     */
    public function claim(User $user, string $code): CompanyInvitation
    {
        $normalized = $this->normalizeNumber($user->wa_number);
        if ($normalized === '') {
            throw new RuntimeException('Lengkapi nomor WhatsApp akun Anda sebelum memakai kode undangan.');
        }

        $candidates = CompanyInvitation::query()
            ->where('wa_number', $normalized)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($candidates as $invitation) {
            if (! Hash::check($code, $invitation->code_hash)) {
                continue;
            }

            return DB::transaction(function () use ($invitation, $user): CompanyInvitation {
                // Kuota diperiksa ulang saat penukaran: paket bisa turun antara
                // undangan dikirim dan kode dipakai.
                $this->quotaGate->assertCanAddUser((string) $invitation->company_id);

                $invitation->company->members()->syncWithoutDetaching([
                    $user->id => [
                        'role' => $invitation->role,
                        'invited_by_user_id' => $invitation->invited_by_user_id,
                        'accepted_at' => now(),
                    ],
                ]);

                $invitation->update(['accepted_at' => now()]);

                return $invitation->fresh();
            });
        }

        throw new RuntimeException('Kode undangan tidak berlaku, sudah dipakai, atau kedaluwarsa.');
    }

    /** Mencabut undangan yang belum dipakai. */
    public function revoke(CompanyInvitation $invitation): void
    {
        if ($invitation->accepted_at !== null) {
            throw new RuntimeException('Undangan yang sudah diterima tidak dapat dicabut. Keluarkan anggotanya.');
        }

        $invitation->update(['revoked_at' => now()]);
    }

    /**
     * Mengeluarkan anggota. Owner usaha tidak dapat dikeluarkan - ia bukan
     * anggota yang diundang, dan tanpa penjaga ini usaha bisa kehilangan
     * seluruh aksesnya.
     */
    public function removeMember(Company $company, User $member): void
    {
        if ((int) $company->owner_user_id === (int) $member->id) {
            throw new RuntimeException('Pemilik usaha tidak dapat dikeluarkan.');
        }

        $company->members()->detach($member->id);

        // Company aktif yang menggantung dibersihkan supaya sesi berikutnya
        // tidak mencoba masuk ke usaha yang aksesnya sudah dicabut.
        if ((int) $member->current_company_id === (int) $company->id) {
            $member->forceFill(['current_company_id' => null])->save();
        }
    }

    private function isAlreadyMember(Company $company, string $normalizedNumber): bool
    {
        foreach ($company->members as $member) {
            if ($this->normalizeNumber($member->wa_number) === $normalizedNumber) {
                return true;
            }
        }

        return $this->normalizeNumber($company->owner?->wa_number) === $normalizedNumber;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Menyeragamkan nomor: buang non-digit, ubah awalan lokal `08` ke `628`. */
    private function normalizeNumber(?string $number): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $number) ?? '';

        if (str_starts_with($digits, '08')) {
            return '628'.substr($digits, 2);
        }

        return $digits;
    }
}
