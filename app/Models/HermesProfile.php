<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class HermesProfile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_platform_provided' => 'boolean',
            'last_ping_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(HermesNode::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'hermes_profile_companies')
            ->withPivot(['role', 'is_default', 'created_at']);
    }

    public function conversationContexts(): HasMany
    {
        return $this->hasMany(HermesConversationContext::class);
    }

    /**
     * Hash token bot yang disimpan di `webhook_secret_reference`.
     *
     * Kolom itu dulu menyimpan **token apa adanya**, dan `AuthenticateTenantBot`
     * mencocokkannya verbatim. Artinya satu baris basis data yang bocor - backup,
     * dump debugging, akses baca ke replika - sudah cukup untuk menyamar sebagai bot
     * tenant. Tidak ada yang perlu dipecahkan lebih dulu.
     *
     * **Kenapa SHA-256 telanjang, bukan bcrypt/argon.** Token dicari **berdasarkan
     * nilainya** (satu query), bukan diverifikasi terhadap baris yang sudah
     * diketahui, jadi hash bersalt menuntut pemindaian seluruh tabel. Yang membuat
     * SHA-256 memadai di sini adalah entropi tokennya sendiri: 40 karakter acak,
     * bukan kata sandi buatan manusia. Salt dan work factor ada untuk melawan
     * rendahnya entropi kata sandi; di sini keduanya tidak menambah apa pun.
     */
    public static function hashBotToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Mencari profil dari token bot mentah.
     *
     * Satu-satunya tempat pencocokan token terjadi, supaya tidak ada pemanggil yang
     * lupa menghash dan diam-diam kembali membandingkan plaintext.
     */
    public static function findByBotToken(string $token): ?self
    {
        if (trim($token) === '') {
            return null;
        }

        return static::query()->where('webhook_secret_reference', static::hashBotToken($token))->first();
    }

    protected static function booted(): void
    {
        static::saving(function (HermesProfile $profile) {
            if ($profile->type === 'primary') {
                $existingPrimary = static::where('owner_user_id', $profile->owner_user_id)
                    ->where('type', 'primary')
                    ->where('id', '!=', $profile->id)
                    ->exists();

                if ($existingPrimary) {
                    throw new LogicException('An owner can only have one primary profile.');
                }
            }

            // Add-on yang **dijual** wajib tertaut ke baris billing-nya. Profil
            // milik platform (bot dev dan bot CS kita sendiri) tidak punya add-on
            // berbayar; memaksanya membuat baris billing palsu hanya untuk lolos
            // aturan ini adalah jebakan, bukan solusi. Kelonggarannya sempit dan
            // hanya berlaku bila `is_platform_provided` benar.
            if ($profile->type === 'addon'
                && empty($profile->billing_addon_id)
                && ! $profile->is_platform_provided) {
                throw new LogicException('An addon profile must have a billing_addon_id.');
            }
        });
    }
}
