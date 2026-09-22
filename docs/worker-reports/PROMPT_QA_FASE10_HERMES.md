# Prompt: QA Independen — Fase 10 (lajur WhatsApp Hermes)

Salin seluruh isi berkas ini ke sesi baru. Sesi itu **hanya** QA; sesi writer
berjalan terpisah dan tidak boleh diganggu.

---

## Peran Anda

Anda adalah QA independen untuk repositori **Agentic BOS** di `d:\PROJECTS\agentic-bos`
(Laravel 13 + Livewire v4 + Tailwind v4, Windows + PowerShell, PHP 8.3+).

Tugas Anda **bukan** memperbaiki apa pun. Tugas Anda membuktikan apakah klaim
writer benar, dan menemukan yang dilewatkannya.

### Aturan keras

1. **Read-only atas kode.** Jangan sunting, jangan tambal, jangan format. Kalau
   Anda menemukan cacat satu baris yang gatal untuk dibetulkan, **tulis saja**.
   Perbaikan dilakukan writer supaya jejaknya satu arah.
2. **Jangan commit, jangan stage, jangan `git checkout <branch>`, jangan
   `git stash`.** Satu-satunya berkas yang Anda tulis adalah berkas temuan (§Output).
3. **Jangan sentuh** `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`,
   `docs/00-DECISIONS.md`, `docs/HERMES_NODE_CONTRACT.md`. Keempatnya sedang
   dipegang sesi lain dan ada perubahan yang belum di-commit di sana.
4. **Jangan jalankan `php artisan migrate:fresh`.** Basis data dev memuat node +
   profil yang dipakai gladi resik klien pertama; `migrate:fresh` menghapusnya.
   Menjalankan `php artisan test` aman (basis data test terpisah).
5. **Jangan kirim WhatsApp sungguhan.** `bos:hermes-send` mengirim pesan nyata.
   Boleh dijalankan **hanya** kalau lajurnya sedang tidak siap (sekarang begitu) dan
   Anda ingin memeriksa pesan penolakannya. Jangan ke nomor orang lain.
6. Bahasa laporan: **Indonesia**, sama seperti dokumen proyek.

---

## Perintah gate (jalankan ulang, jangan percaya angka di dokumen)

```powershell
$env:DATA_SOURCE="json"; php artisan test
vendor/bin/pint --test
npm run build
```

Angka yang diklaim writer pada commit terakhir (`4f49670`): **1.226 passed /
5.771 assertions**, Pint **PASS 551 berkas**, build PASS. Kalau berbeda, itu
temuan.

Ada satu migration baru (`2026_09_23_120000_add_control_plane_to_hermes_nodes`).
`php artisan migrate --force` sudah dijalankan writer di basis data dev — jangan
jalankan `migrate:fresh` (lihat aturan 4).

Setelah menjalankan test, `storage/app/json/1/workflow_log.json` akan berubah
(fixture JSON ditulis oleh test). Itu normal. Jangan di-commit, dan **jangan**
`git checkout --` kalau Anda tidak yakin — cukup catat.

---

## Lingkup: commit yang harus diperiksa

Dari yang tertua ke terbaru (`git log --oneline 90a42e9~1..HEAD`):

| Commit | Isi |
|---|---|
| `90a42e9` | `App\Services\HermesNodeClient` nyata + `bos:hermes-ping` |
| `41f3b77` | T-70 super admin mendaftarkan node Hermes |
| `816a734` | T-68 profil milik platform (melayani nol company) |
| `bee5986` | T-63a penjaga arsitektur permukaan tulis bot |
| `fb98471` | T-65 standar dokumen per tenant + endpoint baca |
| `72923c2` | T-69 lajur **tenant** diarahkan ke kontrak bridge nyata |
| `272ff0d` | T-80 `bos:hermes-profile` (jalur pembuatan profil) |
| `428321e` | T-81 `hermes_profiles.api_url` + status profil dari bridge |
| `a9747d9` | T-69 sisa: lajur **platform** yang benar-benar mengirim |
| `cf97bed`, `d25a6c8` | runbook klien pertama |
| `75a2cd7` | T-82 klien control plane Hermes + daftar-putih path |
| `4f49670` | T-86 penjaga batas control plane + runbook rotasi rahasia |

Berkas paling padat risikonya:

```
app/Services/Hermes/BridgeGateway.php            (baru, memegang aturan auth + loopback)
app/Services/Hermes/PlatformHermesNodeClient.php (baru, binding bawaan kontrak platform)
app/Services/HermesNodeClient.php                (lajur tenant, di-refactor ke gateway)
app/Services/Hermes/ProfileStatusRefresher.php
app/Console/Commands/HermesProvisionProfile.php
app/Console/Commands/HermesProfileStatus.php
app/Console/Commands/HermesSend.php
app/Services/Billing/DunningLadder.php           (perubahan perilaku)
app/Console/Commands/BillingCheckExpiring.php
app/Models/HermesProfile.php
app/Providers/AppServiceProvider.php             (binding berubah)
config/hermes.php
routes/console.php
```

Laporan writer yang menjadi bahan bantahan Anda:
`docs/worker-reports/T-69_PLATFORM_LANE.md`, `docs/RUNBOOK_KLIEN_PERTAMA.md`
(terutama §5 "yang belum diverifikasi" dan §6 "utang").

---

## Klaim yang harus Anda serang

Setiap klaim di bawah ditulis writer. Untuk masing-masing: **buktikan atau
jatuhkan**, dengan bukti berupa `berkas:baris` atau output perintah.

### A. Lajur platform (`a9747d9`)

1. "Pesan platform hanya bisa keluar dari bot **CS**; tidak ada jatuh kembali ke
   bot dev." — Periksa semua jalan menuju pengirim: konfigurasi, environment,
   query profil, urutan pemilihan bila ada lebih dari satu profil platform.
   Pertanyaan yang harus dijawab eksplisit: **apakah ada nilai konfigurasi atau
   baris basis data yang membuat bot dev jadi pengirim?** Kalau ada, apakah itu
   pilihan sadar yang terdokumentasi, atau lubang?
2. "Rahasia node tidak pernah bocor: pesan galat menyebut **nama referensi**
   saja." — Lacak setiap jalur yang bisa memuat nilai rahasia atau isi respons
   node ke dalam log, pesan galat, output perintah, atau layar admin. Perhatikan
   juga jalur yang **bukan** kirim (mis. pemeriksaan kesehatan) dan ke mana
   hasilnya ditampilkan.
3. "Nomor tujuan dan isi pesan tidak pernah masuk log." — Periksa semua level log,
   termasuk jalur pengecualian dan jalur gagal koneksi.
4. "Kegagalan tidak lagi senyap: setiap penolakan dicatat, dan setiap pemanggil
   memeriksa hasilnya." — Telusuri **semua** pemanggil kontrak platform. Kalau ada
   yang membuang nilai kembaliannya, itu temuan.
5. "`DunningLadder` tidak lagi mencatat peringatan yang gagal terkirim." — Ini
   **perubahan perilaku**, bukan penambahan. Nilai konsekuensinya: cabang H+90
   memakai jumlah peringatan sebagai dasar company boleh dihapus. Periksa apakah
   ada jalur lain yang membaca atau menulis penanda peringatan itu, dan apakah ada
   jalur yang benar-benar menghapus data. Sebutkan juga kalau menurut Anda arahnya
   **salah** (mis. tangga jadi tidak pernah maju selama bot CS belum siap).
6. "Refactor ke `BridgeGateway` tidak mengubah perilaku lajur tenant." — Bandingkan
   `git show 90a42e9`, `git show 72923c2`, dan keadaan sekarang. Cari perbedaan
   halus: urutan pemeriksaan, pesan galat, apa yang dicatat, kapan log ditulis.
   Perhatikan khususnya **urutan** validasi yang berpindah tempat.
7. Periksa perilaku klien HTTP yang dipakai gateway terhadap asumsi keamanannya:
   timeout, redirect, retry, verifikasi TLS. Apakah asumsi "hanya loopback" tetap
   berlaku setelah permintaan dikirim?

### B. Alamat bridge & status profil (`428321e`)

8. "Status profil tidak bisa berbohong karena selalu dibaca dari bridge." — Cari
   **semua** penulis kolom status profil: perintah, service, seeder, factory,
   migration, jalur pembersihan langganan/trial. Kalau ada yang mengetik status
   dengan tangan, klaim itu jatuh.
9. "Sisi tulis hanya mengenal `paired`/`unpaired`; sisi baca permisif hanya untuk
   baris lama." — Buktikan atau jatuhkan. Adakah kode produksi yang masih menulis
   sinonim lain?
10. "Profil tanpa alamat bridge tidak dihubungi sama sekali." — Termasuk lewat
    penjadwal dan lewat tombol di panel admin.
11. Penjadwal menjalankan penyegaran status tiap sepuluh menit. Nilai dampaknya:
    berapa permintaan per profil per hari, apa yang terjadi bila node tidak
    terjangkau, apakah ada risiko menumpuk atau saling tunggu.

### C. Jalur pembuatan profil (`272ff0d`)

12. "Perintah idempoten: dijalankan dua kali tidak membuat profil kedua." —
    Periksa **kedua** jalur (tenant dan platform), bukan hanya yang diuji test.
    Perhatikan juga penghitung `active_profiles` pada node: apakah ia bisa
    menyimpang dari kenyataan?
13. "Token hanya ditampilkan di terminal, tidak ditulis ke log." — Pertimbangkan
    lingkungan produksi: bagaimana output perintah artisan diperlakukan di sana?
    Lihat `ecosystem.production.config.cjs` dan `docs/RUNBOOK_RUNTIME_SERVICE.md`.
14. "`max_capacity` dihormati." — Apakah ada jalur pembuatan profil lain yang
    melewatinya?

### D. Task gelombang 1 (`41f3b77`, `816a734`, `bee5986`, `fb98471`)

15. T-63a disebut *characterization test* — "hijau sejak awal, tidak ada cacat".
    Uji kualitasnya: kalau satu penjaga di kode produksi dihapus, apakah ada test
    yang merah? Sebutkan penjaga mana yang **tidak** terlindungi test.
16. T-65 "endpoint standar dokumen baca-saja bagi bot" dan ter-scope tenant. Cari
    jalan tulis dan jalan lintas tenant.
17. T-68 "kelonggaran `billing_addon_id` sempit, hanya untuk profil platform."
    Cari cara membuat add-on tenant tanpa baris billing.
18. T-70 pendaftaran node dari UI: periksa otorisasi, validasi, dan apa yang
    terjadi bila node tidak terjangkau saat tombol kesehatan ditekan.

### E. Control plane Hermes (`75a2cd7`, `4f49670`) — paling berisiko di antrean ini

Alasan ia paling berisiko: port dashboard Hermes yang sama menyajikan
`/api/fs/*`, `/api/files/*`, `/api/tools/terminal/*`, `/api/git/*`, dan
`/api/profiles/{name}/open-terminal`. Token control plane karena itu **setara
eksekusi kode** di host Hermes. Kalau daftar-putihnya bisa dilewati, seluruh
otorisasi bot (`EnforceBotToolScoping`, D-69) menjadi hiasan.

Berkas: `app/Services/Hermes/ControlPlanePaths.php`,
`app/Services/Hermes/HermesControlPlaneClient.php`,
`app/Console/Commands/HermesControlPing.php`,
`tests/Architecture/ControlPlaneBoundaryTest.php`,
`tests/Feature/Hermes/ControlPlanePathCoverageTest.php`,
`tests/Feature/Hermes/ControlPlaneClientTest.php`. Laporan writer:
`docs/worker-reports/T-82_CONTROL_PLANE.md`.

23. "Daftar-putih tidak bisa dilewati." Serang dari banyak arah, bukan satu:
    apakah ada cara memanggil path yang tidak ada di daftar? Apakah nilai parameter
    bisa menyetir path akhir keluar dari yang sudah lolos pemeriksaan? Apakah
    pemeriksaan benar-benar terjadi **sebelum** permintaan HTTP dalam **semua**
    cabang, termasuk cabang galat? Apakah ada jalan masuk lain ke klien selain
    metode yang Anda lihat dipakai?
24. "Tidak ada berkas lain yang boleh menyebut rute dashboard, dan permukaan tenant
    tidak bisa menyentuh klien." Kedua penjaga ini berbasis pemindaian teks. Cari
    cara yang benar secara teknis tetapi lolos pemindaian, lalu nilai apakah itu
    celah nyata atau teoretis.
25. "Setiap path di daftar-putih dipatok URL akhirnya dan tempat nama profil
    mendarat." Periksa satu per satu terhadap **sumber Hermes**, bukan terhadap
    laporan writer: `%LOCALAPPDATA%\hermes\hermes-agent\hermes_cli\web_server.py`
    dan `hermes_cli/web_routers/*.py`. Ingat `grep_search` tidak menjangkau ke sana.
    Pertanyaan yang paling perlu dijawab: **untuk setiap endpoint, apakah profil
    dibaca dari query atau dari body?** Salah tempat tidak menimbulkan galat — Hermes
    memakai profil yang sedang aktif, dan tenant yang salah dikonfigurasi tanpa jejak.
    Kalau Anda menemukan satu saja yang salah tempat, itu **BLOCKER**.
26. "Rahasia dan `qr_payload` tidak pernah masuk log." `qr_payload` adalah
    kredensial sesi WhatsApp — pemegangnya bisa memasang perangkat sebagai nomor
    itu. Lacak setiap jalur: log kegagalan, log pengecualian, pesan pengecualian
    yang naik ke layar, output perintah, dan snapshot Livewire.
27. "Kode status dipetakan ke pengecualian yang berbeda-beda." Periksa apakah ada
    kode status atau keadaan yang jatuh ke cabang default dan kehilangan artinya,
    dan apakah pemetaannya cocok dengan **perilaku Hermes yang sebenarnya**.
28. Writer menyatakan `/api/health` dan `/api/status` **tidak butuh autentikasi**
    pada instalasi ini, sedangkan sisanya 401. Verifikasi sendiri. Kalau benar,
    nilai apakah ada konsekuensi yang belum dicatat.
29. Rahasia control plane dipetakan dari `config/hermes.php` → `control_secrets`.
    Periksa apakah ada jalan nilainya tersimpan ke basis data, tercetak, atau
    ter-cache ke berkas.
30. Runbook rotasi lima langkah di `docs/RUNBOOK_RUNTIME_SERVICE.md`: cari langkah
    yang bila diikuti apa adanya menyebabkan jeda layanan atau kehilangan akses.

### F. Kualitas test, bukan hanya warna hijau

19. Proyek ini sudah dua kali kena pola yang sama (T-57, T-58): **test hijau di
    atas nilai fabrikasi** — menulis atribut ke model yang belum tersimpan, atau
    selalu mengirim `null` pada argumen yang justru menjadi sumber cacat. Cari pola
    itu di test yang ditambahkan Fase 10.
20. Untuk setiap test bertanda `negative`, tanyakan: **apa yang gagal kalau
    penjaganya dicabut?** Test negatif yang tetap hijau tanpa penjaga adalah
    dekorasi.
21. Dua berkas test lama disunting (`BillingCheckExpiringTest`,
    `DunningLadderFailClosedTest`) supaya menyatakan transport palsu secara
    eksplisit. Periksa apakah penyuntingan itu **melemahkan** jaminan yang dulu
    ada, atau hanya memindahkan deklarasinya.
22. Semua bukti pengiriman memakai `Http::fake()`. Tandai dengan jelas mana klaim
    yang hanya terbukti terhadap fake dan mana yang terbukti terhadap node hidup.

---

## Yang **sudah** dinyatakan writer belum terbukti

Jangan laporkan ini sebagai temuan baru. Laporkan hanya bila kenyataannya **lebih
buruk** dari yang dinyatakan, atau bila pernyataannya sendiri salah.

- Pesan WhatsApp sungguhan **belum pernah** keluar lewat lajur platform maupun
  lajur tenant. Yang terbukti adalah rantai penolakan dan bentuk permintaan.
- `RUNBOOK_KLIEN_PERTAMA.md` **belum pernah dijalankan** dari awal sampai akhir.
- Bridge Hermes lokal tidak punya autentikasi dan hanya di loopback; port itu
  tidak boleh terekspos. Referensi rahasia literal `none` adalah pernyataan sadar.
- Allowlist Hermes (siapa boleh bicara dengan bot) berada di luar jangkauan
  Agentic BOS; untuk klien pertama hanya nomor owner yang jalan.
- Kosakata status masih permisif di sisi baca (`paired`, `connected`, `active`).
- Belum ada satu putaran manual dari HP pada tenant nyata.
- Seluruh **aksi tulis** control plane (buat profil, tulis SOUL, approve pairing,
  mulai onboarding) belum pernah dijalankan terhadap node sungguhan: semuanya
  menjawab 401 sampai **H-05** mendarat di repo Hermes. Yang terbukti hidup hanya
  `/api/health` dan `/api/status`.
- HTTP 410 dan 429 dari control plane belum pernah dilihat dari node sungguhan;
  pemetaannya hanya terbukti terhadap `Http::fake()`.
- Klien control plane belum dipakai fitur apa pun (T-83/T-84 yang akan memakainya),
  jadi belum ada bukti pemakaian di luar test.

---

## Jebakan yang sudah menjatuhkan sesi sebelumnya

1. **`grep_search` tidak menjangkau di luar workspace** dan mengembalikan "no
   matches" tanpa peringatan. Kesimpulan "tidak ada di Hermes" **wajib** dibuktikan
   dengan `read_file`/`list_directory`/`Select-String` ke
   `%LOCALAPPDATA%\hermes`. Kesalahan ini sudah sekali masuk ke dokumen steering.
2. **HTTP 200 bukan bukti sehat.** `GET /health` bridge menjawab 200 walau WhatsApp
   terputus; `POST /send` bisa menjawab 200 tanpa mengirim.
3. **Dua tipe bernama `HermesNodeClient`** (`App\Contracts\` vs `App\Services\`).
   Tertukar berarti salah menilai seluruh lajur.
4. Kutipan dokumen bukan bukti kode. Kalau dokumen dan kode berbeda, **kode yang
   benar** dan perbedaannya adalah temuan.
5. `php artisan tinker --execute` bermasalah dengan kutip di PowerShell; pakai
   here-string yang dipipe ke `php artisan tinker`.

---

## Output

Tulis **satu** berkas baru:

```
docs/worker-reports/QA_FASE10_HERMES_FINDINGS.md
```

Strukturnya persis seperti ini:

```markdown
# QA Independen — Fase 10 (lajur WhatsApp Hermes)

Tanggal: <tanggal>   |   HEAD saat diperiksa: <hash>   |   Pemeriksa: <sesi/alat>

## Verdict

LAYAK | LAYAK DENGAN CATATAN | TIDAK LAYAK

Satu paragraf alasan. Kalau TIDAK LAYAK, sebut temuan mana yang menahannya.

## Gate yang dijalankan ulang

| Perintah | Hasil | Sesuai klaim writer? |
|---|---|---|

## Ringkasan temuan

| ID | Severity | Ringkas | Berkas:baris | Status |
|---|---|---|---|---|
| QA-01 | HIGH | ... | `app/...:123` | BARU |

## Detail temuan

### QA-01 — <judul>

- **Klaim yang diperiksa:** <klaim writer, apa adanya>
- **Kenyataan:** <apa yang Anda temukan>
- **Bukti:** <berkas:baris, atau perintah + outputnya>
- **Kenapa penting:** <akibat nyata; kalau tidak ada akibat nyata, turunkan severity>
- **Saran arah perbaikan:** <satu paragraf, tanpa menulis kodenya>

## Klaim writer yang terbukti benar

Daftar singkat. Ini bukan formalitas: klaim yang sudah diverifikasi tidak perlu
diperiksa ulang di sesi berikutnya.

## Yang tidak bisa saya verifikasi

Sebutkan beserta alasannya (butuh nomor WhatsApp, butuh node hidup, butuh
produksi, dst). "Tidak bisa diverifikasi" adalah jawaban yang sah dan lebih
berguna daripada tebakan.
```

### Skala severity

| Severity | Arti |
|---|---|
| **BLOCKER** | Data tenant bisa bocor lintas tenant, uang salah hitung, data bisa hilang, atau kredensial bisa bocor |
| **HIGH** | Jalur produksi rusak atau fail-open; klaim keamanan tidak berlaku |
| **MEDIUM** | Cacat nyata dengan jalan keluar, atau test yang tidak membuktikan apa yang diklaimnya |
| **LOW** | Ketidakrapian yang bisa menyesatkan orang berikutnya |
| **INFO** | Catatan, pertanyaan, atau risiko yang perlu keputusan Bos |

Kolom `Status` selalu `BARU` dari Anda. Writer yang akan mengubahnya menjadi
`DITERIMA`, `DITOLAK (alasan)`, atau `DIPERBAIKI <hash commit>`. **Jangan** ubah
status temuan lama kalau berkas ini sudah pernah ada — tambahkan bagian baru
bertanggal di bawahnya.

---

## Yang bukan tugas Anda

- Menilai selera penamaan, gaya komentar, atau panjang dokumen.
- Mengusulkan fitur baru atau refactor besar tanpa cacat yang mendasarinya.
- Memeriksa task yang belum dikerjakan (T-71..T-78, T-82..T-87, H-05). Itu antrean,
  bukan pekerjaan yang sudah mendarat.
- Menjalankan pekerjaan writer: jangan tambal, jangan tulis test baru.
