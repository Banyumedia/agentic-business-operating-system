<?php

namespace App\Providers;

use App\Contracts\CompanyContext;
use App\Contracts\HermesNodeClient;
use App\Services\CompanyPresetResolver;
use App\Services\FeatureResolver;
use App\Services\Hermes\FakeHermesNodeClient;
use App\Services\TerminologyResolver;
use App\Services\ThemeRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
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
        require_once app_path('Support/helpers.php');

        $this->app->scoped(CompanyPresetResolver::class);
        $this->app->scoped(FeatureResolver::class);
        $this->app->scoped(TerminologyResolver::class);
        $this->app->singleton(HermesNodeClient::class, FakeHermesNodeClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Blade::directive('term', fn (string $expression): string => "<?php echo e(term({$expression})); ?>");

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
