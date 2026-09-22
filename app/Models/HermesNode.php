<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HermesNode extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Profil yang benar-benar menempel pada node ini.
     *
     * Ini, bukan kolom `active_profiles`, adalah sumber kebenaran soal seberapa
     * penuh sebuah node: pencabutan profil (`CleanupExpiredTrials`) hanya menulis
     * `node_id => null`, jadi kenyataan bisa berubah tanpa ada yang menyentuh
     * kolomnya.
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(HermesProfile::class, 'node_id');
    }

    /**
     * Jumlah profil yang nyata menempel, dihitung dari tabel - bukan dibaca dari
     * kolom yang harus dijaga konsisten dengan tangan.
     */
    public function attachedProfileCount(): int
    {
        return $this->profiles()->count();
    }

    /**
     * Selaraskan kolom `active_profiles` dengan kenyataan.
     *
     * Kolom itu dipertahankan karena ditampilkan di `/admin/hermes-nodes`, tetapi
     * perannya cache yang dihitung ulang. Dulu ia dijaga dengan `increment()` dan
     * tidak punya jalan turun sama sekali, sehingga setiap trial yang kedaluwarsa
     * membuat node melaporkan diri lebih penuh daripada kenyataannya - lalu
     * menolak profil yang sah, jauh dari penyebabnya.
     *
     * Sengaja tidak memakai `increment`/`decrement` di mana pun lagi: dua
     * mekanisme untuk satu angka pasti menyimpang.
     */
    public function syncActiveProfiles(): int
    {
        $real = $this->attachedProfileCount();

        if ((int) $this->active_profiles !== $real) {
            // `forceFill` + `saveQuietly` supaya penyelarasan cache tidak
            // membangkitkan event model yang bisa disalahartikan sebagai
            // perubahan konfigurasi node oleh operator.
            $this->forceFill(['active_profiles' => $real])->saveQuietly();
        }

        return $real;
    }

    /**
     * Apakah node ini sudah tidak bisa menerima profil baru.
     *
     * Dibandingkan dengan hitungan nyata supaya `max_capacity` tetap berarti
     * meskipun kolom cache-nya sempat menyimpang.
     */
    public function isAtCapacity(): bool
    {
        return $this->attachedProfileCount() >= (int) $this->max_capacity;
    }
}
