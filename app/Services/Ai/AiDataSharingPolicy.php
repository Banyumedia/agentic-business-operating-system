<?php

namespace App\Services\Ai;

use App\Models\Company;
use App\Models\ModuleSetting;
use App\Services\FeatureResolver;
use InvalidArgumentException;

/**
 * Gerbang D-50(f): data pada kapabilitas bertanda sensitif tidak pernah
 * dikirim ke model AI kecuali owner mengaktifkannya eksplisit per-kapabilitas.
 * Default mati.
 *
 * Data-driven (D-31): daftar kapabilitas sensitif dari
 * FeatureResolver::SENSITIVE_CAPABILITIES (katalog terkunci D-32); entity/field
 * yang ditahan dideklarasikan sebagai peta, bukan cabang kode per industri.
 * Kunci peta adalah kunci kapabilitas Tier B terkunci (D-32/D-33).
 */
class AiDataSharingPolicy
{
    public const SETTINGS_MODULE = 'ai';

    public const SETTINGS_KEY = 'data_sharing';

    /**
     * Seluruh baris entity ditahan, bukan sebagian field: untuk data kesehatan
     * (D-50) kombinasi tanggal + tahap + pemeriksa sudah cukup mengidentifikasi
     * pasien, jadi redaksi per-field saja tidak memadai.
     *
     * @var array<string, array{entity: string, fields: list<string>}>
     */
    private const WITHHELD = [
        'pharmacy.prescription' => [
            'entity' => 'prescriptions',
            'fields' => [
                'patient_contact_id',
                'doctor_name',
                'doctor_sip',
                'extracted_lines',
                'stage',
                'verified_by_user_id',
                'verified_at',
                'served_at',
            ],
        ],
        'addon.payroll_advanced' => [
            'entity' => 'payrolls',
            'fields' => [
                'employee_id',
                'gross_salary',
                'deductions',
                'bpjs_kesehatan',
                'bpjs_ketenagakerjaan',
                'pph21',
                'net_salary',
            ],
        ],
    ];

    /** @return list<string> */
    public function sensitiveCapabilities(): array
    {
        return FeatureResolver::SENSITIVE_CAPABILITIES;
    }

    public function isSensitive(string $capability): bool
    {
        return in_array($capability, FeatureResolver::SENSITIVE_CAPABILITIES, true);
    }

    /** @return list<string> */
    public function withheldFields(string $capability): array
    {
        return self::WITHHELD[$capability]['fields'] ?? [];
    }

    public function entityFor(string $capability): ?string
    {
        return self::WITHHELD[$capability]['entity'] ?? null;
    }

    /**
     * Fail-closed berlapis, ketiganya wajib: (1) kapabilitas memang sensitif;
     * (2) opt-in owner boolean true eksplisit; (3) kapabilitasnya sendiri aktif
     * - feature() sudah memuat persetujuan privasi D-50(a) lewat
     * FeatureResolver, jadi consent dicabut menutup pengiriman walau opt-in
     * masih menyala.
     */
    public function allowsSharing(Company $company, string $capability): bool
    {
        if (! $this->isSensitive($capability)) {
            return false;
        }

        if (($this->optIn($company)[$capability] ?? false) !== true) {
            return false;
        }

        return $company->feature($capability);
    }

    /**
     * Nilai non-boolean diabaikan (dianggap mati) supaya data lama/rusak tidak
     * pernah membuka akses.
     *
     * @return array<string, bool>
     */
    public function optIn(Company $company): array
    {
        $row = ModuleSetting::where('company_id', $company->id)
            ->where('module_name', self::SETTINGS_MODULE)
            ->first();

        $stored = is_array($row?->settings_json[self::SETTINGS_KEY] ?? null)
            ? $row->settings_json[self::SETTINGS_KEY]
            : [];

        $optIn = [];
        foreach ($this->sensitiveCapabilities() as $capability) {
            $optIn[$capability] = ($stored[$capability] ?? null) === true;
        }

        return $optIn;
    }

    /**
     * Hanya kapabilitas sensitif yang boleh muncul, nilainya wajib boolean asli
     * - bukan "1"/"true"/1 - supaya tidak ada jalur yang membuka data sensitif
     * karena coercion tipe.
     *
     * @param  array<mixed, mixed>  $input
     * @return array<string, bool>
     */
    public function normalizeOptIn(array $input): array
    {
        $normalized = [];

        foreach ($input as $capability => $enabled) {
            if (! is_string($capability) || ! $this->isSensitive($capability)) {
                throw new InvalidArgumentException(
                    'Kapabilitas tidak terdaftar sebagai sensitif: '.(is_string($capability) ? $capability : gettype($capability))
                );
            }

            if (! is_bool($enabled)) {
                throw new InvalidArgumentException("Nilai opt-in harus boolean: {$capability}");
            }

            $normalized[$capability] = $enabled;
        }

        return $normalized;
    }

    /** @param array<string, bool> $optIn */
    public function store(Company $company, array $optIn): void
    {
        $row = ModuleSetting::firstOrNew([
            'company_id' => $company->id,
            'module_name' => self::SETTINGS_MODULE,
        ]);

        $json = $row->settings_json ?? [];
        $existing = is_array($json[self::SETTINGS_KEY] ?? null) ? $json[self::SETTINGS_KEY] : [];
        $json[self::SETTINGS_KEY] = array_merge($existing, $optIn);

        $row->settings_json = $json;
        $row->save();
    }

    public function withheldReason(string $capability): string
    {
        return 'Saya tidak memiliki akses ke data '.$capability
            .'. Pengiriman data kapabilitas sensitif ke AI dimatikan secara bawaan '
            .'dan hanya dapat diaktifkan owner usaha di pengaturan.';
    }
}
