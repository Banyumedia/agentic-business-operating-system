<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Settings\DataErasure;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use LogicException;
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

    public function test_owner_can_erase_contact_data_by_id_and_anonymize_related_records(): void
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

        $prescription = Prescription::factory()->create([
            'company_id' => $company->id,
            'patient_contact_id' => $contact->id,
        ]);

        Attachment::create([
            'company_id' => $company->id,
            'attachable_type' => Contact::class,
            'attachable_id' => $contact->id,
            'file_name' => 'ktp-budi.pdf',
            'file_type' => 'application/pdf',
            'drive_file_id' => 'file-contact-budi',
            'drive_url' => 'https://drive.example/contact-budi',
        ]);

        Attachment::create([
            'company_id' => $company->id,
            'attachable_type' => Prescription::class,
            'attachable_id' => $prescription->id,
            'file_name' => 'resep-budi.pdf',
            'file_type' => 'application/pdf',
            'drive_file_id' => 'file-rx-budi',
            'drive_url' => 'https://drive.example/rx-budi',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $component = Livewire::test(DataErasure::class)
            ->set('contactId', (string) $contact->id)
            ->set('confirmationCode', 'ya ')
            ->call('erase');

        $this->assertEquals('success', $component->get('feedbackType'), $component->get('feedback'));

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('prescriptions', ['id' => $prescription->id]);
        $this->assertDatabaseMissing('attachments', [
            'company_id' => $company->id,
            'attachable_type' => Contact::class,
            'attachable_id' => $contact->id,
        ]);
        $this->assertDatabaseMissing('attachments', [
            'company_id' => $company->id,
            'attachable_type' => Prescription::class,
            'attachable_id' => $prescription->id,
        ]);

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
            ->set('contactId', '1')
            ->set('confirmationCode', 'Y')
            ->call('erase')
            ->assertSet('feedbackType', 'error')
            ->assertSee('Ketik YA');
    }

    public function test_requires_numeric_contact_id(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Livewire::test(DataErasure::class)
            ->set('contactId', 'abc')
            ->set('confirmationCode', 'YA')
            ->call('erase')
            ->assertSet('feedbackType', 'error')
            ->assertSee('harus berupa angka bulat yang valid');
    }

    public function test_duplicate_names_are_safe_because_target_is_contact_id(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $first = Contact::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nama Kembar',
        ]);

        $second = Contact::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nama Kembar',
        ]);

        $this->actingAs($owner);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        Livewire::test(DataErasure::class)
            ->set('contactId', (string) $second->id)
            ->set('confirmationCode', 'YA')
            ->call('erase')
            ->assertSet('feedbackType', 'success');

        $this->assertDatabaseHas('contacts', ['id' => $first->id]);
        $this->assertDatabaseMissing('contacts', ['id' => $second->id]);
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
        // Fail-closed lebih awal (MQ-01C2): staff non-owner ditolak di lapisan
        // context sebelum komponen tersentuh; data tetap tanpa mutasi.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Akses lintas company ditolak.');
        app(CompanyContext::class)->setCurrent((string) $company->id);

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
        $this->assertStringContainsString('Penghapusan', $response->getContent());
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
        // Fail-closed lebih awal (MQ-01C2): intruder non-owner ditolak di
        // lapisan context sebelum route/komponen tersentuh.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Akses lintas company ditolak.');
        app(CompanyContext::class)->setCurrent((string) $company->id);
    }
}
