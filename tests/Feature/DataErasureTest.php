<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Settings\DataErasure;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class DataErasureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('datasource.driver', 'eloquent');
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
    }

    public function test_owner_can_erase_contact_data_and_anonymize_deals(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'slug' => 'demo-company',
        ]);

        $contact = Contact::factory()->create([
            'company_id' => $company->id,
            'name' => 'Budi Santoso',
        ]);

        $deal = Deal::factory()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'title' => 'Proyek Budi',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $component = Livewire::test(DataErasure::class)
            ->set('contactName', 'Budi Santoso')
            ->set('confirmationCode', 'ya ')
            ->call('erase');

        $this->assertEquals('success', $component->get('feedbackType'), $component->get('feedback'));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);

        $deal->refresh();
        $this->assertNull($deal->contact_id);

        $this->assertDatabaseHas('access_logs', [
            'company_id' => $company->id,
            'action' => 'erasure',
            'subject_type' => Contact::class,
        ]);
    }

    public function test_requires_exact_confirmation_code(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Livewire::test(DataErasure::class)
            ->set('contactName', 'Budi Santoso')
            ->set('confirmationCode', 'Y')
            ->call('erase')
            ->assertSet('feedbackType', 'error')
            ->assertSee('Ketik YA');
    }

    public function test_non_owner_is_rejected_without_mutation(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create();
        $staff->update(['current_company_id' => $company->id]);

        $contact = Contact::factory()->create([
            'company_id' => $company->id,
            'name' => 'Budi Santoso',
        ]);

        $this->actingAs($staff);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Livewire::test(DataErasure::class)
            ->set('contactName', 'Budi Santoso')
            ->set('confirmationCode', 'YA')
            ->call('erase')
            ->assertStatus(403);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('access_logs', [
            'company_id' => $company->id,
            'action' => 'erasure',
        ]);
    }

    public function test_erasure_tab_is_deep_linkable_and_renders_for_owner(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $response = $this->get('/app/settings/erasure');
        $response->assertOk();
        $this->assertStringContainsString('id="tab-erasure"', $response->getContent());
        $this->assertStringContainsString('Hapus Data', $response->getContent());
    }

    public function test_erasure_tab_is_absent_from_staff_dom(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        // Akses /app milik owner; DOM staf diverifikasi lewat sesi role staff
        // pada datasource JSON (lihat SettingsCapabilityTabsTest) - di sini
        // cukup bukti non-owner ditolak penuh lewat middleware.
        $intruder = User::factory()->create();
        $intruder->update(['current_company_id' => $company->id]);

        $this->actingAs($intruder);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $this->get('/app/settings')->assertStatus(403);
    }
}
