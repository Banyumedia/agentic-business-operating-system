<?php

namespace App\Providers;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use Illuminate\Support\ServiceProvider;
use LogicException;

class DataSourceServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const JSON_BINDINGS = [
        EntityRepository::class => 'App\\Services\\Json\\JsonEntityRepository',
        PresetSource::class => 'App\\Services\\Json\\JsonPresetSource',
        CompanyContext::class => 'App\\Services\\Json\\JsonCompanyContext',
    ];

    /** @var array<class-string, class-string> */
    private const ELOQUENT_BINDINGS = [
        EntityRepository::class => 'App\\Services\\Eloquent\\EloquentEntityRepository',
        PresetSource::class => 'App\\Services\\Eloquent\\EloquentPresetSource',
        CompanyContext::class => 'App\\Services\\Eloquent\\EloquentCompanyContext',
    ];

    public function register(): void
    {
        $driver = config('datasource.driver');

        if ($driver === 'json') {
            foreach (self::JSON_BINDINGS as $contract => $implementation) {
                $this->app->bind($contract, $implementation);
            }

            return;
        }

        if ($driver === 'eloquent') {
            foreach (self::ELOQUENT_BINDINGS as $contract => $implementation) {
                $this->app->bind($contract, function () use ($implementation): never {
                    throw new LogicException(
                        "DATA_SOURCE=eloquent belum tersedia sebelum Fase 3: $implementation",
                    );
                });
            }

            return;
        }

        throw new LogicException('DATA_SOURCE tidak didukung: '.(is_scalar($driver) ? $driver : gettype($driver)));
    }
}
