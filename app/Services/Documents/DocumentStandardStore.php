<?php

namespace App\Services\Documents;

use App\Models\Company;
use App\Models\ModuleSetting;

/**
 * Standar dokumen per tenant, disimpan sebagai **data** (D-31).
 *
 * Alasan keberadaannya: D-69 mewajibkan **satu skill melayani banyak tenant**.
 * Perbedaan antar tenant harus datang sebagai data dari API kita, bukan sebagai
 * berkas skill yang dipecah per industri - memecahnya adalah "industri = kode"
 * yang dilarang D-31, hanya berpindah tempat ke Hermes.
 *
 * Disimpan di `module_settings` dengan `module_name = 'documents'`, bukan sebagai
 * kolom baru per jenis dokumen, supaya menambah jenis dokumen tidak memerlukan
 * migration.
 */
class DocumentStandardStore
{
    public const MODULE = 'documents';

    /**
     * Bawaan yang **didokumentasikan**, bukan tebakan diam-diam.
     *
     * Tenant baru belum menyetel apa pun, dan itu keadaan mayoritas. Mengembalikan
     * galat untuk keadaan itu akan membuat skill gagal justru pada tenant yang
     * paling banyak jumlahnya.
     *
     * @var array<string, array<string, mixed>>
     */
    public const DEFAULTS = [
        'proposal' => [
            'sections' => [
                'Ringkasan',
                'Latar belakang',
                'Lingkup pekerjaan',
                'Jadwal',
                'Harga',
                'Ketentuan',
            ],
            'tone' => 'formal',
            'language' => 'id',
        ],
        'research' => [
            'sections' => [
                'Pertanyaan',
                'Temuan',
                'Risiko',
                'Rekomendasi',
            ],
            'tone' => 'ringkas',
            'language' => 'id',
        ],
    ];

    /**
     * @return array{standards: array<string, array<string, mixed>>, is_default: bool}
     */
    public function forCompany(Company $company): array
    {
        $row = ModuleSetting::query()
            ->where('company_id', $company->id)
            ->where('module_name', self::MODULE)
            ->first();

        $stored = $row?->settings_json['standards'] ?? null;

        if (! is_array($stored) || $stored === []) {
            return ['standards' => self::DEFAULTS, 'is_default' => true];
        }

        // Standar tersimpan ditimpakan **di atas** bawaan per jenis dokumen, jadi
        // tenant yang hanya menyetel `tone` tidak kehilangan daftar `sections`.
        $merged = self::DEFAULTS;

        foreach ($stored as $document => $settings) {
            if (! is_array($settings)) {
                continue;
            }

            $merged[$document] = array_replace($merged[$document] ?? [], $settings);
        }

        return ['standards' => $merged, 'is_default' => false];
    }
}
