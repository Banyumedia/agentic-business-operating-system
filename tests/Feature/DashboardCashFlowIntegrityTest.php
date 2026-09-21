<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Contracts\PresetSource;
use App\Services\Dashboard\CashFlowCalculator;
use App\Services\Dashboard\DashboardComposer;
use App\Services\Dashboard\WidgetRegistry;
use App\Services\FeatureResolver;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use UnexpectedValueException;

class DashboardCashFlowIntegrityTest extends TestCase
{
    /** @return array<string, array{0: string, 1: mixed}> */
    public static function invalidCashRows(): array
    {
        return [
            'direction bukan in/out' => ['invalid', 50000],
            'amount non-numerik' => ['in', 'not-numeric'],
            'amount negatif' => ['in', -10000],
            'amount non-finite' => ['in', INF],
            'amount di luar decimal 18 2' => ['in', '10000000000000000.00'],
            'float di luar presisi eksak' => ['in', 9999999999999999.99],
            'float dengan jarak representasi di atas satu sen' => ['in', 90071992547409.90],
        ];
    }

    #[DataProvider('invalidCashRows')]
    public function test_cashflow_widget_fails_closed_independently(string $direction, mixed $amount): void
    {
        $this->mock(CompanyContext::class, function (MockInterface $mock): void {
            $mock->shouldReceive('current')->once()->andReturn('tenant-a');
            $mock->shouldReceive('displayName')->andReturn('Tenant A');
        });
        $this->mock(FeatureResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('enabled')->once()->with('finance.cashbook')->andReturnTrue();
        });
        $this->mock(EntityRepository::class, function (MockInterface $mock) use ($direction, $amount): void {
            $mock->shouldReceive('for')->once()->with('tenant-a', 'cash_entries')->andReturnSelf();
            $mock->shouldReceive('all')->once()->andReturn([
                ['direction' => 'in', 'amount' => 100000],
                ['direction' => $direction, 'amount' => $amount],
            ]);
        });

        $this->expectException(UnexpectedValueException::class);
        app(WidgetRegistry::class)->compose('kpi_cashflow');
    }

    #[DataProvider('invalidCashRows')]
    public function test_dashboard_kpi_fails_closed_independently(string $direction, mixed $amount): void
    {
        $this->mock(CompanyContext::class, function (MockInterface $mock): void {
            $mock->shouldReceive('current')->andReturn('tenant-a');
            $mock->shouldReceive('displayName')->andReturn('Tenant A');
            $mock->shouldReceive('preset')->once()->andReturn('preset-a');
        });
        $this->mock(PresetSource::class, function (MockInterface $mock): void {
            $mock->shouldReceive('find')->once()->with('preset-a')->andReturn([
                'dashboard' => ['industry_zone' => []],
            ]);
        });
        $this->mock(EntityRepository::class, function (MockInterface $mock) use ($direction, $amount): void {
            $mock->shouldReceive('for')->once()->with('tenant-a', 'cash_entries')->andReturnSelf();
            $mock->shouldReceive('all')->once()->andReturn([
                ['direction' => 'in', 'amount' => 100000],
                ['direction' => $direction, 'amount' => $amount],
            ]);
        });

        $this->expectException(UnexpectedValueException::class);
        app(DashboardComposer::class)->compose();
    }

    public function test_decimal_18_2_precision_is_preserved_exactly(): void
    {
        $calculator = app(CashFlowCalculator::class);
        $flow = $calculator->calculate([
            ['direction' => 'in', 'amount' => '9999999999999999.99'],
            ['direction' => 'out', 'amount' => '1.00'],
        ]);

        $this->assertSame(999999999999999999, $flow['incoming_cents']);
        $this->assertSame(100, $flow['outgoing_cents']);
        $this->assertSame(999999999999999899, $flow['balance_cents']);
        $this->assertSame('Rp 9.999.999.999.999.998,99', $calculator->formatRupiah($flow['balance_cents']));
    }

    public function test_aggregate_overflow_is_rejected(): void
    {
        $entries = array_fill(0, 10, ['direction' => 'in', 'amount' => '9999999999999999.99']);

        $this->expectException(UnexpectedValueException::class);
        app(CashFlowCalculator::class)->calculate($entries);
    }
}
