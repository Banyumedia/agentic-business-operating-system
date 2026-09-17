<?php

namespace Tests;

use App\Http\Middleware\SetCurrentCompany;
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
        }
    }
}
