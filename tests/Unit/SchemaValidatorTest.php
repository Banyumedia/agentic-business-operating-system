<?php

namespace Tests\Unit;

use App\Services\Schema\EntitySchema;
use App\Services\Schema\SchemaValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaValidatorTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function entities(): array
    {
        return collect([
            'contacts', 'deals', 'projects', 'project_milestones', 'resources',
            'bookings', 'items', 'item_batches', 'orders', 'order_lines',
            'employees', 'cash_entries', 'invoices', 'quotations', 'timesheet_entries',
        ])->mapWithKeys(fn (string $entity): array => [$entity => [$entity]])->all();
    }

    #[DataProvider('entities')]
    public function test_every_capability_entity_has_a_migration_ready_schema(string $entity): void
    {
        $schema = EntitySchema::load($entity);

        $this->assertSame($entity, $schema->name());
        $this->assertSame(1, $schema->version());
        $this->assertContains('id', $schema->required());
        $this->assertArrayHasKey('id', $schema->properties());
        $this->assertArrayNotHasKey('company_id', $schema->properties());
    }

    #[DataProvider('entities')]
    public function test_every_entity_rejects_a_row_without_required_fields(string $entity): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field wajib tidak ada: id');

        app(SchemaValidator::class)->validate($entity, []);
    }

    public function test_valid_row_passes_validation(): void
    {
        $row = [
            'id' => 1,
            'name' => 'Kontak Uji',
            'wa_number' => '08123456789',
            'attributes' => ['notes' => 'Pelanggan baru'],
        ];

        $this->assertSame($row, app(SchemaValidator::class)->validate('contacts', $row));
    }

    public function test_missing_required_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field wajib tidak ada: name');

        app(SchemaValidator::class)->validate('contacts', ['id' => 1]);
    }

    public function test_unknown_attribute_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute tidak dideklarasikan: secret_industry_field');

        app(SchemaValidator::class)->validate('contacts', [
            'id' => 1,
            'name' => 'Kontak Uji',
            'attributes' => ['secret_industry_field' => true],
        ]);
    }

    public function test_wrong_field_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipe field tidak valid: name harus string');

        app(SchemaValidator::class)->validate('contacts', [
            'id' => 1,
            'name' => 123,
        ]);
    }

    public function test_attributes_must_be_an_object_map(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipe field tidak valid: attributes harus object');

        app(SchemaValidator::class)->validate('contacts', [
            'id' => 1,
            'name' => 'Kontak Uji',
            'attributes' => ['nilai tanpa key'],
        ]);
    }

    public function test_non_finite_number_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipe field tidak valid: amount harus number');

        app(SchemaValidator::class)->validate('cash_entries', [
            'id' => 1,
            'entry_date' => '2026-09-16',
            'direction' => 'in',
            'amount' => INF,
        ]);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function invalidConstrainedRows(): array
    {
        return [
            'email format' => ['contacts', ['id' => 1, 'name' => 'Kontak', 'email' => 'bukan-email'], 'Format field tidak valid: email'],
            'string length' => ['contacts', ['id' => 1, 'name' => str_repeat('x', 192)], 'Panjang field tidak valid: name'],
            'date format' => ['timesheet_entries', ['id' => 1, 'employee_id' => 1, 'work_date' => '2026-02-30', 'hours' => 1], 'Format field tidak valid: work_date'],
            'numeric maximum' => ['projects', ['id' => 1, 'name' => 'Proyek', 'progress_pct' => 101], 'Nilai field tidak valid: progress_pct'],
            'unsigned integer' => ['contacts', ['id' => -1, 'name' => 'Kontak'], 'Nilai field tidak valid: id'],
            'decimal scale' => ['cash_entries', ['id' => 1, 'entry_date' => '2026-09-16', 'direction' => 'in', 'amount' => 1.234], 'Presisi field tidak valid: amount'],
        ];
    }

    #[DataProvider('invalidConstrainedRows')]
    public function test_declared_field_constraints_are_enforced(string $entity, array $row, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        app(SchemaValidator::class)->validate($entity, $row);
    }

    public function test_rounded_money_values_are_accepted_despite_binary_float_error(): void
    {
        $validator = app(SchemaValidator::class);

        // Hasil pemecahan PPN inklusif: sah pada scale 2, tetapi representasi
        // binernya tidak eksak. Nilai seperti ini pernah tertolak keliru.
        foreach ([684684.68, 274774.77, 450450.45, 108108.11, 0.01] as $amount) {
            $row = $validator->validate('cash_entries', [
                'id' => 1,
                'entry_date' => '2026-09-17',
                'direction' => 'in',
                'amount' => $amount,
            ]);

            $this->assertSame($amount, $row['amount']);
        }
    }

    public function test_unknown_top_level_field_and_path_traversal_are_rejected(): void
    {
        try {
            app(SchemaValidator::class)->validate('contacts', [
                'id' => 1,
                'name' => 'Kontak Uji',
                'company_id' => 'company-lain',
            ]);
            $this->fail('company_id must remain implicit from the company folder.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Field tidak dideklarasikan: company_id', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nama entitas tidak valid');

        EntitySchema::load('../contacts');
    }
}
