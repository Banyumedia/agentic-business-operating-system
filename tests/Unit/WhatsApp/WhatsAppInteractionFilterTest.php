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
        $owner = new User;
        $owner->id = 1;
        $owner->phone = '6281234567890';

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
        $owner = new User;
        $owner->id = 1;
        $owner->phone = '6281234567890';

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
}
