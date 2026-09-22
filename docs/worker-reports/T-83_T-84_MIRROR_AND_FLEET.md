# T-83 + T-84 — Cermin profil node & pemantauan armada

Ditulis terpisah dari `EXECUTION_PLAN.md` mengikuti pola sesi-sesi sebelumnya;
penandaan state di tabel antrean menyusul.

## Yang dibangun

| Berkas | Isi |
|---|---|
| `app/Services/Hermes/ProfileMirror.php` | cermin read-through + rekonsiliasi dua arah (T-83) |
| `app/Services/Hermes/FleetMonitor.php` | keadaan kanal per profil (T-84) |
| `app/Console/Commands/HermesFleetStatus.php` | `bos:hermes-fleet-status` |
| `app/Services/Hermes/HermesControlPlaneClient.php` | +3 metode bernama: `profiles()`, `profileSoul()`, `messagingPlatforms()` |
| `app/Livewire/Admin/HermesNodeManager.php` + view | penyatuan keduanya di `/admin/hermes-nodes` |
| `config/hermes.php` | `control.mirror_ttl` (20 detik) |
| `tests/Feature/Hermes/HermesProfileMirrorTest.php` | 16 test (10 negatif) |
| `tests/Feature/Hermes/FleetMonitorTest.php` | 11 test (8 negatif) |
| `tests/Feature/Admin/HermesNodeRegistrationTest.php` | +3 test UI |

## Keputusan yang menentukan bentuknya

**Tidak ada tabel bayangan** (D-72 butir 2). Salinan daftar profil akan menyimpang
persis saat node tidak terjangkau — yaitu saat operator paling butuh angka yang
benar. Halaman yang menampilkan salinan basi tanpa mengatakannya lebih berbahaya
daripada halaman yang jujur kosong, karena ia membuat operator yakin tidak ada
masalah. Yang ada hanya cache berumur detik, per node, dan setiap hasil membawa
stempel `diambil_pada`.

**Kegagalan dikembalikan sebagai data, bukan dilempar.** Laman pemantauan yang mati
menghilangkan satu-satunya cara melihat bahwa ada node bermasalah. Dan sebabnya
**dibedakan** sampai ke layar: `tanpa_control_plane`, `belum_berwenang`,
`dibatasi_laju`, `tidak_terjangkau`, dan seterusnya. Dua yang paling sering tertukar
mengirim operator ke tempat yang berbeda — "belum berwenang" adalah kredensial,
"tidak terjangkau" adalah proses dan jaringan.

**Rekonsiliasi terlihat, tidak dirapikan.** `yatim` berarti ada bot berjalan di luar
pembukuan (tidak ada company yang menanggungnya, tidak ada kuota yang dihitung);
`hilang_di_node` berarti pemetaan mati. **Tidak ada penghapusan otomatis**: node yang
sedang sakit atau sedang dipindah akan membuat pembersih otomatis memusnahkan peta
company↔profil (D-37) beserta riwayat yang tidak bisa dibangun ulang.

**Dua sumber keadaan tidak diperas menjadi satu kolom.** `hermes_profiles.status`
ditulis dari **bridge WhatsApp** (T-81, keadaan nomor); `gateway_running` datang dari
**control plane** (keadaan proses). Keduanya bisa berbeda secara sah, dan
kombinasinya yang paling perlu terlihat adalah **gateway hidup sementara nomornya
lepas**. Menggabungkannya akan menyembunyikan tepat keadaan itu. `berjalan_di_node`
juga membedakan `null` ("node tidak mengatakannya") dari `false` ("mati").

**`FleetMonitor` tidak menulis apa pun ke basis data** (T-84 butir c), dan ada test
yang menguncinya: menambah penulis kedua untuk `hermes_profiles.status` dari sumber
berbeda menghasilkan dua kebenaran yang saling menimpa tiap sepuluh menit.

**Cermin tidak dimuat otomatis di `mount()`.** Kalau dimuat otomatis, setiap kunjungan
halaman menembak seluruh armada, dan pada node yang mati operator menunggu seluruh
timeout sebelum satu piksel pun tampil. Ada test negatif yang menguncinya
(`Http::assertNothingSent()` saat halaman dipasang).

## Dua fakta Hermes yang dipakai, dibaca dari kode

Dengan `Select-String` ke `%LOCALAPPDATA%\hermes\hermes-agent` — `grep_search` tidak
menjangkau ke sana dan menjawab "no matches" tanpa peringatan.

1. **Daftar keadaan mati bukan karangan kita.** `_PLATFORM_DEAD_STATES` di
   `web_server.py` = `{fatal, disconnected, stopped}`. Kalau kita menyusun daftar
   sendiri, penilaian kita dan penilaian dashboard Hermes akan berbeda untuk keadaan
   yang sama — dan operator akan percaya yang mana pun yang ia buka lebih dulu.
2. **`GET /api/profiles` tidak pernah 404**: saat pembacaan internalnya gagal ia jatuh
   ke pemindaian direktori. Jadi daftar kosong berarti benar-benar kosong, dan 404
   berarti **rutenya berpindah** — itulah yang dikatakan pesan galatnya.
   `GET /api/profiles/{name}/soul` menjawab **200 dengan `exists: false`** untuk profil
   tanpa SOUL, jadi "tidak ada SOUL" dan "profil tidak ada" tetap dua hal berbeda.

## Satu cacat yang ditangkap test saya sendiri

`FleetMonitor` sempat memakai operator `+` untuk menggabungkan larik hasil. Pada `+`,
**kunci kiri yang menang**, jadi `platform_bermasalah` yang kosong dari larik dasar
menimpa hasil sebenarnya: platform `fatal` dilaporkan sebagai sehat. Diganti
`array_merge`. Ini persis jenis cacat yang tidak menimbulkan galat apa pun dan hanya
terlihat karena ada assertion yang memeriksa isinya, bukan hanya statusnya.

## Gate

| Perintah | Hasil |
|---|---|
| `$env:DATA_SOURCE="json"; php artisan test` | **1.269 passed / 5.905 assertions**, 0 gagal |
| `vendor/bin/pint --test` | PASS, 559 berkas |

## Verifikasi terhadap node hidup

`bos:hermes-fleet-status` dijalankan terhadap dashboard API Hermes di
`127.0.0.1:9119` dengan dua profil dev:

```
| Node       | Profil                | Keadaan         | Platform |
| Host Lokal | Asisten Usaha Uji T81 | belum_berwenang | -        |
| Host Lokal | Bot CS Uji            | belum_berwenang | -        |
2 profil bermasalah. Keadaan `belum_berwenang` berarti H-05 belum terpasang di
Hermes, bukan node yang mati.
```

Exit code 1. Yang terbukti terhadap node sungguhan: pemetaan 401 menjadi
`belum_berwenang`, pesan yang tidak menyalahkan node, dan exit code yang bisa dipakai
penjadwal.

## Yang belum terbukti

- **Jalur sukses belum pernah dilihat dari node sungguhan.** Bentuk `profiles[]`,
  `gateway_running`, keadaan platform, dan rekonsiliasi nyata hanya terbukti terhadap
  `Http::fake()` dan terhadap **kode** Hermes yang dibaca. Semuanya menunggu **H-05**.
- **Rekonsiliasi akan tampak seluruhnya merah pada hari pertama.** Cermin mencocokkan
  lewat `instance_id`, tetapi **tidak ada satu pun jalur kita yang membuat profil di
  node** (`POST /api/profiles` belum dipakai siapa pun). Jadi saat H-05 mendarat,
  setiap baris kita akan tampil `hilang_di_node` dan setiap profil Hermes akan tampil
  `yatim`. Itu jujur, tetapi layar yang seluruhnya merah akan dibaca sebagai bug
  lapisan ini. Yang menutupnya adalah **T-85** (provisioning sampai ke node) — dan
  itu memang baris antrean berikutnya di jalur ini.
- **HTTP 410 dan 429 dari node sungguhan** belum pernah dilihat; pemetaannya hanya
  terbukti terhadap fake.
