<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\ListScreen;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaPresenter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class ListScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Data entitas diisolasi; preset dan terminologi tetap dari fixture nyata.
        $this->jsonPath = storage_path('framework/testing/list-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_columns_and_form_fields_are_generated_from_the_entity_schema(): void
    {
        $presenter = app(SchemaPresenter::class);
        $contacts = EntitySchema::load('contacts');

        // Foreign key, kantong attributes, timestamp, dan tipe non-skalar tidak disajikan.
        $this->assertSame(
            ['id', 'type', 'name', 'wa_number', 'email', 'source'],
            array_column($presenter->columns($contacts), 'field'),
        );

        $fields = array_column($presenter->fields($contacts), null, 'field');
        $this->assertArrayNotHasKey('id', $fields);
        $this->assertSame('email', $fields['email']['input']);
        $this->assertSame('text', $fields['name']['input']);
        $this->assertTrue($fields['name']['required']);
        $this->assertFalse($fields['email']['required']);
        $this->assertSame(191, $fields['name']['attrs']['maxlength']);

        $employees = array_column($presenter->fields(EntitySchema::load('employees')), null, 'field');
        $this->assertSame('number', $employees['base_salary']['input']);
        $this->assertSame('date', $employees['joined_on']['input']);
        $this->assertSame('checkbox', $employees['is_active']['input']);
        $this->assertSame(0, $employees['base_salary']['attrs']['min']);
        $this->assertSame(0.01, $employees['base_salary']['attrs']['step']);
    }

    public function test_list_screen_labels_follow_company_terminology_for_every_preset(): void
    {
        $expected = [
            'bengkel-arka' => 'Pelanggan',
            'klinik-sehat' => 'Pasien',
            'salon-ayu' => 'Pelanggan',
        ];

        foreach ($expected as $company => $term) {
            $this->get('/app/contacts?company='.$company)
                ->assertOk()
                ->assertSee('Daftar '.$term)
                ->assertSee('Tambah '.$term)
                ->assertSee('Cari '.$term);
        }

        $this->get('/app/hrd/employees?company=salon-ayu')
            ->assertOk()
            ->assertSee('Data Terapis')
            ->assertSee('Tambah Terapis');
    }

    public function test_search_sort_and_pagination_are_delegated_to_the_repository(): void
    {
        $rows = [];
        foreach (range(1, 12) as $index) {
            $rows[] = ['id' => $index, 'name' => sprintf('Kontak %02d', $index), 'type' => 'customer'];
        }
        $rows[] = ['id' => 13, 'name' => 'Zulu Khusus', 'type' => 'vendor'];
        $this->seedRows('bengkel-arka', 'contacts', $rows);

        $component = Livewire::test(ListScreen::class, ['module' => 'contacts']);

        $component->assertViewHas('total', 13)->assertViewHas('lastPage', 2);
        $this->assertCount(10, $component->viewData('rows'));

        $component->call('goToPage', 2);
        $this->assertCount(3, $component->viewData('rows'));

        $component->set('search', 'Zulu');
        $component->assertViewHas('total', 1)->assertViewHas('page', 1);
        $this->assertSame('Zulu Khusus', $component->viewData('rows')[0]['name']);

        $component->set('search', '')->call('sortBy', 'name');
        $this->assertSame('Kontak 01', $component->viewData('rows')[0]['name']);

        $component->call('sortBy', 'name');
        $this->assertSame('desc', $component->get('direction'));
        $this->assertSame('Zulu Khusus', $component->viewData('rows')[0]['name']);

        // Field sort di luar kolom schema diabaikan, bukan diteruskan ke repository.
        $component->call('sortBy', 'attributes');
        $this->assertSame('name', $component->get('sort'));
    }

    public function test_active_sort_is_announced_via_a_polite_live_region(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [['id' => 1, 'name' => 'Satu']]);
        $component = Livewire::test(ListScreen::class, ['module' => 'contacts']);

        $ascending = $component->call('sortBy', 'name')->html();
        $this->assertStringContainsString('aria-live="polite"', $ascending);
        $this->assertStringContainsString('Diurutkan berdasarkan', $ascending);
        $this->assertStringContainsString('menaik', $ascending);
        $this->assertStringContainsString('aria-sort="ascending"', $ascending);

        $descending = $component->call('sortBy', 'name')->html();
        $this->assertStringContainsString('menurun', $descending);
        $this->assertStringContainsString('aria-sort="descending"', $descending);
    }

    public function test_create_edit_and_delete_persist_through_the_repository(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [['id' => 1, 'name' => 'Awal']]);
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');

        $component = Livewire::test(ListScreen::class, ['module' => 'contacts']);

        $component->call('create')
            ->set('form.name', 'Kontak Baru')
            ->set('form.email', 'baru@contoh.test')
            ->call('save')
            ->assertSet('editing', false);

        $created = $repository->find(2);
        $this->assertSame('Kontak Baru', $created['name']);
        $this->assertSame('baru@contoh.test', $created['email']);
        $this->assertSame('customer', $created['type']);
        $this->assertNotNull($created['created_at']);

        $component->call('edit', 2)
            ->assertSet('form.name', 'Kontak Baru')
            ->set('form.name', 'Kontak Diubah')
            ->call('save');

        $this->assertSame('Kontak Diubah', $repository->find(2)['name']);
        $this->assertCount(2, $repository->all());

        $component->call('confirmDelete', 2)->call('delete');
        $this->assertNull($repository->find(2));
        $this->assertCount(1, $repository->all());
    }

    public function test_invalid_input_is_reported_without_writing_the_row(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [['id' => 1, 'name' => 'Awal']]);
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');

        Livewire::test(ListScreen::class, ['module' => 'contacts'])
            ->call('create')
            ->set('form.name', 'Email Salah')
            ->set('form.email', 'bukan-email')
            ->call('save')
            ->assertSet('editing', true)
            ->assertSee('Format field tidak valid');

        $this->assertCount(1, $repository->all());
    }

    public function test_delete_confirmation_is_two_step_and_starts_inert(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [['id' => 1, 'name' => 'Perlu Konfirmasi']]);
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');

        $component = Livewire::test(ListScreen::class, ['module' => 'contacts'])->call('confirmDelete', 1);
        $html = $component->html();

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="delete-dialog-title"', $html);
        $this->assertStringContainsString('aria-describedby="delete-dialog-description"', $html);
        $this->assertStringContainsString('Perlu Konfirmasi', $html);

        // QA-UI-R C.11: penahanan fokus + inert latar + restorasi opener.
        $this->assertStringContainsString('x-trap.inert.noscroll', $html);
        $this->assertStringContainsString('opener: document.activeElement', $html);
        $this->assertStringContainsString('$wire.cancelDelete().then(() => target?.focus())', $html);
        $this->assertStringContainsString('$wire.delete().then(() => $nextTick(', $html);
        $this->assertStringContainsString("document.querySelector('[data-list-focus-fallback]')?.focus()", $html);

        // D-45 tingkat 2: tombol merah inert saat dialog muncul, tanpa ketik "YA".
        $this->assertStringContainsString('x-bind:disabled="! ready"', $html);
        $this->assertStringNotContainsString('autocapitalize="characters"', $html);

        $component->call('cancelDelete');
        $this->assertNotNull($repository->find(1));

        $component->call('confirmDelete', 1)->call('delete');
        $this->assertNull($repository->find(1));
    }

    public function test_empty_state_and_search_miss_use_company_terminology(): void
    {
        app(CompanyContext::class)->setCurrent('klinik-sehat');

        $component = Livewire::test(ListScreen::class, ['module' => 'contacts']);
        $component->assertSee('Belum ada Pasien yang tercatat.');

        $component->set('search', 'tidak-ada-sama-sekali');
        $component->assertSee('Tidak ada Pasien yang cocok dengan pencarian.');
    }

    public function test_entity_cannot_be_forced_through_component_state(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $component = Livewire::test(ListScreen::class, ['module' => 'contacts']);

        $this->assertSame('contacts', $component->viewData('entity'));

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('module', 'accounting');
    }

    public function test_capability_revocation_closes_the_screen(): void
    {
        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
        );
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $component = Livewire::test(ListScreen::class, ['module' => 'projects'])->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['projects'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_screen_refuses_to_act_after_the_active_company_changes(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [['id' => 1, 'name' => 'Milik Bengkel']]);
        $this->seedRows('klinik-sehat', 'contacts', [['id' => 1, 'name' => 'Milik Klinik']]);

        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $component = Livewire::test(ListScreen::class, ['module' => 'contacts'])->call('confirmDelete', 1);

        app(CompanyContext::class)->setCurrent('klinik-sehat');
        $component->call('delete')->assertForbidden();

        // Baris dengan id yang sama pada company lain tidak boleh tersentuh.
        $this->assertNotNull(app(EntityRepository::class)->for('klinik-sehat', 'contacts')->find(1));
    }

    public function test_screen_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/ListScreen.php')),
            file_get_contents(app_path('Services/Schema/SchemaPresenter.php')),
            file_get_contents(resource_path('views/livewire/screens/list.blade.php')),
            file_get_contents(resource_path('views/components/data-table.blade.php')),
            file_get_contents(resource_path('views/components/form-field.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertDoesNotMatchRegularExpression('/\b(?:Pasien|Pelanggan|Terapis|Mekanik|Sparepart|Work Order)\b/', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    /** @param list<array<string, mixed>> $rows */
    private function seedRows(string $company, string $entity, array $rows): void
    {
        app(CompanyContext::class)->setCurrent($company);
        $repository = app(EntityRepository::class)->for($company, $entity);

        foreach ($rows as $row) {
            $repository->save($row);
        }
    }
}
