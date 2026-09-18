<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoutSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);
        config(['datasource.driver' => 'eloquent']);
    }

    public function test_contact_is_searchable(): void
    {
        $contact = Contact::factory()->create(['name' => 'John Doe']);
        $searchableArray = $contact->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('company_id', $searchableArray);
        $this->assertEquals('John Doe', $searchableArray['name']);
    }

    public function test_deal_is_searchable(): void
    {
        $deal = Deal::factory()->create(['title' => 'Big Project Deal']);
        $searchableArray = $deal->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('company_id', $searchableArray);
        $this->assertEquals('Big Project Deal', $searchableArray['title']);
    }

    public function test_item_is_searchable(): void
    {
        $company = Company::factory()->create();
        $item = Item::create(['company_id' => $company->id, 'type' => 'product', 'name' => 'Wrench Set']);
        $searchableArray = $item->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('company_id', $searchableArray);
        $this->assertEquals('Wrench Set', $searchableArray['name']);
    }

    public function test_invoice_is_searchable(): void
    {
        $invoice = Invoice::factory()->create(['order_id' => 'INV-2026-001']);
        $searchableArray = $invoice->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('company_id', $searchableArray);
        $this->assertEquals('INV-2026-001', $searchableArray['order_id']);
    }

    public function test_project_is_searchable(): void
    {
        $project = Project::factory()->create(['name' => 'Office Renovation']);
        $searchableArray = $project->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('company_id', $searchableArray);
        $this->assertEquals('Office Renovation', $searchableArray['name']);
    }
}
