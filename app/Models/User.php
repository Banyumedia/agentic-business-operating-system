<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'wa_number', 'wa_is_verified', 'current_company_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wa_is_verified' => 'boolean',
        ];
    }

    /**
     * Menyeragamkan nomor WhatsApp sebelum dibandingkan: buang non-digit lalu
     * ubah awalan lokal `08` menjadi `628`. Nilai kosong tetap kosong supaya
     * pemanggil dapat memperlakukannya sebagai gagal, bukan cocok.
     *
     * Ditaruh di model User karena pencocokan nomor-ke-user adalah urusan User,
     * dan semua consumer WA (SenderIdentity, InteractionFilter, middleware bot,
     * undangan tim) dapat menjangkaunya dari sini — menghindari salinan logika
     * normalisasi yang bisa menyimpang antar-tempat (D-66).
     */
    public static function normalizeWaNumber(?string $number): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $number) ?? '';

        if (str_starts_with($digits, '08')) {
            return '628'.substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Mencari user berdasarkan nomor WhatsApp yang **sudah terverifikasi**
     * (D-66). Nomor cocok tapi belum terverifikasi mengembalikan null: nomor WA
     * berpindah tangan, jadi kecocokan saja bukan bukti identitas. Pencocokan
     * dilakukan setelah normalisasi agar format `08...` dan `628...` dianggap
     * satu nomor yang sama, konsisten dengan consumer WA lain.
     */
    public static function findVerifiedByWaNumber(?string $number): ?self
    {
        $normalized = static::normalizeWaNumber($number);

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->whereNotNull('wa_number')
            ->where('wa_is_verified', true)
            ->get()
            ->first(fn (self $user): bool => static::normalizeWaNumber($user->wa_number) === $normalized);
    }

    /**
     * The companies owned by this user.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_user_id');
    }

    /**
     * The currently active company for this user.
     */
    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }
}
