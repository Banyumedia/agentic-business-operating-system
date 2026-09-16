<?php

namespace App\Services;

use InvalidArgumentException;

class TerminologyResolver
{
    /** @var array<string, string> */
    private const DEFAULTS = [
        'contact' => 'Kontak',
        'contacts' => 'Kontak',
        'deal' => 'Peluang',
        'deals' => 'Peluang',
        'project' => 'Proyek',
        'projects' => 'Proyek',
        'resource' => 'Sumber Daya',
        'resources' => 'Sumber Daya',
        'booking' => 'Booking',
        'bookings' => 'Booking',
        'item' => 'Barang',
        'items' => 'Barang',
        'order' => 'Pesanan',
        'orders' => 'Pesanan',
        'staff' => 'Staf',
        'staffs' => 'Staf',
        'invoice' => 'Tagihan',
        'invoices' => 'Tagihan',
        'vendor' => 'Vendor',
        'vendors' => 'Vendor',
    ];

    public function __construct(private readonly CompanyPresetResolver $companyPreset) {}

    public function resolve(string $key): string
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            if (app()->environment(['local', 'testing'])) {
                throw new InvalidArgumentException("Kunci istilah tidak terdaftar: {$key}");
            }

            return $key;
        }

        ['settings' => $settings, 'preset' => $preset] = $this->companyPreset->current();
        $overrides = $this->overrides($settings);

        return $overrides[$key]
            ?? $preset['terminology'][$key]
            ?? self::DEFAULTS[$key];
    }

    public function flushCache(): void
    {
        $this->companyPreset->flushCache();
    }

    /** @param array<string, mixed> $settings
     * @return array<string, string>
     */
    private function overrides(array $settings): array
    {
        $overrides = $settings['terminology'] ?? [];
        if (! is_array($overrides) || ($overrides !== [] && array_is_list($overrides))) {
            throw new InvalidArgumentException('Override istilah harus object.');
        }

        foreach ($overrides as $key => $term) {
            if (! is_string($key) || ! array_key_exists($key, self::DEFAULTS) || ! is_string($term) || trim($term) === '') {
                throw new InvalidArgumentException('Override istilah tidak valid: '.(is_string($key) ? $key : gettype($key)));
            }
        }

        return $overrides;
    }
}
