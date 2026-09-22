# T-82 — Klien control plane Hermes + daftar-putih path

Ditulis di berkas terpisah karena `EXECUTION_PLAN.md`, `AUTOPILOT_STATUS.md`,
`00-DECISIONS.md`, dan `HERMES_NODE_CONTRACT.md` masih memuat perubahan sesi
antrean yang belum di-commit. Penandaan state T-82 di tabel menyusul.

## Yang dibangun

Satu pintu ke dashboard API Hermes (D-72):

| Berkas | Isi |
|---|---|
| `database/migrations/2026_09_23_120000_add_control_plane_to_hermes_nodes.php` | `control_url` + `control_secret_reference`, keduanya nullable |
| `app/Services/Hermes/ControlPlanePaths.php` | daftar-putih 19 entri + 8 keluarga terlarang, sebagai **konstanta** |
| `app/Services/Hermes/HermesControlPlaneClient.php` | satu-satunya pemanggil |
| `app/Services/Hermes/ControlPlane*.php`, `NodeHasNoControlPlane.php` | 7 pengecualian, satu per keadaan |
| `app/Console/Commands/HermesControlPing.php` | `bos:hermes-control-ping` |
| `config/hermes.php` | `control.timeout` + `control_secrets` (nama → env) |
| `app/Livewire/Admin/HermesNodeManager.php` + view | kolom control plane di form, terpisah dari bridge |

## Tiga fakta Hermes yang mengubah desain, dibaca dari kode bukan dari dokumen

Sumber: `%LOCALAPPDATA%\hermes\hermes-agent\hermes_cli\web_server.py` (695 KB) dan
`hermes_cli/web_routers/*.py`. Dibaca dengan `Select-String`, **bukan**
`grep_search` — alat itu tidak menjangkau luar workspace dan menjawab "no matches"
tanpa peringatan.

1. **Parameter onboarding bernama `{pairing_id}`**, bukan `{id}` seperti tertulis di
   antrean. Template yang salah berarti path tidak akan pernah cocok.
2. **Tempat nama profil dikirim berbeda per endpoint.** Query untuk `/api/pairing`,
   `/api/status`, `/api/messaging/platforms`, `clear-pending`, dan `apply`; **body**
   untuk `pairing/approve`, `pairing/revoke`, dan `onboarding/start` — ketiganya
   membaca `body.profile` (`_pairing_store(body.profile)`). Ini bukan detail gaya:
   mengirim profil di tempat yang salah membuat Hermes **mengabaikannya**, lalu
   permintaan jatuh ke profil yang sedang aktif. Pada armada multi-tenant itu berarti
   mengonfigurasi tenant yang salah **tanpa satu galat pun muncul**.
3. **`/api/health` dan `/api/status` tidak butuh autentikasi**; sisanya butuh.
   `/api/health` menjawab `{"ok":true,"version":"0.19.1","auth_required":true}`.

Selain itu daftar path di antrean diverifikasi satu per satu: semuanya ada, plus
beberapa yang sengaja **tidak** dimasukkan ke daftar-putih (`/api/profiles/active`,
`/api/profiles/{name}/describe-auto`, `/api/messaging/platforms/{id}` PUT,
`/api/messaging/telegram/*`). `POST /api/profiles/active` khususnya adalah pengubah
state bersama — persis yang dihindari butir (c) T-82.

## Keputusan yang saya ambil dan alasannya

**Daftar-putih mencocokkan template, bukan path terisi.** Kalau yang dicocokkan
adalah path setelah parameter disisipkan, nama profil `..%2Ffs%2Fwrite-text` bisa
kabur dari daftar. Parameter di-encode per segmen dengan `rawurlencode`.

**Dua lapis, bukan satu.** Daftar-putih menahan path tak dikenal; daftar terlarang
menahan baris baru yang keliru ditambahkan ke daftar-putih oleh manusia yang sedang
tergesa. Ada test yang membuktikan keduanya tidak pernah beririsan.

**`/open-terminal` masuk daftar terlarang sebagai pola, bukan path utuh.** Bentuk
aslinya `/api/profiles/{name}/open-terminal` — kalau yang dilarang hanya prefiks
`/api/profiles/`, seluruh lajur profil ikut mati; kalau yang dilarang path utuh
dengan nama tertentu, nama lain lolos.

**Referensi rahasia kosong diperlakukan sama dengan `none`.** Keduanya berarti
"tidak ada autentikasi", dan keduanya hanya sah di loopback — aturan yang sama
dengan bridge (`BridgeGateway::authHeaders()`). Kalau kosong diizinkan lewat begitu
saja, node publik tanpa token akan tampak sah sampai panggilan pertama.

**Redaksi log dua lapis.** Kunci sensitif (`qr_payload`, `soul`, `token`, `env`,
dst) diganti sebelum badan respons dipotong 300 karakter; lalu nilai rahasia yang
terkonfigurasi disapu dari teks bebas sebagai jaring terakhir. `qr_payload` bukan
sekadar data: siapa pun yang memilikinya bisa memasang perangkat sebagai nomor itu.

**Path terisi tidak masuk log, hanya template.** Nama profil tidak perlu ikut
tercatat di setiap kegagalan.

## Test

`tests/Feature/Hermes/ControlPlaneClientTest.php` — 17 test, 11 negatif. Yang
penting: seluruh penolakan diuji dengan `Http::assertNothingSent()`, jadi yang
dibuktikan adalah **tidak ada permintaan yang pernah dibuat** — bukan sekadar
"melempar". Ditambah 4 test di `HermesNodeRegistrationTest` untuk form admin, dan
satu penjaga arsitektur: tidak ada berkas lain di `app/` yang boleh memuat path
dashboard (`/api/profiles`, `/api/pairing`, `/api/messaging`, `/api/system/stats`).

**Satu cacat di test saya sendiri yang sempat menghasilkan hijau palsu:**
`Http::fake()` **menambah** stub, tidak menggantinya. Memanggilnya ulang di dalam
loop membuat stub pertama menang untuk seluruh iterasi, sehingga pemetaan lima kode
status tampak lulus padahal hanya 401 yang pernah diuji — dan 404 terbukti salah
ketika stubnya benar. Sudah diganti `Http::fakeSequence()` dengan komentar
peringatannya. Pola yang sama seperti T-57/T-58: hijau di atas nilai fabrikasi.

## Gate

- `DATA_SOURCE=json php artisan test` → **1.218 passed / 5.748 assertions, 0 gagal**
- `vendor/bin/pint --test` → **PASS 549 berkas**
- `php artisan migrate --force` → migration baru DONE
- `npm run build` → PASS

## Verifikasi terhadap node Hermes sungguhan

Dashboard API hidup di `http://127.0.0.1:9119`. Node dev diisi `control_url` itu
dengan `control_secret_reference = none` (sah karena loopback).

| Panggilan | Hasil nyata |
|---|---|
| `bos:hermes-control-ping --node=2` | `OK Host Lokal — version 0.19.1` |
| `GET /api/status?profile=...` | **200**, mengembalikan `version`, `release_date`, `config_version`, `latest_config_version`, `can_update_hermes` |
| `GET /api/pairing?profile=...` | **401** → `ControlPlaneUnauthorized` |
| `GET /api/profiles` | **401** → `ControlPlaneUnauthorized` |
| `GET /api/messaging/platforms?profile=...` | **401** → `ControlPlaneUnauthorized` |
| `GET /api/system/stats` | **401** → `ControlPlaneUnauthorized` |
| `POST /api/tools/terminal/run` | `ControlPlanePathDenied`, tanpa permintaan HTTP |
| `GET /api/pairing` tanpa profil | `ControlPlanePathDenied`, tanpa permintaan HTTP |

Jadi pemetaan 401 dan kedua penolakan lokal **terbukti terhadap node hidup**, bukan
hanya terhadap `Http::fake()`.

## Temuan yang perlu masuk antrean (bukan saya yang menandainya)

1. **T-84 sebagian tidak lagi terhalang H-05.** Keadaan tingkat node
   (`/api/health`, `/api/status`) bisa dibaca **sekarang** tanpa autentikasi, dan
   `/api/status` sudah memuat versi + versi config + apakah Hermes bisa diperbarui.
   Yang tetap menunggu H-05 adalah keadaan **per platform per profil**
   (`/api/messaging/platforms`). Nilai praktisnya: pemantauan armada tingkat host
   bisa mendarat lebih dulu.
2. **T-83 tetap terhalang penuh.** `GET /api/profiles` menjawab 401, dan tanpa
   daftar profil dari node tidak ada rekonsiliasi yatim/hilang yang bisa dilakukan.
3. **Catatan keamanan yang bukan urusan kita tetapi perlu diketahui:**
   `/api/status` tanpa autentikasi membocorkan versi Hermes dan versi config kepada
   siapa pun yang bisa menjangkau port itu. Di loopback ini wajar; ia menjadi
   masalah kalau port 9119 pernah diekspos. H-05 sebaiknya tidak mengasumsikan
   "yang public hanya /api/health".
4. **410 dan 429 belum pernah dilihat dari node sungguhan.** Pemetaannya hanya
   terbukti terhadap fake; keduanya baru muncul saat ada sesi pairing nyata.

## Yang belum terbukti

- Seluruh **aksi tulis** (buat profil, tulis SOUL, approve pairing, mulai
  onboarding) belum pernah dijalankan terhadap node sungguhan: semuanya 401 sampai
  **H-05** mendarat. Yang terbukti adalah bentuk permintaannya dan seluruh rantai
  penolakannya.
- Klien ini belum dipakai fitur apa pun. T-83/T-84 yang akan memakainya.

---

# T-86 — Penjaga batas control plane + runbook rotasi

Dikerjakan langsung setelah T-82 karena daftar-putih tanpa penjaga hanyalah
kesepakatan lisan.

## Empat penjaga (`tests/Architecture/ControlPlaneBoundaryTest.php`)

| Penjaga | Menutup |
|---|---|
| (a) hanya dua berkas boleh menyebut rute dashboard | memanggil dashboard dari kelas lain |
| (b) permukaan tenant tidak boleh menyentuh klien | memanggil klien yang benar dari permukaan yang salah |
| (c) setiap path daftar-putih punya test yang memakainya | menumpuk izin yang bentuknya belum pernah diperiksa |
| (d) daftar terlarang dipatok sebagai literal | menambah/menghapus larangan tanpa terlihat di review |

## Penjaga (a) langsung menemukan dua pelanggaran pada kode saya sendiri

`bos:hermes-control-ping` menuliskan template pathnya sendiri, dan satu komentar
di `HermesNodeManager` memuat literal path terlarang. Perbaikannya **bukan**
melonggarkan penjaga:

- Klien mendapat metode bernama (`health()`, `status()`, `systemStats()`) sehingga
  pemanggil tidak pernah menulis path. Ditambahkan **saat dipakai**, bukan 19
  sekaligus — metode yang tidak dipakai siapa pun adalah izin yang menumpuk.
- Komentar ditulis ulang tanpa literal, maknanya tetap.

Ini contoh penjaga yang membayar dirinya sendiri di hari pertama.

## Penjaga (c) menemukan 7 izin menumpuk

Dari 19 path di daftar-putih, **7 tidak pernah dipakai test mana pun**:
`PATCH`/`DELETE /api/profiles/{name}`, `PUT /api/profiles/{name}/model`,
`POST /api/pairing/revoke`, dan tiga endpoint sesi onboarding WhatsApp.

Ditutup `tests/Feature/Hermes/ControlPlanePathCoverageTest.php`: 19 path, masing-masing
dipatok URL akhirnya **dan** tempat nama profil mendarat. Sengaja literal dan
berulang, bukan satu loop atas `ControlPlanePaths::ALLOWED` — loop akan otomatis
"meliputi" path apa pun yang kelak ditambahkan, sehingga izin baru masuk tanpa ada
yang pernah memeriksanya. Ditambah satu asersi jumlah (19) supaya daftar dan testnya
harus berubah di commit yang sama.

## Satu penjaga dihapus, bukan ditambah

Versi lemah dari penjaga "satu pintu" yang saya tulis di `ControlPlaneClientTest`
dibuang: ia memakai glob berlapis yang melewatkan direktori lebih dalam, sementara
versi di berkas arsitektur memindai `app/`, `routes/`, `config/` secara rekursif.
Dua penjaga untuk satu aturan pasti menyimpang, dan yang lemah akan dipercaya.

## Runbook (`docs/RUNBOOK_RUNTIME_SERVICE.md`)

Tabel pembeda bridge vs control plane (kolom, rahasia, autentikasi, port,
wewenang), cara memasang rahasia, dan **rotasi lima langkah tanpa jeda**: tambahkan
referensi baru dulu, pasang token berdampingan di Hermes, pindahkan kolom, buktikan
dengan ping, baru cabut yang lama. Rollback-nya satu kolom dan tidak menyentuh
Hermes sama sekali.

Ditambah empat kandidat rute yang paling mungkin berpindah setelah Hermes
diperbarui. Yang paling berbahaya dinyatakan eksplisit: pergeseran **tempat**
parameter `profile` (query vs body) **tidak menimbulkan galat apa pun** — Hermes
hanya mengabaikannya dan memakai profil yang sedang aktif, jadi tenant yang salah
dikonfigurasi tanpa meninggalkan jejak. Runbook juga menyatakan larangan yang
mudah dilanggar saat panik: bila test `ControlPlane*` merah setelah pembaruan,
**jangan** melonggarkan daftar-putih supaya hijau.

## Gate

- `DATA_SOURCE=json php artisan test` → **1.226 passed / 5.771 assertions, 0 gagal**
- `vendor/bin/pint --test` → **PASS 551 berkas**
- `bos:hermes-control-ping --node=2` terhadap node hidup → `OK ... version 0.19.1`
