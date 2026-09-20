<?php

use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\EnsureFeatureEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Redirect authenticated users trying to access guest routes (login/register).
        // If user has company → app.dashboard. If not → onboarding.
        $middleware->redirectUsersTo(function (Request $request) {
            $user = auth()->user();
            if (! $user) {
                return route('login');
            }

            // User sudah punya company (sebagai owner)
            if ($user->companies()->exists()) {
                return route('app.dashboard');
            }

            // User belum punya company → arahkan ke onboarding
            return route('onboarding');
        });

        $middleware->alias([
            'company.access' => EnsureCompanyAccess::class,
            'feature.enabled' => EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
