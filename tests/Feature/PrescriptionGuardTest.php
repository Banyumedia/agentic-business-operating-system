<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Prescription;
use App\Services\Domain\PrescriptionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PrescriptionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_keras_drug_without_prescription(): void
    {
        $company = Company::factory()->create();
        $item = new Item([
            'company_id' => $company->id,
            'name' => 'Obat Keras',
            'type' => 'product',
            'is_active' => true,
            'attributes' => ['drug_class' => 'keras'],
        ]);
        $item->save();

        $guard = new PrescriptionGuard;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Obat keras memerlukan resep.');

        $guard->ensureCanBeOrdered($item, null);
    }

    public function test_rejects_keras_drug_with_unverified_prescription(): void
    {
        $company = Company::factory()->create();
        $item = new Item([
            'company_id' => $company->id,
            'name' => 'Obat Keras',
            'type' => 'product',
            'is_active' => true,
            'attributes' => ['drug_class' => 'keras'],
        ]);
        $item->save();

        $prescription = Prescription::factory()->create([
            'company_id' => $company->id,
            'stage' => 'draft',
        ]);

        $guard = new PrescriptionGuard;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Obat keras memerlukan resep yang sudah diverifikasi.');

        $guard->ensureCanBeOrdered($item, $prescription->id);
    }

    public function test_allows_keras_drug_with_verified_prescription(): void
    {
        $company = Company::factory()->create();
        $item = new Item([
            'company_id' => $company->id,
            'name' => 'Obat Keras',
            'type' => 'product',
            'is_active' => true,
            'attributes' => ['drug_class' => 'keras'],
        ]);
        $item->save();

        $prescription = Prescription::factory()->create([
            'company_id' => $company->id,
            'stage' => 'verified',
        ]);

        $guard = new PrescriptionGuard;
        $guard->ensureCanBeOrdered($item, $prescription->id);

        $this->assertTrue(true);
    }

    public function test_allows_bebas_drug_without_prescription(): void
    {
        $company = Company::factory()->create();
        $item = new Item([
            'company_id' => $company->id,
            'name' => 'Obat Bebas',
            'type' => 'product',
            'is_active' => true,
            'attributes' => ['drug_class' => 'bebas'],
        ]);
        $item->save();

        $guard = new PrescriptionGuard;
        $guard->ensureCanBeOrdered($item, null);

        $this->assertTrue(true);
    }
}
