<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\Json\JsonCompanyContext;
use App\Services\Json\JsonEntityRepository;
use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaValidator;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class JsonDataSourceTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/json-'.bin2hex(random_bytes(5)));
        config([
            'datasource.json_path' => $this->jsonPath,
            'datasource.demo_companies' => ['bengkel-arka', 'klinik-sehat', 'salon-ayu'],
        ]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_context_accepts_only_allowlisted_company_and_remembers_it_in_demo_environment(): void
    {
        $this->get('/?company=bengkel-arka');

        $context = app(CompanyContext::class);
        $this->assertSame('bengkel-arka', $context->current());
        $this->assertSame('bengkel-arka', session('active_company'));

        $this->app->forgetInstance(CompanyContext::class);
        $this->assertSame('bengkel-arka', app(CompanyContext::class)->current());
    }

    public function test_context_rejects_unknown_company_and_path_traversal(): void
    {
        foreach (['usaha-asing', '../bengkel-arka'] as $company) {
            try {
                app(JsonCompanyContext::class)->setCurrent($company);
                $this->fail("Company {$company} seharusnya ditolak.");
            } catch (InvalidArgumentException) {
                $this->assertNull(session('active_company'));
            }
        }
    }

    public function test_context_is_fail_closed_outside_demo_environments(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        request()->query->set('company', 'bengkel-arka');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('hanya tersedia di environment demo');

        app(JsonCompanyContext::class)->current();
    }

    public function test_tenant_http_routes_fail_closed_outside_demo_environments(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/app/settings?company=bengkel-arka')->assertForbidden();
        $this->get('/app/hrd?company=bengkel-arka')->assertForbidden();
    }

    public function test_scoped_repository_is_revoked_when_active_company_changes(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');
        $context->setCurrent('klinik-sehat');

        foreach ([
            fn () => $repository->all(),
            fn () => $repository->save(['id' => 9, 'name' => 'Ditolak']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Repository lama harus ditolak setelah company aktif berubah.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_repository_reads_writes_finds_filters_sorts_and_paginates(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');

        $repository->save(['id' => 2, 'name' => 'Budi', 'type' => 'customer']);
        $repository->save(['id' => 1, 'name' => 'Ani', 'type' => 'customer']);
        $repository->save(['id' => 3, 'name' => 'Cici', 'type' => 'vendor']);
        $repository->save(['id' => 2, 'name' => 'Budi Baru', 'type' => 'customer']);

        $this->assertCount(3, $repository->all());
        $this->assertSame('Budi Baru', $repository->find(2)['name']);

        $page = $repository->query([
            'type' => 'customer',
            '_sort' => 'name',
            '_direction' => 'asc',
            '_page' => 1,
            '_per_page' => 1,
        ]);

        $this->assertSame(2, $page['total']);
        $this->assertSame(2, $page['last_page']);
        $this->assertSame('Ani', $page['data'][0]['name']);

        $laterPage = $repository->query([
            'type' => 'customer',
            '_sort' => 'name',
            '_direction' => 'desc',
            '_page' => 2,
            '_per_page' => 1,
        ]);
        $this->assertSame('Ani', $laterPage['data'][0]['name']);
        $this->assertSame([], $repository->query(['_page' => 99])['data']);

        foreach ([['_direction' => 'sideways'], ['_page' => 0], ['_per_page' => 101], ['unknown' => 'x']] as $invalid) {
            try {
                $repository->query($invalid);
                $this->fail('Kontrol query tidak valid harus ditolak.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_repository_denies_cross_context_access_and_path_traversal(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        foreach ([['klinik-sehat', 'contacts'], ['../bengkel-arka', 'contacts'], ['bengkel-arka', '../contacts']] as [$company, $entity]) {
            try {
                app(EntityRepository::class)->for($company, $entity)->all();
                $this->fail('Scope repository tidak sah seharusnya ditolak.');
            } catch (InvalidArgumentException|LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_repository_rejects_invalid_rows_and_corrupt_json_without_overwrite(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');

        $this->expectException(InvalidArgumentException::class);
        $repository->save(['id' => 1]);
    }

    public function test_committed_demo_inventory_has_48_schema_valid_files(): void
    {
        $schemaNames = array_map(
            static fn (string $path): string => str_replace('.schema.json', '', basename($path)),
            glob(database_path('schemas/*.schema.json')) ?: [],
        );
        $files = array_values(array_filter(
            glob(storage_path('app/json/*/*.json')) ?: [],
            static fn (string $path): bool => in_array(pathinfo($path, PATHINFO_FILENAME), $schemaNames, true),
        ));
        $validator = app(SchemaValidator::class);

        $this->assertCount(48, $files);

        foreach ($files as $file) {
            $rows = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $entity = pathinfo($file, PATHINFO_FILENAME);

            $this->assertIsArray($rows);
            foreach ($rows as $row) {
                $validator->validate($entity, $row);
            }
        }
    }

    public function test_committed_demo_fixtures_have_enough_rows_for_review(): void
    {
        // Menjaga data demo tetap layak ditinjau; sebelumnya setiap entitas
        // hanya berisi satu baris stub sehingga layar tampak kosong.
        $minimums = ['contacts' => 8, 'employees' => 4, 'bookings' => 6, 'cash_entries' => 8];

        foreach (['bengkel-arka', 'klinik-sehat', 'salon-ayu'] as $company) {
            foreach ($minimums as $entity => $minimum) {
                $rows = json_decode(
                    (string) file_get_contents(storage_path("app/json/{$company}/{$entity}.json")),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );

                $this->assertGreaterThanOrEqual(
                    $minimum,
                    count($rows),
                    "Data demo {$company}/{$entity} terlalu sedikit untuk review UI.",
                );
            }
        }
    }

    public function test_committed_demo_fixtures_keep_referential_integrity(): void
    {
        // Entitas yang belum punya fixture pada Fase 2 (menyusul di Fase 3).
        $external = ['users', 'business_identities', 'accounting_journals', 'company_memberships', 'pos_shifts'];

        foreach (['bengkel-arka', 'klinik-sehat', 'salon-ayu'] as $company) {
            $ids = [];
            foreach (glob(storage_path("app/json/{$company}/*.json")) ?: [] as $file) {
                $entity = pathinfo($file, PATHINFO_FILENAME);
                $rows = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                if (is_array($rows) && array_is_list($rows)) {
                    $ids[$entity] = array_column($rows, 'id');
                }
            }

            foreach ($ids as $entity => $_) {
                if (! is_file(database_path("schemas/{$entity}.schema.json"))) {
                    continue;
                }

                $references = EntitySchema::load($entity)->references();
                $rows = json_decode(
                    (string) file_get_contents(storage_path("app/json/{$company}/{$entity}.json")),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );

                foreach ($references as $field => $reference) {
                    if (in_array($reference['entity'], $external, true)) {
                        continue;
                    }

                    foreach ($rows as $row) {
                        $value = $row[$field] ?? null;
                        if ($value === null) {
                            continue;
                        }

                        $this->assertContains(
                            $value,
                            $ids[$reference['entity']] ?? [],
                            "{$company}/{$entity}.{$field}={$value} menunjuk baris {$reference['entity']} yang tidak ada.",
                        );
                    }
                }
            }
        }
    }

    public function test_demo_orders_resolve_business_identity_fixture(): void
    {
        foreach (['bengkel-arka', 'klinik-sehat', 'salon-ayu'] as $company) {
            $identity = json_decode((string) file_get_contents(storage_path("app/json/{$company}/business_identity.json")), true, flags: JSON_THROW_ON_ERROR);
            $orders = json_decode((string) file_get_contents(storage_path("app/json/{$company}/orders.json")), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame($identity['id'], $orders[0]['business_identity_id']);
        }
    }

    public function test_object_root_is_not_silently_replaced(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $directory = $this->jsonPath.'/bengkel-arka';
        (new Filesystem)->ensureDirectoryExists($directory);
        file_put_contents($directory.'/contacts.json', '{}');

        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');
        foreach ([fn () => $repository->all(), fn () => $repository->save(['id' => 1, 'name' => 'Aman'])] as $operation) {
            try {
                $operation();
                $this->fail('Root object seharusnya ditolak.');
            } catch (\JsonException) {
                $this->assertSame('{}', file_get_contents($directory.'/contacts.json'));
            }
        }
    }

    public function test_short_temporary_write_never_replaces_existing_data(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $directory = $this->jsonPath.'/bengkel-arka';
        (new Filesystem)->ensureDirectoryExists($directory);
        $path = $directory.'/contacts.json';
        file_put_contents($path, "[]\n");

        $repository = new class(app(CompanyContext::class), app(SchemaValidator::class)) extends JsonEntityRepository
        {
            protected function writeTemporaryFile(string $path, string $contents): int|false
            {
                return max(0, strlen($contents) - 1);
            }
        };

        try {
            $repository->for('bengkel-arka', 'contacts')->save(['id' => 1, 'name' => 'Aman']);
            $this->fail('Short write seharusnya ditolak.');
        } catch (\RuntimeException) {
            $this->assertSame("[]\n", file_get_contents($path));
        }
    }

    public function test_corrupt_json_is_not_silently_replaced(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $directory = $this->jsonPath.'/bengkel-arka';
        (new Filesystem)->ensureDirectoryExists($directory);
        file_put_contents($directory.'/contacts.json', '{corrupt');

        try {
            app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['id' => 1, 'name' => 'Aman']);
            $this->fail('JSON rusak seharusnya ditolak.');
        } catch (\JsonException) {
            $this->assertSame('{corrupt', file_get_contents($directory.'/contacts.json'));
        }
    }

    public function test_repository_assigns_next_id_and_deletes_rows(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');

        $first = $repository->save(['name' => 'Tanpa Id']);
        $second = $repository->save(['name' => 'Berikutnya']);

        $this->assertSame(1, $first['id']);
        $this->assertSame(2, $second['id']);

        $this->assertTrue($repository->delete(1));
        $this->assertNull($repository->find(1));
        $this->assertCount(1, $repository->all());

        $this->assertFalse($repository->delete(404));
        $this->assertSame(3, $repository->save(['name' => 'Setelah Hapus'])['id']);
    }

    public function test_schema_declared_no_overlap_is_enforced_per_scope(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $repository = app(EntityRepository::class)->for('salon-ayu', 'bookings');

        $repository->save([
            'id' => 1,
            'resource_id' => 1,
            'starts_at' => '2026-09-20T09:00:00+07:00',
            'ends_at' => '2026-09-20T10:00:00+07:00',
        ]);

        // Slot berurutan pada sumber daya sama: batas bersentuhan tetap sah.
        $repository->save([
            'id' => 2,
            'resource_id' => 1,
            'starts_at' => '2026-09-20T10:00:00+07:00',
            'ends_at' => '2026-09-20T11:00:00+07:00',
        ]);

        // Sumber daya berbeda pada jam yang sama tetap sah.
        $repository->save([
            'id' => 3,
            'resource_id' => 2,
            'starts_at' => '2026-09-20T09:30:00+07:00',
            'ends_at' => '2026-09-20T10:30:00+07:00',
        ]);

        $this->assertCount(3, $repository->all());

        foreach ([
            ['id' => 4, 'starts_at' => '2026-09-20T09:30:00+07:00', 'ends_at' => '2026-09-20T10:30:00+07:00'],
            ['id' => 5, 'starts_at' => '2026-09-20T08:30:00+07:00', 'ends_at' => '2026-09-20T09:30:00+07:00'],
            ['id' => 6, 'starts_at' => '2026-09-20T08:00:00+07:00', 'ends_at' => '2026-09-20T12:00:00+07:00'],
        ] as $conflict) {
            try {
                $repository->save($conflict + ['resource_id' => 1]);
                $this->fail('Jadwal bertumpang-tindih seharusnya ditolak.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('bertumpang-tindih', $exception->getMessage());
            }
        }

        // Penolakan tidak boleh menambah baris.
        $this->assertCount(3, $repository->all());

        // Menyimpan ulang baris yang sama tidak boleh bentrok dengan dirinya.
        $repository->save([
            'id' => 1,
            'resource_id' => 1,
            'starts_at' => '2026-09-20T09:00:00+07:00',
            'ends_at' => '2026-09-20T09:45:00+07:00',
        ]);
        $this->assertSame('2026-09-20T09:45:00+07:00', $repository->find(1)['ends_at']);
    }

    public function test_delete_is_denied_after_the_active_company_changes(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');
        $repository->save(['id' => 5, 'name' => 'Aman']);
        $context->setCurrent('klinik-sehat');

        $this->expectException(LogicException::class);
        $repository->delete(5);
    }
}
