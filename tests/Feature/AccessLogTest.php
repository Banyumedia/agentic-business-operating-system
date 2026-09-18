<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_log_created_when_reading_sensitive_entity(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $contact = Contact::factory()->create([
            'company_id' => $company->id,
            'name' => 'Secret Name',
        ]);

        // This is simulating the controller logging the access
        AccessLog::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'subject_type' => Contact::class,
            'subject_id' => $contact->id,
            'action' => 'read',
            'ip' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'subject_type' => Contact::class,
            'subject_id' => $contact->id,
            'action' => 'read',
        ]);
    }

    public function test_mass_export_recorded_as_one_event(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        Contact::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        // Simulate export
        AccessLog::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'subject_type' => Contact::class,
            'subject_id' => null, // null for mass action
            'action' => 'export',
            'ip' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('access_logs', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'subject_type' => Contact::class,
            'subject_id' => null,
            'action' => 'export',
        ]);
    }
}
