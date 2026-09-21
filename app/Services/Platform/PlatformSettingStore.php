<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write pengaturan platform (rekening, QRIS) - Super Admin runtime.
 *
 * Read: DB override dulu, fallback ke config env. Cache 5 menit; write
 * membersihkan cache supaya perubahan langsung terlihat.
 */
class PlatformSettingStore
{
    private const CACHE_KEY = 'platform_settings:all';

    private const TTL = 300;

    /** @return array<string, ?string> */
    public function all(): array
    {
        /** @var array<string, ?string>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $rows = PlatformSetting::query()->get(['key', 'value'])
            ->mapWithKeys(fn ($r) => [$r->key => $r->value])
            ->all();

        Cache::put(self::CACHE_KEY, $rows, self::TTL);

        return $rows;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, ?string>  $values  nilai null = hapus key (fallback env aktif lagi)
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                PlatformSetting::query()->where('key', $key)->delete();

                continue;
            }

            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Nilai efektif rekening: DB override -> env config.
     *
     * @return array{enabled: bool, bank_name: string, bank_account: string, bank_holder: string, qris_path: string}
     */
    public function paymentConfig(): array
    {
        $all = $this->all();

        return [
            'enabled' => array_key_exists('payment_enabled', $all)
                ? $all['payment_enabled'] === '1'
                : (bool) config('billing.manual_payment.enabled'),
            'bank_name' => $all['payment_bank_name'] ?? (string) config('billing.manual_payment.bank.name'),
            'bank_account' => $all['payment_bank_account'] ?? (string) config('billing.manual_payment.bank.account_number'),
            'bank_holder' => $all['payment_bank_holder'] ?? (string) config('billing.manual_payment.bank.account_holder'),
            'qris_path' => $all['payment_qris_path'] ?? (string) config('billing.manual_payment.qris_path'),
        ];
    }
}
