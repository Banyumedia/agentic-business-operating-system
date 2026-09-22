<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

/**
 * T-21b: membuktikan migration dan kolom uang berperilaku benar di MySQL, bukan
 * hanya di SQLite.
 *
 * B-01 mewajibkan migration ditulis dengan Laravel Schema Builder yang portabel,
 * tetapi seluruh dev dan test berjalan di SQLite - jadi sampai sekarang tidak ada
 * satu pun bukti bahwa 60+ migration benar-benar bisa dijalankan di MySQL.
 * Perbedaan yang sudah terukur: SQLite menyimpan `decimal` sebagai REAL sehingga
 * sen hilang di atas ~13 digit signifikan (`1234567890123.45` kembali sebagai
 * `1234567890123.40`). Kalau MySQL juga kehilangan sen, itu bug produksi; kalau
 * tidak, batasnya memang milik SQLite dan boleh dicatat sebagai batas dev.
 *
 * Test ini **melewati dirinya** bila MySQL tidak tersedia, supaya suite di mesin
 * atau CI tanpa MySQL tetap hijau. Jalankan dengan MySQL hidup:
 *
 *     php artisan test --filter=MysqlParityTest
 */
class MysqlParityTest extends TestCase
{
    private const CONNECTION = 'mysql_parity';

    /**
     * `migrate:fresh` di MySQL memakan belasan detik, jadi dijalankan sekali per
     * kelas dan setiap test membersihkan barisnya sendiri. Menjalankannya per
     * test membuat suite ini saja lebih lama daripada seluruh suite lainnya.
     */
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessMysqlIsReachable();
        $this->migrate();
        $this->truncate();
    }

    public function test_every_migration_runs_on_mysql(): void
    {
        // Kegagalan di sini berarti ada migration yang hanya jalan di SQLite -
        // biasanya karena tipe kolom, index terlalu panjang, atau `change()`
        // yang tidak portabel.
        foreach (['companies', 'customer_invoices', 'cash_entries', 'chart_of_accounts', 'accounting_journals', 'accounting_journal_lines', 'payrolls', 'approval_tickets'] as $table) {
            $this->assertTrue(
                Schema::connection(self::CONNECTION)->hasTable($table),
                "Tabel {$table} tidak terbentuk di MySQL.",
            );
        }
    }

    public function test_money_columns_keep_every_cent_on_mysql(): void
    {
        $companyId = $this->company();

        // Nilai yang terbukti kehilangan sen di SQLite.
        foreach (['1234567890123.45', '9999999999999.99', '0.01', '1234.56'] as $amount) {
            DB::connection(self::CONNECTION)->table('cash_entries')->insert([
                'company_id' => $companyId,
                'entry_date' => '2026-09-20',
                'direction' => 'in',
                'amount' => $amount,
                'category' => 'jasa',
                'description' => 'Uji presisi '.$amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stored = DB::connection(self::CONNECTION)
                ->table('cash_entries')
                ->where('description', 'Uji presisi '.$amount)
                ->value('amount');

            $this->assertSame(
                $amount,
                (string) $stored,
                "MySQL mengubah nilai uang {$amount} menjadi {$stored}.",
            );
        }
    }

    public function test_company_scoped_unique_keys_are_enforced_on_mysql(): void
    {
        // Index unique yang ditambahkan lewat `Schema::table()` (bukan saat
        // pembuatan tabel) adalah tempat perbedaan MySQL/SQLite paling mungkin
        // muncul, dan yang dijaga di sini adalah data finansial.
        $companyId = $this->company();
        $other = $this->company('Usaha Kedua', 'usaha-kedua');

        $this->insertAccount($companyId, '1-1000');
        $this->insertAccount($other, '1-1000');

        $this->assertSame(2, DB::connection(self::CONNECTION)->table('chart_of_accounts')->count());

        $duplicateRefused = false;
        try {
            $this->insertAccount($companyId, '1-1000');
        } catch (Throwable) {
            $duplicateRefused = true;
        }

        $this->assertTrue($duplicateRefused, 'MySQL menerima kode akun ganda dalam satu usaha.');
    }

    public function test_decimal_precision_differs_from_sqlite_and_mysql_is_the_accurate_one(): void
    {
        // Karakterisasi eksplisit: menyatakan perbedaannya, bukan menyembunyikannya.
        $companyId = $this->company();
        DB::connection(self::CONNECTION)->table('cash_entries')->insert([
            'company_id' => $companyId,
            'entry_date' => '2026-09-20',
            'direction' => 'in',
            'amount' => '1234567890123.45',
            'category' => 'jasa',
            'description' => 'Pembanding SQLite',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mysqlValue = (string) DB::connection(self::CONNECTION)
            ->table('cash_entries')
            ->where('description', 'Pembanding SQLite')
            ->value('amount');

        $sqliteValue = (string) (float) '1234567890123.45';

        $this->assertSame('1234567890123.45', $mysqlValue);
        $this->assertNotSame($mysqlValue, number_format((float) $sqliteValue, 2, '.', ''));
    }

    private function migrate(): void
    {
        if (self::$migrated) {
            return;
        }

        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
        self::$migrated = true;
    }

    /**
     * Bersihkan baris uji tanpa membangun ulang skema. Urutannya dari anak ke
     * induk karena foreign key MySQL benar-benar ditegakkan - berbeda dari
     * SQLite yang di banyak konfigurasi membiarkannya mati.
     */
    private function truncate(): void
    {
        $connection = DB::connection(self::CONNECTION);

        foreach (['accounting_journal_lines', 'chart_of_accounts', 'cash_entries', 'companies', 'users'] as $table) {
            $connection->table($table)->delete();
        }
    }

    private function company(string $name = 'Usaha Paritas', string $slug = 'usaha-paritas'): int
    {
        // `companies.owner_user_id` NOT NULL dengan foreign key, jadi pemiliknya
        // harus ada lebih dulu - insert mentah tidak boleh menebak default.
        $ownerId = (int) DB::connection(self::CONNECTION)->table('users')->insertGetId([
            'name' => 'Pemilik '.$slug,
            'email' => $slug.'@contoh.test',
            'password' => bcrypt('rahasia-uji'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::connection(self::CONNECTION)->table('companies')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'owner_user_id' => $ownerId,
            'business_preset' => 'laundry',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAccount(int $companyId, string $code): void
    {
        DB::connection(self::CONNECTION)->table('chart_of_accounts')->insert([
            'company_id' => $companyId,
            'account_code' => $code,
            'name' => 'Kas',
            'type' => 'asset',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function skipUnlessMysqlIsReachable(): void
    {
        $config = config('database.connections.'.self::CONNECTION);

        try {
            $dsn = sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']);
            $pdo = new \PDO($dsn, $config['username'], (string) $config['password'], [
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$config['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $exception) {
            $this->markTestSkipped('MySQL tidak tersedia untuk pemeriksaan paritas: '.$exception->getMessage());
        }
    }
}
