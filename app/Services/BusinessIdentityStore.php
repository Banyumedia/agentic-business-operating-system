<?php

namespace App\Services;

use App\Contracts\CompanyContext;
use App\Models\BusinessIdentity;
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

        // Jalur Eloquent: company berupa ID numerik -> baca tabel business_identities.
        // Jalur JSON demo (D-41): company berupa slug -> baca file per-slug.
        if (ctype_digit($company)) {
            $identity = BusinessIdentity::where('company_id', (int) $company)
                ->where('is_default', true)
                ->first();

            if (! $identity) {
                throw new InvalidArgumentException("Identitas usaha tidak ditemukan: {$company}");
            }

            return [
                'id' => $identity->id,
                'legal_name' => $identity->legal_name,
                'npwp' => $identity->npwp,
                'address' => $identity->address,
                'tax_mode' => $identity->tax_mode,
                'tax_rate' => $identity->tax_rate !== null ? (float) $identity->tax_rate : null,
                'price_includes_tax' => (bool) $identity->price_includes_tax,
            ];
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

        // Tarif untuk tenant taxable wajib eksplisit. Sebelumnya tarif yang
        // hilang jatuh ke DEFAULT_RATE lewat `?? 0.11`, tetapi cabang itu tidak
        // menyala untuk nilai `0.0` (kolom Eloquent berdefault `0.00`), sehingga
        // tenant taxable diam-diam memungut 0% sementara jalur JSON tanpa tarif
        // menampilkan 11% — dua sumber data menjawab beda. PKP yang memungut 0%
        // adalah konfigurasi mustahil, jadi tarif null maupun 0 DITOLAK, bukan
        // ditambal default (D-74/TX-01).
        $rate = $identity['tax_rate'] ?? null;
        if ($rate === null) {
            throw new InvalidArgumentException("Tarif pajak wajib diisi untuk usaha ber-PPN: {$company}");
        }

        if (! is_int($rate) && ! is_float($rate)) {
            throw new InvalidArgumentException("Tarif pajak identitas usaha tidak valid: {$company}");
        }

        if ((float) $rate <= 0.0) {
            throw new InvalidArgumentException("Tarif pajak usaha ber-PPN tidak boleh nol: {$company}");
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
