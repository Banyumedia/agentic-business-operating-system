<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Providers\DataSourceServiceProvider;
use LogicException;
use Tests\TestCase;

class DataSourceBindingTest extends TestCase
{
    public function test_json_driver_binds_every_contract_to_its_json_implementation(): void
    {
        $expected = [
            EntityRepository::class => 'App\\Services\\Json\\JsonEntityRepository',
            PresetSource::class => 'App\\Services\\Json\\JsonPresetSource',
            CompanyContext::class => 'App\\Services\\Json\\JsonCompanyContext',
        ];

        foreach ($expected as $contract => $implementation) {
            $this->assertTrue($this->app->bound($contract));

            $binding = $this->app->getBindings()[$contract]['concrete'];
            $concrete = (new \ReflectionFunction($binding))->getStaticVariables()['concrete'];

            $this->assertSame($implementation, $concrete);
        }
    }

    public function test_eloquent_driver_binds_every_contract_to_its_eloquent_implementation(): void
    {
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        $expected = [
            EntityRepository::class => 'App\Services\Eloquent\EloquentEntityRepository',
            PresetSource::class => 'App\Services\Preset\EloquentPresetSource',
            CompanyContext::class => 'App\Services\Eloquent\EloquentCompanyContext',
        ];

        foreach ($expected as $contract => $implementation) {
            $this->assertTrue($this->app->bound($contract));

            $binding = $this->app->getBindings()[$contract]['concrete'];
            $concrete = (new \ReflectionFunction($binding))->getStaticVariables()['concrete'];

            $this->assertSame($implementation, $concrete);
        }
    }

    public function test_unknown_driver_is_rejected(): void
    {
        config(['datasource.driver' => 'unknown']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DATA_SOURCE tidak didukung: unknown');

        (new DataSourceServiceProvider($this->app))->register();
    }
}
