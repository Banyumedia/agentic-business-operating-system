<?php

namespace Tests;

use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\SetCurrentCompany;
use App\Services\PlanCapabilityGate;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The legacy JSON demo tests expect to reach the application routes directly
        // without authenticating, as they used a generic context switch.
        // If a test class uses RefreshDatabase, we assume it's testing actual models/auth.
        if (! in_array(RefreshDatabase::class, class_uses_recursive($this))) {
            $this->withoutMiddleware(Authenticate::class);
            $this->withoutMiddleware(SetCurrentCompany::class);
            $this->withoutMiddleware(EnsureCompanyAccess::class);

            // Mock PlanCapabilityGate to bypass DB queries in these non-Eloquent tests
            $gate = $this->mock(PlanCapabilityGate::class);
            $gate->shouldReceive('allowedCapabilities')->andReturn([]);
            $this->app->instance(PlanCapabilityGate::class, $gate);
        }
    }
}
