<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachments_can_morph_to_contact_and_respect_tenant_isolation(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $contactA = Contact::factory()->create(['company_id' => $companyA->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyB->id]);

        $attachmentA = Attachment::factory()->create([
            'company_id' => $companyA->id,
            'attachable_type' => Contact::class,
            'attachable_id' => $contactA->id,
            'file_name' => 'KTP_A.pdf',
        ]);

        $attachmentB = Attachment::factory()->create([
            'company_id' => $companyB->id,
            'attachable_type' => Contact::class,
            'attachable_id' => $contactB->id,
            'file_name' => 'KTP_B.pdf',
        ]);

        $this->assertEquals(2, Attachment::count());

        $this->assertEquals(1, $contactA->attachments()->count());
        $this->assertEquals(1, $contactB->attachments()->count());

        $this->assertEquals('KTP_A.pdf', $contactA->attachments->first()->file_name);
        $this->assertEquals($companyA->id, $contactA->attachments->first()->company_id);
    }
}
