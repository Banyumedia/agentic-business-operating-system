<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Services\FeatureResolver;
use App\Services\TerminologyResolver;
use Tests\TestCase;

class CommittedCapabilityFixtureTest extends TestCase
{
    public function test_committed_company_fixtures_resolve_through_the_real_company_disk(): void
    {
        $cases = [
            'bengkel-arka' => ['preset' => 'bengkel', 'contact' => 'Pelanggan', 'feature' => 'projects'],
            'klinik-sehat' => ['preset' => 'klinik', 'contact' => 'Pasien', 'feature' => 'bookings'],
            'salon-ayu' => ['preset' => 'salon', 'contact' => 'Pelanggan', 'feature' => 'bookings'],
        ];

        foreach ($cases as $company => $expected) {
            session(['active_company' => $company]);

            $this->assertSame($expected['preset'], app(CompanyContext::class)->preset());
            $this->assertSame($expected['contact'], app(TerminologyResolver::class)->resolve('contact'));
            $this->assertTrue(app(FeatureResolver::class)->enabled($expected['feature']));
        }
    }
}
