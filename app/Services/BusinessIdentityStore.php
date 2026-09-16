<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;

/**
 * Membaca identitas usaha (`business_identity.json`) sebagai data.
 *
 * Fase 3 menggantinya dengan tabel `business_identities` tanpa mengubah
 * pemanggil: kontraknya tetap `taxProfile()`.
 */
class BusinessIdentityStore
{
    private const DEFAULT_RATE = 0.11;

    /** @return array<string, mixed> */
    public function read(string $company): array
    {
        $path = $this->path($company);
        $disk = Storage::disk('company-json');

        if (! $disk->exists($path)) {
            return [];
        }

        $identity = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($identity) || array_is_list($identity)) {
            throw new JsonException("Identitas usaha harus object: {$company}");
        }

        return $identity;
    }

    /**
     * Profil pajak efektif.
     *
     * Bila kunci `tax_mode` tidak ada, usaha dianggap non-PKP - default yang
     * sesuai mayoritas klien (D-44) dan tidak pernah memunculkan pajak yang
     * tidak diminta. Nilai yang ada tetapi tidak dikenali **ditolak keras**,
     * karena diam-diam menganggapnya non-PKP berarti berhenti memungut PPN
     * pada usaha yang sebenarnya PKP.
     */
    public function taxProfile(string $company): TaxProfile
    {
        $identity = $this->read($company);
        $mode = $identity['tax_mode'] ?? null;

        if ($mode === null) {
            return TaxProfile::nonTaxable();
        }

        if (! in_array($mode, ['taxable', 'non_taxable'], true)) {
            throw new InvalidArgumentException("Mode pajak identitas usaha tidak dikenal: {$company}");
        }

        if ($mode === 'non_taxable') {
            return TaxProfile::nonTaxable();
        }

        $rate = $identity['tax_rate'] ?? self::DEFAULT_RATE;
        if (! is_int($rate) && ! is_float($rate)) {
            throw new InvalidArgumentException("Tarif pajak identitas usaha tidak valid: {$company}");
        }

        $includes = $identity['price_includes_tax'] ?? false;
        if (! is_bool($includes)) {
            throw new InvalidArgumentException("Konfigurasi harga inklusif tidak valid: {$company}");
        }

        return new TaxProfile(taxable: true, priceIncludesTax: $includes, rate: (float) $rate);
    }

    private function path(string $company): string
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $company)) {
            throw new InvalidArgumentException('Identitas usaha tidak valid.');
        }

        return "json/{$company}/business_identity.json";
    }
}
