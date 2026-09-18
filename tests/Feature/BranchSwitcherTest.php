<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\BranchSwitcher;
use App\Models\Company;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agentic.data_source' => 'eloquent']);
        $this->app->bind(CompanyContext::class, EloquentCompanyContext::class);
    }

    public function test_shows_branches_owned_by_user()
    {
        $user = User::factory()->create();
        $hq = Company::factory()->create(['owner_user_id' => $user->id, 'name' => 'HQ']);
        $branch = Company::factory()->create(['owner_user_id' => $user->id, 'parent_company_id' => $hq->id, 'name' => 'Branch 1']);

        $user->current_company_id = $hq->id;
        $user->save();

        app(CompanyContext::class)->setCurrent((string) $hq->id);

        Livewire::actingAs($user)
            ->test(BranchSwitcher::class)
            ->assertSee('HQ')
            ->assertSee('Branch 1');
    }

    public function test_switches_branch()
    {
        $user = User::factory()->create();
        $hq = Company::factory()->create(['owner_user_id' => $user->id, 'name' => 'HQ']);
        $branch = Company::factory()->create(['owner_user_id' => $user->id, 'parent_company_id' => $hq->id, 'name' => 'Branch 1']);

        $user->current_company_id = $hq->id;
        $user->save();
        app(CompanyContext::class)->setCurrent((string) $hq->id);

        Livewire::actingAs($user)
            ->test(BranchSwitcher::class)
            ->call('switchBranch', $branch->id)
            ->assertRedirect('/app');

        $this->assertEquals($branch->id, $user->fresh()->current_company_id);
    }

    public function test_rejects_switching_to_unowned_company()
    {
        $user = User::factory()->create();
        $hq = Company::factory()->create(['owner_user_id' => $user->id, 'name' => 'HQ']);

        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create(['owner_user_id' => $otherUser->id]);

        $user->current_company_id = $hq->id;
        $user->save();
        app(CompanyContext::class)->setCurrent((string) $hq->id);

        Livewire::actingAs($user)
            ->test(BranchSwitcher::class)
            ->call('switchBranch', $otherCompany->id)
            ->assertNoRedirect(); // Should not redirect or change company

        $this->assertEquals($hq->id, $user->fresh()->current_company_id);
    }
}
