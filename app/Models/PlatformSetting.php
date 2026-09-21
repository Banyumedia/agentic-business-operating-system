<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-level key-value settings (UR-04/D-05): rekening pembayaran,
 * QRIS, dan konfigurasi lain yang dapat diubah Super Admin runtime tanpa
 * restart. Bukan data tenant (tanpa company_id).
 */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public $timestamps = true;
}
