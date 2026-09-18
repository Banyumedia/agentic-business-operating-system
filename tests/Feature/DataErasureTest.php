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
}
