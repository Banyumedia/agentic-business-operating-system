<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prospek yang japri bot CS platform sebelum menjadi pelanggan (D-76).
 *
 * Bukan `SupportTicket`: tiket menuntut company (T-17), prospek justru belum
 * punya. Sengaja tanpa relasi ke `User`/`Company` - menautkannya berarti menebak
 * identitas dari nomor WA (dilarang D-66). Data disimpan minimal dan bisa dihapus
 * lewat nomornya.
 */
class Lead extends Model
{
    protected $guarded = ['id'];

    /**
     * Kosakata status yang sah. Dijaga di lapisan aplikasi, bukan enum DB, supaya
     * konsisten dengan pola `hermes_nodes.status`/`hermes_profiles.status` yang
     * sudah beralih ke string tervalidasi.
     *
     * @var list<string>
     */
    public const STATUSES = ['baru', 'dihubungi', 'dikonversi', 'ditutup'];
}
