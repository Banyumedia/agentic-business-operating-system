<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\User;
use App\Services\CompanyRoleResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengenali siapa manusia di balik sebuah nomor WhatsApp (D-66, T-58).
 *
 * Sebelum ini bot tidak punya identitas per orang: japri hanya dibandingkan
 * dengan nomor owner, dan di grup bot tidak tahu siapa yang bicara sehingga
 * peran tak pernah bisa diterapkan.
 *
 * Tiga penjaga yang menentukan hasilnya:
 * 1. Nomor cocok saja tidak cukup - `wa_is_verified` wajib benar, karena nomor
 *    WA berpindah tangan.
 * 2. Keanggotaan `company_user` (D-65) wajib aktif; pencabutan keanggotaan
 *    langsung menutup akses tanpa perlu menyentuh apa pun di sisi WA.
 * 3. Satu nomor yang menjadi anggota di lebih dari satu company **ditolak**
 *    (fail-closed), bukan ditebak, sampai T-37 menghadirkan pemilih konteks.
 */
class WhatsAppSenderIdentity
{
    public function __construct(private readonly CompanyRoleResolver $roles) {}

    /**
     * @return array{
     *     known: bool,
     *     reason: string,
     *     user: User|null,
     *     company: Company|null,
     *     role: string|null,
     *     ambiguous: bool
     * }
     */
    public function resolve(HermesProfile $profile, string $senderPhone): array
    {
        // Profil milik platform (bot dev, bot CS platform) tidak melayani company
        // mana pun, jadi ia tidak boleh pernah mengenali seseorang sebagai anggota
        // sebuah usaha - bahkan bila nomornya terverifikasi dan ia memang pemilik
        // usaha lain. Aturan untuk profil platform ditulis terpisah; di sini
        // fail-closed.
        if ($profile->is_platform_provided) {
            return $this->unknown('Profil platform tidak melayani usaha mana pun.');
        }

        $number = $this->normalize($senderPhone);

        if ($number === '') {
            return $this->unknown('Nomor pengirim tidak dapat dibaca.');
        }

        $users = $this->verifiedUsersFor($number);

        if ($users->isEmpty()) {
            // Dibedakan dari "bukan anggota": nomor yang terdaftar tapi belum
            // terverifikasi harus terbaca sebagai masalah verifikasi, bukan
            // sebagai orang asing.
            return $this->unknown($this->existsUnverified($number)
                ? 'Nomor pengirim belum terverifikasi.'
                : 'Nomor pengirim tidak dikenali.');
        }

        $memberships = [];

        foreach ($users as $user) {
            foreach ($this->companiesOf($user, $profile) as $company) {
                $memberships[] = ['user' => $user, 'company' => $company];
            }
        }

        if ($memberships === []) {
            return $this->unknown('Nomor pengirim bukan anggota usaha ini.');
        }

        if (count($memberships) > 1) {
            // Menebak company berarti berisiko menjawab dengan data usaha yang
            // salah. Lebih baik menolak dan menunggu T-37.
            return [
                'known' => false,
                'reason' => 'Nomor ini terdaftar pada lebih dari satu usaha; pilih usaha aktif lebih dulu.',
                'user' => null,
                'company' => null,
                'role' => null,
                'ambiguous' => true,
            ];
        }

        /** @var User $user */
        $user = $memberships[0]['user'];
        /** @var Company $company */
        $company = $memberships[0]['company'];

        return [
            'known' => true,
            'reason' => 'Pengirim dikenali.',
            'user' => $user,
            'company' => $company,
            'role' => $this->roleFor($user, $company),
            'ambiguous' => false,
        ];
    }

    /**
     * Company tempat pengguna ini berhak, dibatasi company yang memang dilayani
     * profil bot tersebut. Tanpa pembatasan itu, bot satu usaha bisa menjawab
     * atas nama usaha lain yang kebetulan punya anggota sama.
     *
     * @return list<Company>
     */
    private function companiesOf(User $user, HermesProfile $profile): array
    {
        $servedIds = $profile->companies()->pluck('companies.id')->all();
        $companies = [];

        $ownedQuery = Company::query()->where('owner_user_id', $user->id);
        if ($servedIds !== []) {
            $ownedQuery->whereIn('id', $servedIds);
        }

        foreach ($ownedQuery->get() as $company) {
            $companies[(int) $company->id] = $company;
        }

        if (Schema::hasTable('company_user')) {
            $memberIds = DB::table('company_user')
                ->where('user_id', $user->id)
                ->pluck('company_id')
                ->all();

            if ($servedIds !== []) {
                $memberIds = array_values(array_intersect($memberIds, $servedIds));
            }

            foreach (Company::query()->whereIn('id', $memberIds)->get() as $company) {
                $companies[(int) $company->id] = $company;
            }
        }

        return array_values($companies);
    }

    private function roleFor(User $user, Company $company): string
    {
        if ((int) $company->owner_user_id === (int) $user->id) {
            return CompanyRoleResolver::ROLE_OWNER;
        }

        if (! Schema::hasTable('company_user')) {
            return CompanyRoleResolver::ROLE_STAFF;
        }

        $role = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->value('role');

        return $role === CompanyRoleResolver::ROLE_OWNER
            ? CompanyRoleResolver::ROLE_OWNER
            : CompanyRoleResolver::ROLE_STAFF;
    }

    /** @return Collection<int, User> */
    private function verifiedUsersFor(string $number)
    {
        return User::query()
            ->whereNotNull('wa_number')
            ->where('wa_is_verified', true)
            ->get()
            ->filter(fn (User $user): bool => $this->normalize($user->wa_number) === $number)
            ->values();
    }

    private function existsUnverified(string $number): bool
    {
        return User::query()
            ->whereNotNull('wa_number')
            ->get()
            ->contains(fn (User $user): bool => $this->normalize($user->wa_number) === $number);
    }

    /** @return array{known: bool, reason: string, user: null, company: null, role: null, ambiguous: bool} */
    private function unknown(string $reason): array
    {
        return [
            'known' => false,
            'reason' => $reason,
            'user' => null,
            'company' => null,
            'role' => null,
            'ambiguous' => false,
        ];
    }

    /** Menyeragamkan nomor: buang non-digit, ubah awalan lokal `08` ke `628`. */
    private function normalize(?string $number): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $number) ?? '';

        if (str_starts_with($digits, '08')) {
            return '628'.substr($digits, 2);
        }

        return $digits;
    }
}
