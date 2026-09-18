<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\CommandPalette;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class EloquentCommandPaletteSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_scout_search_returns_eloquent_results_for_active_company()
    {
        config(['datasource.driver' => 'eloquent']);
        config(['scout.driver' => 'collection']); // Use collection driver for testing

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Target hits for Company A
        Contact::factory()->create(['company_id' => $companyA->id, 'name' => 'Alice Appleseed', 'email' => 'alice@example.com']);
        Project::factory()->create(['company_id' => $companyA->id, 'name' => 'Alpha Project']);

        // Noise hits for Company A (doesn't match 'Al')
        Contact::factory()->create(['company_id' => $companyA->id, 'name' => 'Bob Builder', 'email' => 'bob@example.com']);

        // Hits for Company B (matches 'Al' but wrong company)
        Contact::factory()->create(['company_id' => $companyB->id, 'name' => 'Albert Einstein', 'email' => 'albert@example.com']);

        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $companyA->id);
        $mockContext->shouldReceive('preset')->andReturn('agency');
        $this->app->instance(CompanyContext::class, $mockContext);

        Livewire::test(CommandPalette::class)
            ->set('search', 'Al')
            ->assertViewHas('results', function ($results) {
                $dataResults = collect($results)->where('type', 'Data');

                if ($dataResults->count() !== 2) {
                    dump($dataResults->pluck('title')->all());

                    return false;
                }

                $titles = $dataResults->pluck('title')->all();

                return in_array('Alice Appleseed', $titles) && in_array('Alpha Project', $titles);
            });
    }
}
