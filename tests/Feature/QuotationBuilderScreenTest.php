<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\QuotationBuilderScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MP-08: builder penawaran berbaris (RAB) + konversi ke proyek.
 *
 * Yang dijaga: nilai selalu dihitung ulang dari baris lewat `TaxRateService`
 * (bukan dari total yang dikirim klien), penawaran yang sudah jadi proyek
 * tidak bisa dikonversi lagi maupun diubah, dan layar fail-closed terhadap
 * company lain.
 */
class QuotationBuilderScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/quo-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        $this->useCompany('bengkel-arka', 'desain_interior', ['tax_mode' => 'non_taxable']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_quotation_total_is_computed_from_its_lines(): void
    {
        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->assertOk()
            ->call('create')
            ->set('form.title', 'Renovasi ruang tamu')
            ->set('lines.0.description', 'Jasa desain')
            ->set('lines.0.quantity', 2)
            ->set('lines.0.unit_price', 1500000)
            ->call('addLine')
            ->set('lines.1.description', 'Survei lokasi')
            ->set('lines.1.quantity', 1)
            ->set('lines.1.unit_price', 250000)
            ->call('save')
            ->assertSet('failure', null);

        $quotation = $this->quotations()->all()[0];

        $this->assertSame(3250000.0, (float) $quotation['subtotal']);
        $this->assertSame(3250000.0, (float) $quotation['grand_total']);
        $this->assertSame('draft', $quotation['stage']);
        $this->assertCount(2, $this->lines());
    }

    public function test_negative_client_sent_total_is_ignored_and_recomputed(): void
    {
        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('create')
            ->set('form.title', 'Uji hitung')
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 3)
            ->set('lines.0.unit_price', 111111.11)
            ->call('save')
            ->assertSet('failure', null);

        $quotation = $this->quotations()->all()[0];
        $this->assertSame(333333.33, (float) $quotation['grand_total']);
        $this->assertSame(333333.33, (float) $this->lines()[0]['line_total']);
    }

    public function test_negative_empty_quotation_is_rejected(): void
    {
        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('create')
            ->set('form.title', 'Kosong')
            ->set('lines.0.description', '')
            ->set('lines.0.quantity', '')
            ->set('lines.0.unit_price', '')
            ->call('save')
            ->assertSee('setidaknya satu rincian');

        $this->assertSame([], $this->quotations()->all());
    }

    public function test_full_lifecycle_ends_in_a_project_with_the_stored_value(): void
    {
        $id = $this->createQuotation('Renovasi dapur', 4000000);

        $screen = Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('markSent', $id)
            ->assertSet('failure', null);

        $screen->call('markApproved', $id)->assertSet('failure', null);

        $screen->call('requestConvert', $id)
            ->assertSet('pendingConvertId', $id)
            ->call('confirmConvert')
            ->assertSet('failure', null)
            ->assertSet('notice', 'Penawaran dikonversi menjadi proyek.');

        $quotation = $this->quotations()->find($id);
        $this->assertNotNull($quotation['project_id']);

        $project = app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'projects')->find((int) $quotation['project_id']);
        $this->assertNotNull($project);
        $this->assertSame('Renovasi dapur', $project['name']);
        $this->assertSame(4000000.0, (float) $project['budget']);
        $this->assertSame('survei', $project['stage']);
    }

    public function test_negative_a_quotation_cannot_be_converted_twice(): void
    {
        $id = $this->createQuotation('Sekali konversi', 1000000);

        $screen = Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('markSent', $id)
            ->call('markApproved', $id)
            ->call('requestConvert', $id)
            ->call('confirmConvert');

        $firstProjectId = $this->quotations()->find($id)['project_id'];

        // Percobaan kedua lewat jalur publik yang sama - requestConvert
        // menolak lebih dulu karena project_id sudah terisi.
        $screen->call('requestConvert', $id)
            ->assertSee('sudah jadi proyek')
            ->assertSet('pendingConvertId', null);

        $this->assertSame($firstProjectId, $this->quotations()->find($id)['project_id']);
        $this->assertCount(1, array_filter(
            app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'projects')->all(),
        ));
    }

    public function test_negative_a_draft_quotation_cannot_be_converted(): void
    {
        $id = $this->createQuotation('Belum disetujui', 1000000);

        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('requestConvert', $id)
            ->assertSee('yang disetujui')
            ->assertSet('pendingConvertId', null);

        $this->assertNull($this->quotations()->find($id)['project_id'] ?? null);
    }

    public function test_negative_staff_cannot_convert_an_approved_quotation(): void
    {
        $id = $this->createQuotation('Percobaan staf', 1000000);

        $screen = Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('markSent', $id)
            ->call('markApproved', $id)
            ->call('requestConvert', $id)
            ->assertSet('pendingConvertId', $id);

        session(['company_role' => 'staff']);

        $screen->call('confirmConvert')->assertStatus(403);

        $this->assertNull($this->quotations()->find($id)['project_id'] ?? null);
    }

    public function test_negative_a_converted_quotation_cannot_be_edited(): void
    {
        $id = $this->createQuotation('Sudah jadi proyek', 1000000);

        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('markSent', $id)
            ->call('markApproved', $id)
            ->call('requestConvert', $id)
            ->call('confirmConvert');

        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('edit', $id)
            ->assertSee('sudah jadi proyek')
            ->assertSet('editing', false);
    }

    public function test_negative_a_quotation_from_another_company_is_not_found(): void
    {
        $foreignId = $this->createQuotation('Milik usaha lain', 500000);

        $this->useCompany('salon-ayu', 'desain_interior', ['tax_mode' => 'non_taxable']);

        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('requestConvert', $foreignId)
            ->assertSee('tidak ditemukan pada usaha ini');
    }

    private function createQuotation(string $title, float $amount): int
    {
        Livewire::test(QuotationBuilderScreen::class, ['module' => 'projects', 'submodule' => 'quotations'])
            ->call('create')
            ->set('form.title', $title)
            ->set('lines.0.description', 'Jasa')
            ->set('lines.0.quantity', 1)
            ->set('lines.0.unit_price', $amount)
            ->call('save');

        return (int) $this->quotations()->all()[0]['id'];
    }

    private function quotations(): EntityRepository
    {
        return app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'quotations');
    }

    /** @return list<array<string, mixed>> */
    private function lines(): array
    {
        return app(EntityRepository::class)
            ->for(app(CompanyContext::class)->current(), 'quotation_lines')
            ->all();
    }

    /** @param array<string, mixed> $identity */
    private function useCompany(string $company, string $preset, array $identity): void
    {
        Storage::disk('company-json')->put(
            "json/{$company}/settings.json",
            json_encode(['preset' => $preset], JSON_THROW_ON_ERROR),
        );
        Storage::disk('company-json')->put(
            "json/{$company}/business_identity.json",
            json_encode(
                array_merge(['id' => 1, 'name' => 'Usaha Uji', 'preset' => $preset], $identity),
                JSON_THROW_ON_ERROR,
            ),
        );

        app(CompanyContext::class)->setCurrent($company);

        // Aksi konversi owner-only (T-50); fixture default bertindak sebagai owner.
        session(['company_role' => 'owner']);
    }
}
