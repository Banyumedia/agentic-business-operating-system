<?php

namespace App\Providers;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use Illuminate\Support\ServiceProvider;
use LogicException;

class DataSourceServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const JSON_BINDINGS = [
        EntityRepository::class => 'App\Services\Json\JsonEntityRepository',
        PresetSource::class => 'App\Services\Json\JsonPresetSource',
        CompanyContext::class => 'App\Services\Json\JsonCompanyContext',
        CompanySettingsStore::class => 'App\Services\Json\JsonCompanySettingsStore',
    ];

    /** @var array<class-string, class-string> */
    private const ELOQUENT_BINDINGS = [
        EntityRepository::class => 'App\Services\Eloquent\EloquentEntityRepository',
        PresetSource::class => 'App\Services\Preset\EloquentPresetSource',
        CompanyContext::class => 'App\Services\Eloquent\EloquentCompanyContext',
        CompanySettingsStore::class => 'App\Services\Eloquent\EloquentCompanySettingsStore',
    ];

    public function register(): void
    {
        $driver = config('datasource.driver');

        if ($driver === 'json') {
            foreach (self::JSON_BINDINGS as $contract => $implementation) {
                if ($contract === CompanyContext::class || $contract === CompanySettingsStore::class) {
                    $this->app->scoped($contract, $implementation);
                } else {
                    $this->app->bind($contract, $implementation);
                }
            }

            return;
        }

        if ($driver === 'eloquent') {
            foreach (self::ELOQUENT_BINDINGS as $contract => $implementation) {
                if ($contract === CompanyContext::class || $contract === CompanySettingsStore::class) {
                    $this->app->scoped($contract, $implementation);
                } else {
                    $this->app->bind($contract, $implementation);
                }
            }

            return;
        }

        throw new LogicException('DATA_SOURCE tidak didukung: '.(is_scalar($driver) ? $driver : gettype($driver)));
    }
}
