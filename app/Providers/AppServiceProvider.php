<?php

namespace App\Providers;

use App\Services\ThemeRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            $company = (string) session('active_company', 'usaha-demo');

            $view->with('theme', ThemeRegistry::forCompany($company));
        });
    }
}
