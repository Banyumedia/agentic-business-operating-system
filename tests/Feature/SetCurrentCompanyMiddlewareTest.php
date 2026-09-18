<?php

namespace Tests\Feature;

use App\Http\Middleware\SetCurrentCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SetCurrentCompanyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_current_company_if_null()
    {
        $user = User::factory()->create(['current_company_id' => null]);
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $request = Request::create('/app/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new SetCurrentCompany;

        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals($company->id, $user->fresh()->current_company_id);
    }

    public function test_it_does_not_override_existing_current_company()
    {
        $company1 = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company1->id]);
        $company2 = Company::factory()->create(['owner_user_id' => $user->id]);

        $request = Request::create('/app/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new SetCurrentCompany;

        $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals($company1->id, $user->fresh()->current_company_id);
    }

    public function test_it_ignores_guest()
    {
        $request = Request::create('/app/dashboard', 'GET');

        $middleware = new SetCurrentCompany;

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
