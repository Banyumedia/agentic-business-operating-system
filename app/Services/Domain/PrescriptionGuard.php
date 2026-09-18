<?php

namespace App\Services\Domain;

use App\Models\Item;
use App\Models\Prescription;
use LogicException;

class PrescriptionGuard
{
    /**
     * Prevent restricted items from being added to an order without a verified prescription.
     *
     * @throws LogicException
     */
    public function ensureCanBeOrdered(Item $item, ?int $prescriptionId): void
    {
        $attributes = $item->attributes ?? [];
        $drugClass = $attributes['drug_class'] ?? null;

        if (in_array($drugClass, ['keras', 'psikotropika'])) {
            if (! $prescriptionId) {
                throw new LogicException("Obat {$drugClass} memerlukan resep.");
            }

            $prescription = Prescription::find($prescriptionId);
            if (! $prescription || $prescription->stage !== 'verified') {
                throw new LogicException("Obat {$drugClass} memerlukan resep yang sudah diverifikasi.");
            }
        }
    }
}
