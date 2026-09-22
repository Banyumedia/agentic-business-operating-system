<?php

namespace App\Providers;

use App\Contracts\CompanyContext;
use App\Contracts\HermesNodeClient;
use App\Http\Middleware\EnsureCompanyAccess;
use App\Http\Middleware\SetCurrentCompany;
use App\Services\CompanyPresetResolver;
use App\Services\FeatureResolver;
use App\Services\Hermes\PlatformHermesNodeClient;
use App\Services\TerminologyResolver;
use App\Services\ThemeRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Livewire\Livewire;
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
        // Lajur WhatsApp platform (dunning langganan D-23/D-49). Sebelum T-69 ini
        // dibind ke `FakeHermesNodeClient` yang hanya menulis log lalu
        // mengembalikan `true`, sehingga tidak ada pelanggan yang pernah menerima
        // peringatan dan tidak ada yang tahu. Fake-nya tetap ada dan dipakai test
        // yang memang menguji tangga dunning, bukan transportnya - tetapi harus
        // **dinyatakan** di test itu, bukan menjadi bawaan aplikasi.
        $this->app->singleton(HermesNodeClient::class, PlatformHermesNodeClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Request Livewire update (POST /livewire/update) tidak melewati
        // grup middleware route asal; tanpa ini cek tenant hanya jalan pada
        // request HTTP biasa. Daftarkan sebagai persistent middleware supaya
        // Livewire menerapkannya ulang pada setiap request update (MQ-01C2).
        Livewire::addPersistentMiddleware([
            SetCurrentCompany::class,
            EnsureCompanyAccess::class,
        ]);

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
