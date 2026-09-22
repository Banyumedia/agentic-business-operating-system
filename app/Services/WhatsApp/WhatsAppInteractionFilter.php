<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Models\HermesProfile;
use App\Services\CompanyRoleResolver;

class WhatsAppInteractionFilter
{
    /**
     * Memeriksa apakah pesan masuk layak diproses atau harus diabaikan (fail-closed).
     *
     * Hasilnya dapat memuat `role` dan `read_only` untuk pengirim yang dikenali
     * (D-66). `read_only = true` berarti pemanggil WAJIB menolak aksi tulis:
     * bot tidak boleh melakukan apa pun yang web larang untuk orang itu.
     *
     * @param  HermesProfile  $profile  Profil bot penerima
     * @param  string  $chatId  Nomor JID (grup format @g.us, DM format @s.whatsapp.net)
     * @param  string  $senderPhone  Nomor pengirim
     * @param  string  $messageText  Isi pesan teks
     * @param  Company|null  $company  Perusahaan konteks aktif
     * @param  bool  $isMentioned  Apakah bot di-tag/mention di pesan
     * @return array{allow: bool, reason: string}
     */
    public function evaluate(
        HermesProfile $profile,
        string $chatId,
        string $senderPhone,
        string $messageText,
        ?Company $company = null,
        bool $isMentioned = false
    ): array {
        $isGroup = str_ends_with($chatId, '@g.us');

        // 1. Profil Primary (Bot Internal Operasional)
        if ($profile->type === 'primary') {
            // A. Pesan Langsung (DM/Japri)
            if (! $isGroup) {
                $owner = $profile->owner;

                // Kolomnya `wa_number`, bukan `phone` (migration
                // 2026_09_17_222052). Sebelumnya di sini terbaca `phone` yang
                // tidak ada di skema `users`, sehingga pembandingnya selalu
                // kosong dan owner TIDAK PERNAH bisa japri bot-nya sendiri.
                $cleanSender = $this->normalizePhone($senderPhone);
                $cleanOwner = $this->normalizePhone($owner?->wa_number);

                if ($cleanOwner !== '' && $cleanSender === $cleanOwner) {
                    // Nomor yang cocok tapi belum terverifikasi tetap ditolak:
                    // nomor WA berpindah tangan, jadi kecocokan saja bukan bukti
                    // identitas (fail-closed, D-66).
                    if (($owner?->wa_is_verified ?? false) !== true) {
                        return [
                            'allow' => false,
                            'reason' => 'Nomor pemilik usaha belum terverifikasi.',
                        ];
                    }

                    return [
                        'allow' => true,
                        'reason' => 'Owner direct message.',
                        'role' => CompanyRoleResolver::ROLE_OWNER,
                        'read_only' => false,
                    ];
                }

                // Bukan owner: coba kenali sebagai anggota tim (D-65/D-66).
                // Japri staf diizinkan tetapi BACA-SAJA, dan aksi apa pun tetap
                // melewati otorisasi yang sama dengan web - WA tidak boleh
                // menjadi jalan memutar aturan owner-only (T-50).
                $identity = app(WhatsAppSenderIdentity::class)->resolve($profile, $senderPhone);

                if (! $identity['known']) {
                    return [
                        'allow' => false,
                        'reason' => $identity['ambiguous']
                            ? $identity['reason']
                            : 'DM ke bot internal hanya diizinkan untuk nomor pemilik usaha (Owner) atau anggota tim terdaftar.',
                    ];
                }

                return [
                    'allow' => true,
                    'reason' => 'Anggota tim dikenali; japri dibatasi baca-saja.',
                    'role' => $identity['role'],
                    'read_only' => $identity['role'] !== CompanyRoleResolver::ROLE_OWNER,
                ];
            }

            // B. Pesan di Grup Tim Internal
            if ($isGroup && $company) {
                // Relasinya bernama `settings()`, bukan `moduleSettings()`.
                // Pemanggilan sebelumnya melempar BadMethodCallException setiap
                // ada pesan grup dengan konteks company - tidak pernah terlihat
                // karena test lama selalu mengirim company null.
                $moduleSetting = $company->settings()->where('module_name', 'assistant')->first();
                $tagOnly = $moduleSetting?->settings_json['group_tag_only'] ?? true;

                if ($tagOnly && ! $isMentioned && ! str_contains($messageText, '@bot')) {
                    return [
                        'allow' => false,
                        'reason' => 'Mode grup aktif: bot hanya merespons saat di-tag/mention.',
                    ];
                }

                return ['allow' => true, 'reason' => 'Group message mentioned or free interaction.'];
            }
        }

        // 2. Profil Addon (Bot CS Publik)
        if ($profile->type === 'addon') {
            // Bot CS hanya melayani chat langsung pelanggan, bukan di grup
            if ($isGroup) {
                return [
                    'allow' => false,
                    'reason' => 'Bot CS publik tidak melayani percakapan grup.',
                ];
            }

            return ['allow' => true, 'reason' => 'Public customer inquiry allowed.'];
        }

        return ['allow' => false, 'reason' => 'Unknown profile type.'];
    }

    /**
     * Menyeragamkan nomor sebelum dibandingkan: buang non-digit lalu ubah
     * awalan lokal `08` menjadi `628`. Nilai kosong tetap kosong supaya
     * pemanggil dapat memperlakukannya sebagai gagal, bukan cocok.
     */
    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '08')) {
            return '628'.substr($digits, 2);
        }

        return $digits;
    }
}
