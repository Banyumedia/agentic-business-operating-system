<?php

namespace Tests\Feature\Analytics;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\Analytics\BusinessHealthAnalyzer;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class BusinessHealthAnalyzerTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/json-'.bin2hex(random_bytes(5)));
        config([
            'datasource.driver' => 'json',
            'datasource.json_path' => $this->jsonPath,
            'datasource.demo_companies' => ['bengkel-arka', 'klinik-sehat'],
            'analytics.trend_threshold_percent' => 3.0,
            'analytics.min_data_points' => 2,
        ]);

        $this->app->forgetInstance(CompanyContext::class);
        $this->app->forgetInstance(EntityRepository::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeEntries(string $company, array $rows): void
    {
        (new Filesystem)->ensureDirectoryExists($this->jsonPath.'/'.$company);
        file_put_contents(
            $this->jsonPath.'/'.$company.'/cash_entries.json',
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );
    }

    /** @return array<string, mixed> */
    private function entry(int $id, string $date, string $direction, float $amount): array
    {
        return [
            'id' => $id,
            'entry_date' => $date,
            'direction' => $direction,
            'amount' => $amount,
        ];
    }

    private function analyzer(): BusinessHealthAnalyzer
    {
        return $this->app->make(BusinessHealthAnalyzer::class);
    }

    public function test_revenue_expenses_margin_computed_from_known_entries(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-09-05', 'in', 1_000_000),
            $this->entry(2, '2026-09-10', 'in', 500_000),
            $this->entry(3, '2026-09-12', 'out', 300_000),
            $this->entry(4, '2026-09-20', 'out', 200_000),
        ]);

        $result = $this->analyzer()->analyze('2026-09-25');

        $this->assertFalse($result['insufficient_data']);
        $this->assertSame(1_500_000.0, $result['revenue']);
        $this->assertSame(500_000.0, $result['expenses']);
        $this->assertSame(1_000_000.0, $result['margin']);
    }

    public function test_trend_up_down_and_flat_respect_config_threshold(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        // Naik jauh: bulan ini 2.000.000 vs bulan lalu 1.000.000 (+100%).
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-08-15', 'in', 1_000_000),
            $this->entry(2, '2026-09-15', 'in', 2_000_000),
        ]);
        $result = $this->analyzer()->analyze('2026-09-25');
        $this->assertSame('up', $result['trend']['direction']);
        $this->assertSame(100.0, $result['trend']['percent']);

        // Datar: +2% di bawah ambang 3%.
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-08-15', 'in', 1_000_000),
            $this->entry(2, '2026-09-15', 'in', 1_020_000),
        ]);
        $result = $this->analyzer()->analyze('2026-09-25');
        $this->assertSame('flat', $result['trend']['direction']);

        // Turun: -50%.
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-08-15', 'in', 2_000_000),
            $this->entry(2, '2026-09-15', 'in', 1_000_000),
        ]);
        $result = $this->analyzer()->analyze('2026-09-25');
        $this->assertSame('down', $result['trend']['direction']);
        $this->assertSame(-50.0, $result['trend']['percent']);
    }

    public function test_empty_data_returns_insufficient_data_without_error(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $result = $this->analyzer()->analyze('2026-09-25');

        $this->assertTrue($result['insufficient_data']);
        $this->assertSame(0.0, $result['revenue']);
        $this->assertSame(0.0, $result['expenses']);
        $this->assertSame(0.0, $result['margin']);
        $this->assertSame([], $result['highlights']);
    }

    public function test_single_entry_is_below_min_data_points(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-09-15', 'in', 1_000_000),
        ]);

        $result = $this->analyzer()->analyze('2026-09-25');

        $this->assertTrue($result['insufficient_data']);
    }

    public function test_other_company_data_never_leaks_into_analysis(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-09-15', 'in', 1_000_000),
            $this->entry(2, '2026-09-16', 'in', 1_000_000),
        ]);
        (new Filesystem)->ensureDirectoryExists($this->jsonPath.'/klinik-sehat');
        file_put_contents(
            $this->jsonPath.'/klinik-sehat/cash_entries.json',
            json_encode([
                $this->entry(1, '2026-09-15', 'in', 99_999_999),
                $this->entry(2, '2026-09-16', 'out', 88_888_888),
            ], JSON_PRETTY_PRINT).PHP_EOL,
        );

        $result = $this->analyzer()->analyze('2026-09-25');

        $this->assertFalse($result['insufficient_data']);
        $this->assertSame(2_000_000.0, $result['revenue']);
        $this->assertSame(0.0, $result['expenses']);
    }

    public function test_repository_scope_rejects_cross_company_access(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('lintas company');

        app(EntityRepository::class)->for('klinik-sehat', 'cash_entries');
    }

    public function test_highlights_list_top_expense_and_most_active_contact(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->writeEntries('bengkel-arka', [
            $this->entry(1, '2026-08-15', 'in', 1_000_000),
            $this->entry(2, '2026-09-10', 'in', 1_000_000),
            $this->entry(3, '2026-09-12', 'out', 700_000),
        ]);

        (new Filesystem)->ensureDirectoryExists($this->jsonPath.'/bengkel-arka');
        file_put_contents($this->jsonPath.'/bengkel-arka/orders.json', json_encode([
            ['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-1', 'contact_id' => 7],
            ['id' => 2, 'business_identity_id' => 1, 'order_no' => 'WO-2', 'contact_id' => 7],
            ['id' => 3, 'business_identity_id' => 1, 'order_no' => 'WO-3', 'contact_id' => 9],
        ], JSON_PRETTY_PRINT).PHP_EOL);
        file_put_contents($this->jsonPath.'/bengkel-arka/contacts.json', json_encode([
            ['id' => 7, 'name' => 'Andi Prasetyo'],
            ['id' => 9, 'name' => 'Budi Santoso'],
        ], JSON_PRETTY_PRINT).PHP_EOL);

        $result = $this->analyzer()->analyze('2026-09-25');
        $types = array_column($result['highlights'], 'type');

        $this->assertContains('top_expense', $types);
        $this->assertContains('top_contact', $types);
        $this->assertContains('Andi Prasetyo', array_column($result['highlights'], 'label'));
    }
}
