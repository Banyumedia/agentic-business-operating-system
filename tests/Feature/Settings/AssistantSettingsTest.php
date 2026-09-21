<?php

namespace Tests\Feature\Settings;

use App\Contracts\CompanyContext;
use App\Livewire\Settings\AssistantSettings;
use App\Models\Company;
use App\Models\HermesConversationContext;
use App\Models\HermesProfile;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);

        $this->owner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);

        $this->staff = User::factory()->create([
            'email' => 'staff@example.com',
        ]);

        $this->company = Company::factory()->create([
            'name' => 'Klinik Sehat',
            'slug' => 'klinik-sehat',
            'owner_user_id' => $this->owner->id,
        ]);
    }

    public function test_assistant_settings_mounts_with_defaults(): void
    {
        $this->actingAs($this->owner);
        session([
            'active_company' => (string) $this->company->id,
            'company_role' => 'owner',
        ]);

        Livewire::test(AssistantSettings::class)
            ->assertSee('Karyawan AI (Hermes Control Center)')
            ->assertSee('Aturan SOP (Markdown)')
            ->assertSee('Batas Diskon Maksimal Kasir (%)')
            ->assertSet('maxDiscountPercent', 10)
            ->assertSet('groupTagOnly', true);
    }

    public function test_owner_can_save_sop_and_guardrail(): void
    {
        $this->actingAs($this->owner);
        session([
            'active_company' => (string) $this->company->id,
            'company_role' => 'owner',
        ]);

        $customSop = "### SOP Khusus Klinik\n1. Selalu sapa pasien dengan sopan.\n2. Catat rekam medis di kontak.";

        Livewire::test(AssistantSettings::class)
            ->set('sopMarkdown', $customSop)
            ->set('maxDiscountPercent', 15)
            ->set('operatingHours', '07:00 - 21:00')
            ->set('groupTagOnly', true)
            ->call('saveSop')
            ->assertHasNoErrors()
            ->assertSee('Aturan kerja dan SOP Karyawan AI berhasil disimpan.');

        $setting = ModuleSetting::where('company_id', $this->company->id)
            ->where('module_name', 'assistant')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($customSop, $setting->settings_json['sop_markdown']);
        $this->assertSame(15, $setting->settings_json['max_discount_percent']);
        $this->assertSame('07:00 - 21:00', $setting->settings_json['operating_hours']);
        $this->assertTrue($setting->settings_json['group_tag_only']);
    }

    public function test_staff_cannot_save_sop(): void
    {
        $this->actingAs($this->owner);
        session([
            'active_company' => (string) $this->company->id,
            'company_role' => 'staff',
        ]);

        Livewire::test(AssistantSettings::class)
            ->set('maxDiscountPercent', 50)
            ->call('saveSop')
            ->assertStatus(403);
    }

    public function test_displays_profiles_and_active_groups(): void
    {
        $this->actingAs($this->owner);
        session([
            'active_company' => (string) $this->company->id,
            'company_role' => 'owner',
        ]);

        $primaryProfile = HermesProfile::create([
            'owner_user_id' => $this->owner->id,
            'type' => 'primary',
            'instance_id' => 'inst_primary_01',
            'webhook_secret_reference' => 'sec_ref_01',
            'label' => 'Asisten Klinik Utama',
            'status' => 'connected',
        ]);

        HermesConversationContext::create([
            'hermes_profile_id' => $primaryProfile->id,
            'channel' => 'whatsapp',
            'chat_id' => '120363012345678@g.us',
            'active_company_id' => $this->company->id,
            'updated_at' => now(),
        ]);

        Livewire::test(AssistantSettings::class)
            ->set('activeSubTab', 'groups')
            ->assertSee('Nomor 1: Operasional Internal')
            ->assertSee('Asisten Klinik Utama')
            ->assertSee('120363012345678@g.us')
            ->assertSee('1 dari');
    }
}
