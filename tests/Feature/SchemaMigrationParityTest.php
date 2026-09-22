<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Schema\EntitySchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Menjaga agar deklarasi `unique` di katalog schema benar-benar ditegakkan oleh
 * basis data.
 *
 * Latar belakang: `chart_of_accounts.account_code` dan
 * `accounting_journals.journal_number` menyatakan `unique` di schema JSON, tetapi
 * migration-nya tidak pernah membuat index unique. Validator schema meloloskan
 * duplikat karena ia hanya memeriksa bentuk data satu baris, dan jalur
 * Eloquent/MySQL tidak punya penjaga lain. Akibatnya dua akun dengan kode sama
 * atau dua jurnal dengan nomor sama bisa hidup berdampingan - cacat data
 * finansial yang tidak terlihat sampai laporan mulai menggandakan angka.
 *
 * Test ini sengaja diturunkan dari katalog (`database/schemas/*.schema.json`),
 * bukan dari daftar entitas yang ditulis tangan, sehingga entitas baru ikut
 * terjaga tanpa menyunting test ini (D-31/D-42).
 */
class SchemaMigrationParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_unique_rule_in_the_schema_catalog_is_enforced_by_the_database(): void
    {
        $missing = [];

        foreach ($this->catalog() as $entity => $schema) {
            if (! Schema::hasTable($entity)) {
                continue;
            }

            $actual = $this->uniqueColumnSets($entity);

            foreach ($schema->unique() as $rule) {
                $expected = $this->expectedColumns($rule);

                if (! $this->isEnforced($expected, $actual)) {
                    $missing[] = $entity.' ('.implode(', ', $expected).')';
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", array_merge(
            ['Deklarasi unique berikut tidak punya index unique di basis data:'],
            $missing,
        )));
    }

    public function test_every_schema_file_in_the_catalog_can_be_loaded(): void
    {
        // `SchemaValidatorTest` memakai daftar entitas yang ditulis tangan, jadi
        // schema yang lupa didaftarkan di sana tidak pernah diperiksa sama sekali.
        // Begitulah `production_orders` dan `production_order_lines` bisa hidup
        // tanpa bagian `attributes` dan ditolak loader tanpa ada yang tahu.
        $broken = [];

        foreach (glob(database_path('schemas/*.schema.json')) ?: [] as $path) {
            $entity = basename($path, '.schema.json');

            try {
                EntitySchema::load($entity);
            } catch (\Throwable $exception) {
                $broken[] = $entity.': '.$exception->getMessage();
            }
        }

        $this->assertSame([], $broken, implode("\n", array_merge(
            ['Schema berikut ada di katalog tapi ditolak loader:'],
            $broken,
        )));
    }

    public function test_negative_a_duplicate_account_code_is_refused_inside_one_company(): void
    {
        $companyId = $this->companyId();

        $this->insertAccount($companyId, '1-1000');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertAccount($companyId, '1-1000');
    }

    public function test_the_same_account_code_is_allowed_in_a_different_company(): void
    {
        // Keunikan ter-scope company (D-26): dua usaha boleh memakai bagan akun
        // standar yang sama tanpa saling mengganggu.
        $first = $this->companyId();
        $second = $this->companyId('Usaha Kedua');

        $this->insertAccount($first, '1-1000');
        $this->insertAccount($second, '1-1000');

        $this->assertSame(2, DB::table('chart_of_accounts')->count());
    }

    public function test_negative_a_duplicate_approval_operation_id_is_refused_inside_one_company(): void
    {
        // Lapis kedua di atas `Company::lockForUpdate()`: satu operasi approval
        // hanya boleh punya satu tiket, walau dua permintaan lolos lock.
        $companyId = $this->companyId();

        $this->insertApprovalTicket($companyId, str_repeat('a', 64), '1111');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertApprovalTicket($companyId, str_repeat('a', 64), '2222');
    }

    public function test_approval_tickets_without_an_operation_id_do_not_block_each_other(): void
    {
        // Kolomnya nullable karena ditambahkan ke tabel yang sudah ada. NULL
        // yang berulang harus tetap diterima, kalau tidak satu baris lama akan
        // memblokir seluruh tiket berikutnya.
        $companyId = $this->companyId();

        $this->insertApprovalTicket($companyId, null, '1111');
        $this->insertApprovalTicket($companyId, null, '2222');

        $this->assertSame(2, DB::table('approval_tickets')->count());
    }

    public function test_negative_a_duplicate_journal_number_is_refused_inside_one_company(): void
    {
        $companyId = $this->companyId();

        $this->insertJournal($companyId, 'JV-2026-0001');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertJournal($companyId, 'JV-2026-0001');
    }

    public function test_the_same_journal_number_is_allowed_in_a_different_company(): void
    {
        $first = $this->companyId();
        $second = $this->companyId('Usaha Kedua');

        $this->insertJournal($first, 'JV-2026-0001');
        $this->insertJournal($second, 'JV-2026-0001');

        $this->assertSame(2, DB::table('accounting_journals')->count());
    }

    /** @return array<string, EntitySchema> */
    private function catalog(): array
    {
        $catalog = [];

        foreach (glob(database_path('schemas/*.schema.json')) ?: [] as $path) {
            $entity = basename($path, '.schema.json');

            try {
                $catalog[$entity] = EntitySchema::load($entity);
            } catch (\Throwable) {
                // Schema rusak dilaporkan oleh test tersendiri; di sini ia
                // dilewati supaya kegagalan paritas unique tetap terbaca jelas.
                continue;
            }
        }

        ksort($catalog);

        return $catalog;
    }

    /**
     * Kolom yang seharusnya ikut dalam index unique. `company_scoped` berarti
     * `company_id` menjadi bagian kuncinya, bukan sekadar catatan - tanpa itu
     * nomor yang sah di satu usaha akan memblokir usaha lain.
     *
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private function expectedColumns(array $rule): array
    {
        $fields = array_values((array) ($rule['fields'] ?? []));

        $columns = ($rule['company_scoped'] ?? false) === true
            ? array_merge(['company_id'], $fields)
            : $fields;

        // Diurutkan seperti sisi basis data: urutan kolom di dalam index tidak
        // mengubah arti keunikannya, jadi perbandingan tidak boleh peka urutan.
        sort($columns);

        return $columns;
    }

    /**
     * Sebuah deklarasi dianggap ditegakkan bila ada index unique yang kolomnya
     * merupakan himpunan bagian dari kolom yang diminta. Unique atas bagian dari
     * kunci selalu **lebih ketat**, jadi ia sudah menjamin yang diminta.
     *
     * Ini bukan kelonggaran teoretis: `invoices.order_id` sengaja unique
     * **global** di migration, bukan per company. `order_id` adalah referensi
     * order dari gateway pembayaran, dan unique global itulah yang mencegah
     * webhook satu usaha mengkreditkan pembayaran usaha lain. Melonggarkannya
     * menjadi per company demi "kerapian" schema akan menjadi regresi keamanan.
     *
     * @param  list<string>  $expected
     * @param  list<list<string>>  $actual
     */
    private function isEnforced(array $expected, array $actual): bool
    {
        foreach ($actual as $columns) {
            if ($columns !== [] && array_diff($columns, $expected) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Index unique nyata di tabel, kolomnya diurutkan agar perbandingan tidak
     * bergantung urutan penulisan di migration.
     *
     * @return list<list<string>>
     */
    private function uniqueColumnSets(string $table): array
    {
        $sets = [];

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) !== true) {
                continue;
            }

            $columns = array_values((array) $index['columns']);
            sort($columns);
            $sets[] = $columns;
        }

        return $sets;
    }

    private function companyId(string $name = 'Usaha Uji'): int
    {
        return (int) Company::factory()->create(['name' => $name])->id;
    }

    private function insertAccount(int $companyId, string $code): void
    {
        DB::table('chart_of_accounts')->insert([
            'company_id' => $companyId,
            'account_code' => $code,
            'name' => 'Kas',
            'type' => 'asset',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertApprovalTicket(int $companyId, ?string $operationId, string $code): void
    {
        DB::table('approval_tickets')->insert([
            'company_id' => $companyId,
            'operation_id' => $operationId,
            'code' => $code,
            'action_type' => 'workflow.transition',
            'payload' => json_encode(['operation_id' => $operationId]),
            'status' => 'prepared',
            'channel' => 'whatsapp',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertJournal(int $companyId, string $number): void
    {
        DB::table('accounting_journals')->insert([
            'company_id' => $companyId,
            'journal_number' => $number,
            'transaction_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
