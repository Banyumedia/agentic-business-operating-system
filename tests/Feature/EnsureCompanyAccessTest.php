<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureCompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_guest(): void
    {
        $request = Request::create('/app/dashboard');
        $middleware = new EnsureCompanyAccess;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('');

        $middleware->handle($request, fn () => response('OK'));
    }

    public function test_rejects_user_without_current_company(): void
    {
        $user = User::factory()->create(['current_company_id' => null]);

        $request = Request::create('/app/dashboard');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureCompanyAccess;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No active company selected');

        $middleware->handle($request, fn () => response('OK'));
    }

    public function test_rejects_user_accessing_others_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $otherUser->id]);

        $user->update(['current_company_id' => $company->id]);

        $request = Request::create('/app/dashboard');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureCompanyAccess;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You do not have access to this company');

        $middleware->handle($request, fn () => response('OK'));
    }

    public function test_allows_user_accessing_own_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $user->update(['current_company_id' => $company->id]);

        $request = Request::create('/app/dashboard');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureCompanyAccess;

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
