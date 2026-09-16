<?php

namespace App\Providers;

use App\Contracts\CompanyContext;
use App\Services\ThemeRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['components.layouts.module', 'layouts.app'], function ($view): void {
            try {
                $company = app(CompanyContext::class)->current();
                $theme = ThemeRegistry::forCompany($company);
            } catch (InvalidArgumentException|LogicException) {
                $theme = 'a';
            }

            $view->with('theme', $theme);
        });
    }
}
