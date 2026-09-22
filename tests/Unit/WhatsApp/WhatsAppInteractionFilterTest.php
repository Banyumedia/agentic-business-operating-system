<?php

namespace Tests\Unit\WhatsApp;

use App\Models\HermesProfile;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppInteractionFilter;
use PHPUnit\Framework\TestCase;

class WhatsAppInteractionFilterTest extends TestCase
{
    private WhatsAppInteractionFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new WhatsAppInteractionFilter;
    }

    public function test_primary_profile_rejects_dm_from_stranger(): void
    {
        $owner = $this->owner();

        $profile = new HermesProfile;
        $profile->type = 'primary';
        $profile->setRelation('owner', $owner);

        // Stranger DM
        $eval = $this->filter->evaluate(
            $profile,
            '628999999999@s.whatsapp.net',
            '628999999999',
            'Halo bisa pinjam uang?',
            null,
            false
        );

        $this->assertFalse($eval['allow']);
        $this->assertStringContainsString('hanya diizinkan untuk nomor pemilik', $eval['reason']);
    }

    public function test_primary_profile_allows_dm_from_owner(): void
    {
        $owner = $this->owner();

        $profile = new HermesProfile;
        $profile->type = 'primary';
        $profile->setRelation('owner', $owner);

        // Owner DM
        $eval = $this->filter->evaluate(
            $profile,
            '6281234567890@s.whatsapp.net',
            '0812-3456-7890',
            'Berapa omzet hari ini?',
            null,
            false
        );

        $this->assertTrue($eval['allow']);
    }

    public function test_addon_cs_profile_allows_any_public_dm(): void
    {
        $profile = new HermesProfile;
        $profile->type = 'addon';

        // Public customer DM
        $eval = $this->filter->evaluate(
            $profile,
            '628888888888@s.whatsapp.net',
            '628888888888',
            'Toko buka jam berapa?',
            null,
            false
        );

        $this->assertTrue($eval['allow']);
    }

    public function test_addon_cs_profile_rejects_group_chats(): void
    {
        $profile = new HermesProfile;
        $profile->type = 'addon';

        // Addon dimasukkan ke grup
        $eval = $this->filter->evaluate(
            $profile,
            '120363012345678@g.us',
            '628888888888',
            'Halo grup',
            null,
            false
        );

        $this->assertFalse($eval['allow']);
        $this->assertStringContainsString('tidak melayani percakapan grup', $eval['reason']);
    }

    public function test_negative_owner_number_is_read_from_the_column_that_actually_exists(): void
    {
        // Cacat T-57: filter dulu membaca `phone`, kolom yang tidak ada di
        // skema `users`. Test lama tetap hijau karena menulis atribut itu ke
        // model belum tersimpan. Di sini nomor SENGAJA hanya diisi pada
        // `phone`, jadi bila implementasi kembali membacanya, test ini gagal.
        $owner = new User;
        $owner->id = 1;
        $owner->phone = '6281234567890';
        $owner->wa_is_verified = true;

        $profile = new HermesProfile;
        $profile->type = 'primary';
        $profile->setRelation('owner', $owner);

        $eval = $this->filter->evaluate(
            $profile,
            '6281234567890@s.whatsapp.net',
            '6281234567890',
            'Berapa omzet hari ini?',
            null,
            false
        );

        $this->assertFalse($eval['allow'], 'Nomor owner hanya sah bila tersimpan di kolom wa_number.');
    }

    public function test_negative_unverified_owner_number_is_rejected(): void
    {
        // Nomor WA berpindah tangan, jadi kecocokan saja bukan bukti identitas (D-66).
        $owner = $this->owner(verified: false);

        $profile = new HermesProfile;
        $profile->type = 'primary';
        $profile->setRelation('owner', $owner);

        $eval = $this->filter->evaluate(
            $profile,
            '6281234567890@s.whatsapp.net',
            '6281234567890',
            'Berapa omzet hari ini?',
            null,
            false
        );

        $this->assertFalse($eval['allow']);
        $this->assertStringContainsString('belum terverifikasi', $eval['reason']);
    }

    public function test_negative_owner_without_a_number_never_matches(): void
    {
        // Tanpa penjaga nilai kosong, pengirim tanpa nomor bisa "cocok" dengan
        // owner tanpa nomor - keduanya menjadi string kosong.
        $owner = new User;
        $owner->id = 1;
        $owner->wa_number = null;
        $owner->wa_is_verified = true;

        $profile = new HermesProfile;
        $profile->type = 'primary';
        $profile->setRelation('owner', $owner);

        $eval = $this->filter->evaluate($profile, '@s.whatsapp.net', '', 'Halo', null, false);

        $this->assertFalse($eval['allow']);
    }

    public function test_local_prefix_is_normalised_on_both_sides(): void
    {
        $owner = $this->owner(number: '081234567890');

        $profile = new HermesProfile;
        $profile->type = 'primary';
        $profile->setRelation('owner', $owner);

        // Owner tersimpan dengan awalan 08, pengirim datang sebagai 628.
        $eval = $this->filter->evaluate(
            $profile,
            '6281234567890@s.whatsapp.net',
            '6281234567890',
            'Cek stok',
            null,
            false
        );

        $this->assertTrue($eval['allow']);
    }

    private function owner(string $number = '6281234567890', bool $verified = true): User
    {
        $owner = new User;
        $owner->id = 1;
        $owner->wa_number = $number;
        $owner->wa_is_verified = $verified;

        return $owner;
    }
}
