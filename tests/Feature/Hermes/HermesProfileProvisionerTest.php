<?php

namespace Tests\Feature\Hermes;

use App\Models\HermesProfile;
use App\Models\User;
use App\Services\Hermes\HermesProfileProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HermesProfileProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private HermesProfileProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'owner_provision@example.com']);
        $this->provisioner = new HermesProfileProvisioner;
    }

    public function test_ensures_primary_profile_is_idempotent(): void
    {
        $profile1 = $this->provisioner->ensurePrimaryProfile($this->owner);
        $this->assertSame('primary', $profile1->type);
        $this->assertSame($this->owner->id, $profile1->owner_user_id);

        $profile2 = $this->provisioner->ensurePrimaryProfile($this->owner);
        $this->assertSame($profile1->id, $profile2->id);
        $this->assertCount(1, HermesProfile::where('owner_user_id', $this->owner->id)->get());
    }

    public function test_can_provision_addon_cs_profile(): void
    {
        $addon = $this->provisioner->provisionAddonCsProfile($this->owner, 99, 'CS Toko Utama');
        $this->assertSame('addon', $addon->type);
        $this->assertSame('CS Toko Utama', $addon->label);
        $this->assertSame(99, $addon->billing_addon_id);
        $this->assertSame($this->owner->id, $addon->owner_user_id);
    }

    public function test_tool_scoping_primary_allows_erp_and_blocks_os_tools(): void
    {
        // Primary diizinkan aksi transaksi & reminder
        $this->assertTrue($this->provisioner->isToolAllowed('primary', 'create_transaction'));
        $this->assertTrue($this->provisioner->isToolAllowed('primary', 'set_reminder'));

        // Primary DILARANG aksi shell/terminal/file
        $this->assertFalse($this->provisioner->isToolAllowed('primary', 'terminal'));
        $this->assertFalse($this->provisioner->isToolAllowed('primary', 'write_file'));
        $this->assertFalse($this->provisioner->isToolAllowed('primary', 'git'));
    }

    public function test_tool_scoping_addon_is_read_only_and_blocks_mutations(): void
    {
        // Addon CS hanya diizinkan katalog dan cek order
        $this->assertTrue($this->provisioner->isToolAllowed('addon', 'search_catalog'));
        $this->assertTrue($this->provisioner->isToolAllowed('addon', 'check_my_order'));

        // Addon CS DILARANG mutasi data, diskon, accounting, dan OS tools
        $this->assertFalse($this->provisioner->isToolAllowed('addon', 'create_transaction'));
        $this->assertFalse($this->provisioner->isToolAllowed('addon', 'update_settings'));
        $this->assertFalse($this->provisioner->isToolAllowed('addon', 'view_accounting'));
        $this->assertFalse($this->provisioner->isToolAllowed('addon', 'terminal'));
    }
}
