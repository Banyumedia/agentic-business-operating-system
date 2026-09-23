<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['company_id', 'legal_name', 'npwp', 'address', 'price_includes_tax', 'tax_mode', 'tax_rate', 'is_default', 'fiscal_locked_at'])]
class BusinessIdentity extends Model
{
    use HasFactory;

    /**
     * Field fiskal yang dikunci setelah `fiscal_locked_at` tercap (D-74).
     * Mengubahnya di tengah jalan membuat dokumen lama dan baru mengikuti dua
     * aturan pajak berbeda tanpa jejak.
     *
     * @var list<string>
     */
    private const FISCAL_FIELDS = ['tax_mode', 'price_includes_tax', 'tax_rate'];

    /**
     * Penegakan kunci di lapisan model — bukan sekadar UI tanpa tombol.
     * Berlaku untuk setiap penulis (Livewire, TenantBot, command) karena semua
     * lewat sini. Baris tanpa `fiscal_locked_at` (identitas lama sebelum TX-02)
     * TIDAK ikut terkunci diam-diam: kunci hanya menolak perubahan field fiskal
     * ketika baris memang **sudah** terkunci saat perubahan diminta.
     */
    protected static function booted(): void
    {
        static::updating(function (self $identity): void {
            // Kunci yang relevan adalah keadaan tersimpan (getOriginal), bukan
            // nilai baru — mencap kunci itu sendiri harus tetap boleh.
            if ($identity->getOriginal('fiscal_locked_at') === null) {
                return;
            }

            foreach (self::FISCAL_FIELDS as $field) {
                if ($identity->isDirty($field)) {
                    throw new LogicException(
                        'Konfigurasi pajak sudah dikunci saat pendaftaran dan tidak dapat diubah (D-74). '
                        .'Perubahan mode pajak menyangkut pembukuan berjalan dan memerlukan jalur khusus.'
                    );
                }
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_includes_tax' => 'boolean',
            'tax_mode' => 'string',
            'tax_rate' => 'decimal:2',
            'is_default' => 'boolean',
            'fiscal_locked_at' => 'datetime',
        ];
    }

    /**
     * The company this identity belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
