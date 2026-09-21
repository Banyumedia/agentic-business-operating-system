<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\ListScreen;
use App\Services\DynamicMenuRegistry;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-41 (D-62): jalur input Buku Kas beserta pembebanan ke proyek.
 *
 * Sebelum ini `LedgerScreen` baca-saja, jadi tidak ada cara mencatat
 * penerimaan maupun pengeluaran dari UI dan laba-rugi proyek mustahil dihitung.
 * Yang dijaga di sini: entri kas bisa dibuat lewat pola `list` generik, dan
 * referensi proyek/kontak berasal dari penandaan di schema - bukan dari daftar
 * field khusus entitas di kode (D-31/D-42).
 */
class CashEntryInputTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/cash-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        session(['company_role' => 'owner']);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_cash_entry_module_path_is_registered_and_uses_the_generic_list_screen(): void
    {
        $registry = app(DynamicMenuRegistry::class);

        $this->assertTrue($registry->hasPath('accounting', 'entries'));

        $definition = $registry->routeDefinition('accounting', 'entries');
        $this->assertSame('list', $definition['screen']);
        $this->assertSame('cash_entries', $definition['entity']);

        // Item muncul di navigasi, bukan hanya terjangkau lewat URL.
        $routes = array_column($registry->menusFor('accounting'), 'route');
        $this->assertContains('/app/accounting/entries', $routes);
    }

    public function test_only_references_marked_assignable_become_form_fields(): void
    {
        $presenter = app(SchemaPresenter::class);
        $fields = array_column($presenter->fields(EntitySchema::load('cash_entries')), null, 'field');

        $this->assertArrayHasKey('project_id', $fields);
        $this->assertArrayHasKey('contact_id', $fields);
        $this->assertSame('relation', $fields['project_id']['input']);
        $this->assertSame('projects', $fields['project_id']['relation']);

        // Kolom sistem tetap tersembunyi: operator tidak menetapkan jurnal
        // atau pencatat secara manual.
        $this->assertArrayNotHasKey('journal_id', $fields);
        $this->assertArrayNotHasKey('created_by_user_id', $fields);

        // Referensi tidak boleh bocor ke kolom tabel.
        $columns = array_column($presenter->columns(EntitySchema::load('cash_entries')), 'field');
        $this->assertNotContains('project_id', $columns);
    }

    public function test_relation_label_follows_the_company_terminology(): void
    {
        $this->seedProjects();

        $fields = array_column(
            Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
                ->assertOk()
                ->viewData('fields'),
            null,
            'field',
        );

        // Preset bengkel menyebut proyek sebagai "Pekerjaan" dan kontak sebagai
        // "Pelanggan"; labelnya harus mengikuti kamus, bukan nama kolom.
        $this->assertSame('Pekerjaan', $fields['project_id']['label']);
        $this->assertSame('Pelanggan', $fields['contact_id']['label']);
    }

    public function test_expense_can_be_charged_to_a_project(): void
    {
        $this->seedProjects();

        Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
            ->call('create')
            ->set('form.entry_date', '2026-09-22')
            ->set('form.direction', 'out')
            ->set('form.amount', 2000000)
            ->set('form.category', 'material')
            ->set('form.description', 'Beli material')
            ->set('form.project_id', 7)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('failure', null);

        $rows = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries')->all();

        $this->assertCount(1, $rows);
        $this->assertSame(7, (int) $rows[0]['project_id']);
        $this->assertSame('out', $rows[0]['direction']);
        $this->assertSame(2000000.0, (float) $rows[0]['amount']);
    }

    public function test_entry_without_a_project_is_accepted(): void
    {
        $this->seedProjects();

        Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
            ->call('create')
            ->set('form.entry_date', '2026-09-22')
            ->set('form.direction', 'in')
            ->set('form.amount', 50000)
            ->set('form.project_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $rows = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries')->all();

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['project_id']);
    }

    public function test_relation_options_only_contain_rows_of_the_active_company(): void
    {
        $this->seedProjects();
        app(EntityRepository::class)->for('bengkel-arka', 'projects')
            ->save(['id' => 9, 'name' => 'Servis Besar', 'stage' => 'survei']);

        app(CompanyContext::class)->setCurrent('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'projects')
            ->save(['id' => 99, 'name' => 'Proyek Usaha Lain', 'stage' => 'survei']);
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $fields = array_column(
            Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
                ->viewData('fields'),
            null,
            'field',
        );

        $options = $fields['project_id']['options'];
        $this->assertSame([7, 9], array_keys($options));
        $this->assertSame('Ganti Kampas', $options[7]);
        $this->assertNotContains('Proyek Usaha Lain', $options);
    }

    public function test_assigning_a_project_outside_the_company_is_rejected_and_nothing_is_saved(): void
    {
        $this->seedProjects();

        app(CompanyContext::class)->setCurrent('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'projects')
            ->save(['id' => 99, 'name' => 'Proyek Usaha Lain', 'stage' => 'survei']);
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        // Nilai dikirim klien; relasi menggantung harus ditolak, bukan disimpan.
        Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
            ->call('create')
            ->set('form.entry_date', '2026-09-22')
            ->set('form.direction', 'out')
            ->set('form.amount', 1000)
            ->set('form.project_id', 99)
            ->call('save')
            ->assertHasErrors('form.project_id');

        $this->assertSame([], app(EntityRepository::class)->for('bengkel-arka', 'cash_entries')->all());
    }

    public function test_direction_outside_the_schema_enum_is_rejected(): void
    {
        Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
            ->call('create')
            ->set('form.entry_date', '2026-09-22')
            ->set('form.direction', 'masuk')
            ->set('form.amount', 1000)
            ->call('save');

        $this->assertSame([], app(EntityRepository::class)->for('bengkel-arka', 'cash_entries')->all());
    }

    public function test_cash_entries_are_isolated_per_tenant(): void
    {
        $this->seedProjects();

        Livewire::test(ListScreen::class, ['module' => 'accounting', 'submodule' => 'entries'])
            ->call('create')
            ->set('form.entry_date', '2026-09-22')
            ->set('form.direction', 'out')
            ->set('form.amount', 750000)
            ->set('form.project_id', 7)
            ->call('save')
            ->assertHasNoErrors();

        app(CompanyContext::class)->setCurrent('salon-ayu');

        $this->assertSame([], app(EntityRepository::class)->for('salon-ayu', 'cash_entries')->all());
    }

    private function seedProjects(): void
    {
        app(EntityRepository::class)->for('bengkel-arka', 'projects')
            ->save(['id' => 7, 'name' => 'Ganti Kampas', 'stage' => 'survei']);
    }
}
