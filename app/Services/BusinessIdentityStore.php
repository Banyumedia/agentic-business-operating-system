<?php

namespace App\Services;

use App\Contracts\CompanyContext;
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

    public function __construct(private readonly CompanyContext $companyContext) {}

    /** @return array<string, mixed> */
    public function read(string $company): array
    {
        if ($company !== $this->companyContext->current()) {
            throw new \LogicException('Akses identitas usaha lintas company ditolak.');
        }

        $path = $this->path($company);
        $disk = Storage::disk('company-json');

        if (! $disk->exists($path)) {
            throw new InvalidArgumentException("Identitas usaha tidak ditemukan: {$company}");
        }

        $identity = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($identity) || array_is_list($identity)) {
            throw new JsonException("Identitas usaha harus object: {$company}");
        }

        if (! isset($identity['id']) || ! is_int($identity['id']) || $identity['id'] < 1) {
            throw new InvalidArgumentException("ID identitas usaha tidak valid: {$company}");
        }

        return $identity;
    }

    /**
     * Profil pajak efektif.
     *
     * Mode wajib eksplisit. Konfigurasi fiskal yang hilang atau tidak dikenal
     * ditolak karena default diam-diam dapat menghentikan pemungutan pajak.
     */
    public function taxProfile(string $company): TaxProfile
    {
        $identity = $this->read($company);
        $mode = $identity['tax_mode'] ?? null;

        if ($mode === null) {
            throw new InvalidArgumentException("Mode pajak identitas usaha belum dikonfigurasi: {$company}");
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
