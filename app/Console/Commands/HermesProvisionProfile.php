<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\HermesNode;
use App\Models\HermesProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membuat profil Hermes — untuk tenant maupun untuk platform (T-80).
 *
 * `HermesProfileProvisioner::ensurePrimaryProfile()` sudah ada sejak lama tetapi
 * **tidak pernah dipanggil dari mana pun**, sehingga tidak ada satu cara pun
 * membuat baris `hermes_profiles`. Tanpa baris itu `HermesNodeClient` selalu
 * menolak, bot tenant tidak punya token untuk memanggil TenantBot API, dan bot CS
 * platform tidak bisa didaftarkan. Pola yang sama dengan `hermes_nodes` sebelum
 * T-70: skemanya siap, jalurnya tidak ada.
 *
 * Contoh:
 *   php artisan bos:hermes-profile --company=3 --node=1
 *   php artisan bos:hermes-profile --platform --owner=1 --node=1 --type=addon --label="Bot CS"
 */
class HermesProvisionProfile extends Command
{
    protected $signature = 'bos:hermes-profile
        {--company= : ID company yang dilayani profil ini (jalur tenant)}
        {--platform : Buat profil milik platform, tidak melayani company mana pun}
        {--owner= : ID user pemilik profil; wajib untuk --platform}
        {--node= : ID hermes_nodes tempat profil ditempatkan}
        {--type=primary : primary atau addon}
        {--label= : Label yang tampil di panel super admin}
        {--api-url= : Alamat bridge WhatsApp milik profil ini; kosong berarti memakai alamat node}
        {--reissue : Terbitkan ulang token bot untuk profil yang sudah ada; token lama langsung berhenti berlaku}';

    protected $description = 'Membuat profil Hermes untuk tenant atau untuk platform';

    /**
     * Token bot dalam bentuk mentah, hanya selama perintah berjalan.
     *
     * Null berarti tidak ada token baru yang diterbitkan pada jalankan ini - profil
     * sudah ada dan `--reissue` tidak diminta. Dibedakan supaya perintah tidak pernah
     * mencetak nilai yang tidak bisa dipakai.
     */
    private ?string $issuedToken = null;

    public function handle(): int
    {
        $node = HermesNode::query()->find($this->option('node'));

        if ($node === null) {
            $this->error('Node Hermes tidak ditemukan. Daftarkan dulu di /admin/hermes-nodes.');

            return self::FAILURE;
        }

        // Alamat tanpa skema akan ditolak `HermesNodeClient` saat mengirim.
        // Menolaknya sekarang mencegah baris yang tampak sah tapi mati saat dipakai.
        $bridge = trim((string) $this->option('api-url'));

        if ($bridge !== '' && ! str_starts_with($bridge, 'http://') && ! str_starts_with($bridge, 'https://')) {
            $this->error('Alamat bridge harus diawali http:// atau https://.');

            return self::FAILURE;
        }

        // `max_capacity` ada supaya satu node tidak kelebihan profil. Mengabaikannya
        // membuat kolom itu sekadar dekorasi.
        //
        // Yang dibandingkan adalah jumlah profil yang **nyata** menempel, bukan kolom
        // `active_profiles`. Kolom itu tidak punya jalan turun (pencabutan profil di
        // `CleanupExpiredTrials` hanya menulis `node_id => null`), jadi mempercayainya
        // berarti node yang lowong lambat laun menolak profil yang sah. Selarasnya
        // dilakukan lebih dulu supaya pesan galat menyebut angka yang benar dan panel
        // admin tidak terus menampilkan angka basi.
        $node->syncActiveProfiles();

        if ($node->isAtCapacity()) {
            $this->error("Node {$node->name} sudah penuh ({$node->active_profiles}/{$node->max_capacity}).");

            return self::FAILURE;
        }

        try {
            $profile = $this->option('platform')
                ? $this->provisionPlatform($node)
                : $this->provisionTenant($node);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Profil siap: #'.$profile->id.' ('.$profile->type.')');
        $this->line('  instance_id      : '.$profile->instance_id);
        $this->line('  bridge           : '.($profile->api_url ?: $node->api_url.' (dari node)'));

        // Token hanya pernah ada di sini. Basis data menyimpan **hash**-nya (QA-08),
        // jadi kalau hilang ia tidak bisa ditampilkan ulang - hanya diterbitkan ulang.
        if ($this->issuedToken !== null) {
            $this->line('  token bot        : '.$this->issuedToken);
            $this->newLine();
            $this->line('Pasang token itu di sisi Hermes sebagai kredensial pemanggil TenantBot API.');
            $this->line('Catat sekarang: yang tersimpan di basis data hanya hash-nya, jadi token ini tidak bisa ditampilkan lagi.');
        } else {
            // Menampilkan sesuatu yang tampak seperti token padahal bukan akan membuat
            // operator memasang nilai yang tidak akan pernah bekerja.
            $this->newLine();
            $this->line('Profil ini sudah ada dan tokennya tidak dapat ditampilkan lagi - yang tersimpan hanya hash.');
            $this->line('Jalankan ulang dengan --reissue bila token lamanya hilang; token lama langsung berhenti berlaku.');
        }

        return self::SUCCESS;
    }

    private function provisionTenant(HermesNode $node): HermesProfile
    {
        $company = Company::query()->find($this->option('company'));

        if ($company === null) {
            throw new \RuntimeException('Company tidak ditemukan.');
        }

        // Profil tertaut ke `owner_user_id`; company tanpa owner hanya akan
        // menghasilkan baris menggantung yang tidak pernah bisa dipakai.
        $owner = User::query()->find($company->owner_user_id);

        if ($owner === null) {
            throw new \RuntimeException('Company ini belum punya owner, jadi profil tidak bisa ditautkan.');
        }

        return DB::transaction(function () use ($company, $owner, $node): HermesProfile {
            $profile = HermesProfile::query()->firstOrCreate(
                ['owner_user_id' => $owner->id, 'type' => 'primary'],
                [
                    'node_id' => $node->id,
                    'api_url' => $this->bridgeAddress(),
                    'label' => $this->option('label') ?: 'Asisten '.$company->name,
                    'instance_id' => $this->instanceId('tenant', $owner->id),
                    'webhook_secret_reference' => $this->issueToken(),
                    'status' => 'unpaired',
                    'is_platform_provided' => false,
                ]
            );

            // `firstOrCreate` mengevaluasi seluruh larik atribut walau barisnya sudah
            // ada, jadi `issueToken()` tetap terpanggil. Kalau tidak dibatalkan di
            // sini, perintah akan mencetak token yang tidak pernah tersimpan.
            $this->settleToken($profile);

            // Penghitung diselaraskan dari kenyataan, bukan dinaikkan. Jalankan kedua
            // tidak membuat profil baru, jadi hitungannya juga tidak berubah - tanpa
            // perlu menebak dari `wasRecentlyCreated`.
            $node->syncActiveProfiles();

            // `syncWithoutDetaching` supaya perintah bisa dijalankan dua kali tanpa
            // menumpuk baris pivot - dan tanpa mencabut company lain yang sudah
            // dilayani profil yang sama (D-37: satu profil primary per owner, lintas
            // beberapa company miliknya).
            $profile->companies()->syncWithoutDetaching([
                $company->id => ['is_default' => true, 'created_at' => now()],
            ]);

            return $profile;
        });
    }

    private function provisionPlatform(HermesNode $node): HermesProfile
    {
        $owner = User::query()->find($this->option('owner'));

        if ($owner === null) {
            throw new \RuntimeException('Owner profil platform tidak ditemukan.');
        }

        // Profil platform membawa nomor yang mewakili kita. Menautkannya ke user
        // sembarang berarti pemilik nomor itu bukan pihak yang berwenang.
        if (! $owner->is_platform_admin) {
            throw new \RuntimeException('Profil platform hanya boleh dimiliki super admin.');
        }

        $type = (string) $this->option('type');

        if (! in_array($type, ['primary', 'addon'], true)) {
            throw new \RuntimeException('Tipe profil harus primary atau addon.');
        }

        return DB::transaction(function () use ($owner, $node, $type): HermesProfile {
            // `firstOrCreate` dengan pola yang sama seperti jalur tenant. Sebelumnya
            // jalur ini memakai `create()` tanpa syarat, dan karena satu-satunya kolom
            // unik (`instance_id`) diacak per jalankan, tidak ada kendala basis data
            // yang menahan duplikat. Dua profil platform berbahaya secara halus:
            // `PlatformHermesNodeClient::senderProfile()` memilih dengan
            // `orderBy('id')->first()`, jadi yang kedua diam-diam tidak terpakai
            // sementara ia memegang nomor yang mewakili perusahaan.
            //
            // Kunci pencarian adalah owner + tipe + penanda platform: itulah yang
            // sebenarnya mengidentifikasi satu profil platform. Tipe ikut jadi kunci
            // supaya `--type=primary` dan `--type=addon` tidak saling menimpa.
            $profile = HermesProfile::query()->firstOrCreate(
                [
                    'owner_user_id' => $owner->id,
                    'type' => $type,
                    // Tanpa penanda ini, `addon` akan ditolak karena tidak punya
                    // `billing_addon_id` (T-68), dan lajur tenant bisa memakainya.
                    'is_platform_provided' => true,
                ],
                [
                    'node_id' => $node->id,
                    'api_url' => $this->bridgeAddress(),
                    'label' => $this->option('label') ?: 'Bot Platform',
                    'instance_id' => $this->instanceId('platform', $owner->id),
                    'webhook_secret_reference' => $this->issueToken(),
                    'status' => 'unpaired',
                ]
            );

            $this->settleToken($profile);

            $node->syncActiveProfiles();

            return $profile;
        });
    }

    /**
     * Alamat bridge milik profil, atau null bila ia memakai alamat node.
     * Nullable dan bukan string kosong: kolom kosong berarti "belum diisi", bukan
     * "alamatnya kosong".
     */
    private function bridgeAddress(): ?string
    {
        $bridge = trim((string) $this->option('api-url'));

        return $bridge === '' ? null : $bridge;
    }

    private function instanceId(string $kind, int $ownerId): string
    {
        return 'inst_'.$kind.'_'.$ownerId.'_'.bin2hex(random_bytes(4));
    }

    /**
     * Menerbitkan token bot baru, menyimpan **hash**-nya, dan mengingat plaintextnya
     * hanya selama perintah ini berjalan.
     *
     * Token dipakai bot untuk memanggil TenantBot API. Dua profil dengan token sama
     * berarti bot yang satu bisa menyamar sebagai yang lain, jadi ia acak dan panjang
     * - bukan diturunkan dari id atau nama. Yang masuk basis data adalah hash-nya
     * (QA-08): kolom yang menyimpan token apa adanya menjadikan setiap salinan basis
     * data sebagai salinan kredensial.
     */
    private function issueToken(): string
    {
        $this->issuedToken = 'sec_'.Str::random(40);

        return HermesProfile::hashBotToken($this->issuedToken);
    }

    /**
     * Menerbitkan ulang token untuk profil yang sudah ada.
     *
     * Ada karena menyimpan hash punya satu konsekuensi yang tidak bisa dihindari:
     * token yang hilang tidak bisa ditampilkan lagi. Tanpa jalan ini, operator yang
     * kehilangan token hanya punya pilihan menghapus profil - dan itu memutus peta
     * company↔profil (D-37) beserta seluruh riwayatnya.
     */
    private function reissueToken(HermesProfile $profile): void
    {
        $profile->forceFill(['webhook_secret_reference' => $this->issueToken()])->save();
    }

    /**
     * Menentukan token mana yang sah untuk ditampilkan setelah `firstOrCreate`.
     *
     * Tiga keadaan yang harus dibedakan, karena masing-masing punya jawaban berbeda
     * bagi operator: profil baru (token baru tersimpan, tampilkan), profil sudah ada
     * dengan `--reissue` (terbitkan ulang, tampilkan yang baru), dan profil sudah ada
     * tanpa `--reissue` (tidak ada yang bisa ditampilkan - katakan apa adanya).
     */
    private function settleToken(HermesProfile $profile): void
    {
        if ($profile->wasRecentlyCreated) {
            return;
        }

        if ($this->option('reissue')) {
            $this->reissueToken($profile);

            return;
        }

        $this->issuedToken = null;
    }
}
