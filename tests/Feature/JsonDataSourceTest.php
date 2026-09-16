<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Services\Json\JsonCompanyContext;
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

    public function test_scoped_repository_is_revoked_when_active_company_changes(): void
    {
        $context = app(CompanyContext::class);
        $context->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'contacts');
        $context->setCurrent('klinik-sehat');

        $this->expectException(LogicException::class);
        $repository->all();
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

    public function test_committed_demo_inventory_has_45_schema_valid_files(): void
    {
        $files = glob(storage_path('app/json/*/*.json'));
        $validator = app(SchemaValidator::class);

        $this->assertIsArray($files);
        $this->assertCount(45, $files);

        foreach ($files as $file) {
            $rows = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $entity = pathinfo($file, PATHINFO_FILENAME);

            $this->assertIsArray($rows);
            foreach ($rows as $row) {
                $validator->validate($entity, $row);
            }
        }
    }

    public function test_object_root_is_not_silently_replaced(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $directory = $this->jsonPath.'/bengkel-arka';
        (new Filesystem)->ensureDirectoryExists($directory);
        file_put_contents($directory.'/contacts.json', '{}');

        try {
            app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['id' => 1, 'name' => 'Aman']);
            $this->fail('Root object seharusnya ditolak.');
        } catch (\JsonException) {
            $this->assertSame('{}', file_get_contents($directory.'/contacts.json'));
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
}
