# QA Independen — Fase 10 (lajur WhatsApp Hermes)

Tanggal: 2026-09-22   |   HEAD saat diperiksa: `3ac7e5f` (kode Fase 10 terakhir: `4f49670`; `321c06d` dan `3ac7e5f` hanya dokumen)   |   Pemeriksa: sesi QA independen (Kiro)

## Verdict

**LAYAK DENGAN CATATAN.**

Ketiga gate lolos dan angkanya **persis** sesuai klaim writer (1.226 passed /
5.771 assertions, Pint 551 berkas, build PASS). Inti lajur — fail-closed di
tenant maupun platform, tidak ada jatuh kembali ke bot dev, redaksi rahasia,
dan **daftar-putih control plane** — terbukti benar; verifikasi tempat parameter
`profile` (query vs body) untuk **setiap** endpoint saya lakukan sendiri terhadap
sumber Hermes asli di `%LOCALAPPDATA%\hermes` dan semuanya cocok. Tidak ada
BLOCKER: tidak ada endpoint yang salah tempat, tidak ada kebocoran rahasia yang
saya temukan, tidak ada jalur penghapusan data yang benar-benar hidup.

Yang menahannya dari LAYAK penuh adalah satu klaim yang **jatuh** (QA-01: jalur
pembuatan profil **platform** tidak idempoten — bertentangan langsung dengan
klaim writer 12, dan tidak diuji), satu penyimpangan penghitung yang
berpasangan dengannya (QA-02: `active_profiles` tidak pernah diturunkan), dan
satu asumsi keamanan HTTP yang tidak ditegakkan (QA-03: klien mengikuti redirect,
sehingga "hanya loopback" hanya menjaga koneksi awal). Sisanya MEDIUM/LOW/INFO.

## Gate yang dijalankan ulang

| Perintah | Hasil | Sesuai klaim writer? |
|---|---|---|
| `$env:DATA_SOURCE="json"; php artisan test` | 1.226 passed / 5.771 assertions, 0 gagal (126,97 s) | Ya, persis |
| `vendor/bin/pint --test` | PASS, 551 berkas | Ya, persis |
| `npm run build` | PASS (built in 2.02s) | Ya |

Catatan: `storage/app/json/1/workflow_log.json` berubah setelah test (fixture
JSON), sesuai peringatan prompt. Tidak di-commit, tidak di-`checkout`.

## Ringkasan temuan

| ID | Severity | Ringkas | Berkas:baris | Status |
|---|---|---|---|---|
| QA-01 | MEDIUM | Jalur profil **platform** tidak idempoten (`create()` tanpa `firstOrCreate`), tidak diuji — bantah klaim 12 | `app/Console/Commands/HermesProvisionProfile.php:156-173` | BARU |
| QA-02 | MEDIUM | `active_profiles` hanya pernah di-`increment`, tak pernah diturunkan; `max_capacity` menyimpang naik | `app/Console/Commands/HermesProvisionProfile.php:122,172` ; `app/Console/Commands/CleanupExpiredTrials.php:42-46` | BARU |
| QA-03 | MEDIUM | Klien bridge & control plane mengikuti redirect Guzzle (default); "hanya loopback" hanya menjaga koneksi awal, badan permintaan bisa kabur ke host lain | `app/Services/Hermes/BridgeGateway.php:58-66` ; `app/Services/Hermes/HermesControlPlaneClient.php:112-124` | BARU |
| QA-04 | MEDIUM | Penjaga (b) `isTenantFacing()` tidak meliputi komponen Livewire akar (`Dashboard`, dst) maupun `app/Services`; klien bisa dipanggil lewat metode bernama tanpa menyebut rute → lolos kedua penjaga | `tests/Architecture/ControlPlaneBoundaryTest.php:120-134` | BARU |
| QA-05 | LOW | Refactor ke `BridgeGateway` **mengubah urutan validasi** lajur tenant: profil/node diperiksa sebelum nomor/pesan — bantah "perilaku tidak berubah" (klaim 6) | `app/Services/HermesNodeClient.php:60-90` vs `72923c2` | BARU |
| QA-06 | LOW | Klaim 8 ("status selalu dibaca dari bridge") harfiahnya salah: provisioning & cleanup menulis `status` dengan tangan (hanya `unpaired` — arah aman) | `app/Console/Commands/HermesProvisionProfile.php:116,166` ; `CleanupExpiredTrials.php:42` | BARU |
| QA-07 | LOW | Factory menulis `status='connected'`; kosakata baca permisif dipakai default test, bukan hanya "baris lama" (menyentuh klaim 9) | `database/factories/HermesProfileFactory.php:34` | BARU |
| QA-08 | INFO | `hermes_profiles.webhook_secret_reference` menyimpan **nilai token** plaintext (dicocokkan verbatim), bukan referensi nama seperti node secret; dicetak `bos:hermes-profile` ke stdout | `app/Http/Middleware/AuthenticateTenantBot.php:26` ; `HermesProvisionProfile.php:83-88,196` | BARU |
| QA-09 | INFO | Cabang DELETE `call()` memakai `$payload ?: $query`; DELETE ber-`PROFILE_QUERY` di masa depan akan menjatuhkan profil dari query. Tak ada endpoint begitu hari ini | `app/Services/Hermes/HermesControlPlaneClient.php:120` | BARU |
| QA-10 | INFO | Penjadwal `bos:hermes-profile-status` tiap 10 menit tanpa `withoutOverlapping()`; ping berurutan @10s bisa menumpuk pada armada besar / node lambat | `routes/console.php:29` | BARU |
| QA-11 | INFO | `DunningLadder` merekam peringatan hanya pada `daysOverdue` **persis** 30/60/83; satu hari terlewat (deploy/downtime) = peringatan itu tak pernah tercatat. Gabung dengan delivery-gating, ambang hapus makin sulit tercapai. Cabang H+90 hanya `Log::info`, belum ada penghapusan nyata | `app/Services/Billing/DunningLadder.php:36-60` | BARU |

## Detail temuan

### QA-01 — Jalur pembuatan profil platform tidak idempoten (bantah klaim 12)

- **Klaim yang diperiksa:** "Perintah idempoten: dijalankan dua kali tidak
  membuat profil kedua." Prompt secara khusus meminta memeriksa **kedua** jalur.
- **Kenyataan:** Jalur **tenant** (`provisionTenant`) memakai `firstOrCreate`
  pada `['owner_user_id','type'=>'primary']` dan hanya menambah penghitung bila
  `wasRecentlyCreated` — idempoten, dan itulah yang diuji
  (`ProvisionProfileCommandTest::test_running_it_twice_does_not_create_a_second_profile`,
  hanya `--company`). Jalur **platform** (`provisionPlatform`) memakai
  `HermesProfile::create([...])` tanpa syarat dan `$node->increment('active_profiles')`
  tanpa syarat. Menjalankan `bos:hermes-profile --platform --owner=X --node=Y --type=addon`
  dua kali membuat **dua** profil platform.
- **Bukti:** `app/Console/Commands/HermesProvisionProfile.php:156-173` (`create` +
  `increment` tanpa guard). Satu-satunya unik di tabel adalah `instance_id`
  (acak per jalankan — `create_hermes_profiles_table.php:21`), jadi tidak ada
  kendala DB yang menahannya. Tidak ada `HermesProvisionProfileTest` yang
  menjalankan jalur platform dua kali (`ProvisionProfileCommandTest.php:76-97`
  hanya sekali).
- **Kenapa penting:** `PlatformHermesNodeClient::senderProfile()` memakai
  `orderBy('id')->first()`, jadi profil kedua tidak langsung mengubah pengiriman —
  tetapi ia menggelembungkan `active_profiles` (lihat QA-02) dan meninggalkan
  baris platform menggantung yang membingungkan operasi. Klaim writer sendiri
  ("dijalankan dua kali tidak membuat profil kedua") tidak berlaku untuk jalur
  yang justru paling jarang dijalankan dan paling sulit dikoreksi (menyangkut
  nomor yang mewakili perusahaan).
- **Saran arah perbaikan:** Samakan jalur platform dengan pola tenant —
  `firstOrCreate` pada kunci yang benar-benar mengidentifikasi profil platform
  (mis. `owner_user_id`+`type`+`is_platform_provided`), naikkan penghitung hanya
  saat `wasRecentlyCreated`, dan tambahkan satu test yang menjalankan jalur
  platform dua kali.

### QA-02 — `active_profiles` tidak pernah diturunkan; penjaga kapasitas menyimpang

- **Klaim yang diperiksa:** Klaim 12 juga bertanya "apakah penghitung
  `active_profiles` bisa menyimpang dari kenyataan?" dan klaim 14 "`max_capacity`
  dihormati."
- **Kenyataan:** `active_profiles` hanya pernah dinaikkan (dua tempat di
  provisioning) dan diinisialisasi 0 saat node dibuat. Tidak ada satu pun jalur
  yang menurunkannya. `CleanupExpiredTrials` mencabut profil owner yang tak punya
  company aktif dengan `node_id => null` + `webhook_secret_reference => revoked_...`
  **tanpa** `decrement('active_profiles')`. Jadi setiap trial yang kedaluwarsa,
  setiap re-run jalur platform (QA-01), dan setiap penghapusan profil di masa
  depan menaikkan penghitung tanpa jalan turun.
- **Bukti:** `grep active_profiles|decrement` di `app/` → hanya `increment`
  (`HermesProvisionProfile.php:122,172`) dan inisialisasi `+ ['active_profiles' => 0]`
  (`HermesNodeManager.php:223`). `CleanupExpiredTrials.php:42-46` menulis
  `node_id => null` tanpa menyentuh penghitung node lamanya.
- **Kenapa penting:** `max_capacity` ditegakkan **hanya** lewat
  `$node->active_profiles >= $node->max_capacity` (`HermesProvisionProfile.php:63`).
  Karena penghitung hanya naik, node lambat laun melaporkan "penuh" secara palsu
  dan menolak pembuatan profil yang sah — kegagalan yang muncul jauh dari
  penyebabnya. Ini menjadikan `max_capacity` "dekorasi" persis seperti yang
  ingin dihindari writer, hanya secara perlahan.
- **Saran arah perbaikan:** Turunkan penghitung saat profil dicabut/dipindah/dihapus
  (mis. di `CleanupExpiredTrials` dan jalur hapus profil), atau hitung
  `active_profiles` sebagai turunan (`count()` profil aktif pada node) alih-alih
  kolom yang harus dijaga konsisten dengan tangan.

### QA-03 — "Hanya loopback" tidak menjaga permintaan setelah redirect

- **Klaim yang diperiksa:** "asumsi 'hanya loopback' tetap berlaku setelah
  permintaan dikirim" (klaim 7) dan aturan yang sama untuk control plane.
- **Kenyataan:** Baik `BridgeGateway` maupun `HermesControlPlaneClient` memakai
  facade `Http` tanpa `withoutRedirecting()`. Klien HTTP Laravel (Guzzle)
  **mengikuti hingga 5 redirect** secara bawaan. Pemeriksaan `isLoopback()` hanya
  memvalidasi host **URL terkonfigurasi**, bukan tujuan setelah redirect. Sebuah
  bridge/control plane loopback yang disusupi atau salah konfigurasi bisa
  menjawab `302` ke host luar; Guzzle akan mengikutinya dan mengirim badan
  permintaan ke sana.
- **Bukti:** Tidak ada `withoutRedirecting|allow_redirects|withOptions` di
  `app/Services/**`. `BridgeGateway.php:58-66` (POST `/send`),
  `HermesControlPlaneClient.php:112-124` (`->send(...)`/`->get(...)`).
- **Kenapa penting:** Untuk bridge, badan yang bisa kabur memuat **nomor tujuan +
  isi pesan** (JID `chatId` + `message`). Untuk control plane, memuat payload
  seperti isi SOUL. Header `Authorization: Bearer` **kemungkinan** dilucuti Guzzle
  saat redirect lintas-host (perilaku `RedirectMiddleware`), jadi token relatif
  aman — tetapi badan permintaan tidak. Ini melemahkan klaim keamanan loopback
  untuk data, bukan untuk token. Severity MEDIUM karena butuh node yang sudah
  disusupi/salah konfigurasi untuk memicu redirect; bukan jalur serang dari luar.
- **Saran arah perbaikan:** Tambahkan `->withoutRedirecting()` pada kedua klien
  (bridge dan control plane); loopback lokal tidak butuh redirect. Pertimbangkan
  juga `connectTimeout` eksplisit selain `timeout` total.

### QA-04 — Penjaga tenant-facing tidak meliputi permukaan tenant yang sesungguhnya

- **Klaim yang diperiksa:** "permukaan tenant tidak bisa menyentuh klien" (klaim
  24) — penjaga (b) `test_tenant_facing_code_cannot_reach_the_control_plane`.
- **Kenyataan:** `isTenantFacing()` hanya mengenali `app/Livewire/Screens/`,
  `app/Livewire/Settings*`, `app/Livewire/Public/`, `app/Livewire/Widgets/`, dan
  controller non-admin. Ia **tidak** meliputi komponen Livewire di akar
  `app/Livewire/` yang jelas dipakai tenant (`Dashboard.php`, `Sidebar.php`,
  `CommandPalette.php`, `Lobby.php`, `Onboarding.php`, `Paywall.php`), juga tidak
  `app/Services/`, `app/Jobs/`, `app/Models/`. Penjaga (a) hanya menangkap
  penyebutan **rute literal** (`/api/profiles`, dst). Karena klien punya metode
  bernama (`health()`, `status()`, `systemStats()`), sebuah komponen akar seperti
  `Dashboard.php` bisa memanggil
  `app(HermesControlPlaneClient::class)->status($node,$profile)` **tanpa** menulis
  rute apa pun — lolos penjaga (a) (tak ada literal) dan penjaga (b) (tak ada di
  daftar tenant-facing).
- **Bukti:** `tests/Architecture/ControlPlaneBoundaryTest.php:120-134`
  (`isTenantFacing`); daftar komponen akar dari struktur `app/Livewire/`.
- **Kenapa penting:** Penjaga ini adalah satu-satunya penegak "control plane
  super-admin-only lewat struktur". Definisi tenant-facing yang lebih sempit dari
  kenyataan berarti seluruh kelas permukaan yang bisa dicapai tenant tidak
  terlindungi. Bukan bukti ada pelanggaran hari ini — hanya penjaganya bocor.
- **Saran arah perbaikan:** Balik logika penjaga: alih-alih daftar-putih
  permukaan yang "tenant-facing", pakai daftar **kecil** berkas yang **boleh**
  menyentuh klien (mis. `app/Console`, `app/Livewire/Admin`) dan larang selain
  itu; atau perluas `isTenantFacing()` mencakup akar `app/Livewire/` dan
  `app/Services/`.

### QA-05 — Refactor `BridgeGateway` mengubah urutan validasi lajur tenant

- **Klaim yang diperiksa:** "Refactor ke `BridgeGateway` tidak mengubah perilaku
  lajur tenant" (klaim 6).
- **Kenyataan:** Di `72923c2` (sebelum refactor), `sendWhatsAppMessage`
  menormalkan nomor dan menolak nomor/pesan kosong **lebih dulu**, baru
  `profileFor()`. Di HEAD, `profileFor()` (yang bisa melempar "tidak ada
  profil"/"belum siap") dan pemeriksaan node dijalankan **lebih dulu**;
  normalisasi nomor + tolak kosong dipindah ke `BridgeGateway::sendText()` yang
  jalan **sesudah** resolusi profil/node.
- **Bukti:** `git show 72923c2:app/Services/HermesNodeClient.php` (baris
  `normalizeNumber` → cek kosong → `profileFor`) vs
  `app/Services/HermesNodeClient.php:60-90` sekarang (`profileFor` dulu, lalu
  `gateway->sendText`).
- **Kenapa penting:** Untuk pemanggil yang mengirim nomor kosong ke company yang
  profilnya belum siap, galat yang muncul berubah dari "Nomor tujuan tidak dapat
  dibaca" menjadi "Profil belum siap mengirim". Tidak ada dampak keamanan atau
  data; hanya urutan/isi pesan galat, dan tidak ada test yang mengunci kombinasi
  itu (sehingga perubahan ini tidak terverifikasi test). Severity LOW, tetapi
  klaim "perilaku tidak berubah" harfiahnya tidak akurat pada **urutan** —
  persis titik yang prompt minta diperhatikan.
- **Saran arah perbaikan:** Kalau urutan fail-fast nomor/pesan memang disengaja
  dipertahankan, kembalikan validasi input murah itu ke depan `profileFor()`;
  kalau tidak, cukup catat perubahan urutan di laporan dan tambahkan satu test
  yang menyatakan urutan galat yang diinginkan.

### QA-06 — "Status selalu dibaca dari bridge" harfiahnya salah (arah aman)

- **Klaim yang diperiksa:** "Status profil tidak bisa berbohong karena selalu
  dibaca dari bridge" (klaim 8).
- **Kenyataan:** Beberapa jalur produksi menulis `status` dengan tangan:
  `HermesProvisionProfile` menulis `'unpaired'` saat membuat profil, dan
  `CleanupExpiredTrials` menulis `'unpaired'` saat mencabut. Yang menulis status
  **siap** (`'paired'`) hanya `ProfileStatusRefresher`. Jadi klaim benar untuk
  arah berbahaya (tak ada yang mengetik `paired`), tetapi salah untuk pernyataan
  mutlak "selalu dibaca dari bridge".
- **Bukti:** `HermesProvisionProfile.php:116,166` (`'status' => 'unpaired'`);
  `CleanupExpiredTrials.php:42` (`'status' => 'unpaired'`);
  `ProfileStatusRefresher.php:56` (satu-satunya penulis `paired`).
- **Kenapa penting:** Rendah — semua tulisan tangan adalah arah "belum siap",
  yang aman (paling buruk menahan pengiriman, bukan mengizinkan yang salah).
  Layak dicatat supaya klaim tidak dipakai ulang sebagai kebenaran mutlak.
- **Saran arah perbaikan:** Perhalus klaim menjadi "tak ada jalur produksi yang
  mengetik status **siap** dengan tangan; hanya penyegar yang boleh."

### QA-07 — Factory menulis `status='connected'`

- **Klaim yang diperiksa:** "Sisi tulis hanya mengenal `paired`/`unpaired`; sisi
  baca permisif hanya untuk baris lama" (klaim 9).
- **Kenyataan:** `HermesProfileFactory` mem-default `status => 'connected'`. Ini
  kode test, bukan produksi — tetapi berarti kosakata baca permisif
  (`ready_statuses = ['paired','connected','active']`) aktif dipakai oleh default
  factory di seluruh suite, bukan sekadar "baris lama". Sisi tulis **produksi**
  memang hanya `paired`/`unpaired`, jadi inti klaim berlaku.
- **Bukti:** `database/factories/HermesProfileFactory.php:34`;
  `config/hermes.php` (`ready_statuses`).
- **Kenapa penting:** Rendah/INFO. Tidak ada dampak produksi; hanya membuat klaim
  "hanya baris lama" tidak akurat dan bisa menyembunyikan drift kosakata di
  kemudian hari.
- **Saran arah perbaikan:** Selaraskan default factory ke `'unpaired'` (atau
  `'paired'`) supaya kosakata test cocok dengan yang ditulis produksi, atau catat
  bahwa `connected` sengaja dipertahankan untuk menguji jalur baca permisif.

### QA-08 — `webhook_secret_reference` menyimpan nilai token plaintext, bukan referensi

- **Klaim yang diperiksa:** "Token hanya ditampilkan di terminal, tidak ditulis
  ke log" (klaim 13), dan pola berulang proyek "basis data bebas kredensial".
- **Kenyataan:** Meski namanya "reference", `AuthenticateTenantBot` mencocokkan
  kolom ini **verbatim** dengan bearer token (`where('webhook_secret_reference', $token)`).
  Jadi nilainya adalah rahasia sungguhan yang tersimpan **plaintext** di
  `hermes_profiles`, dan `bos:hermes-profile` mencetaknya ke stdout. Ini berbeda
  dari node secret yang mengikuti pola nama→env. Perintah itu **tidak** menulisnya
  ke log aplikasi (klaim 13 berlaku untuk alur interaktif), dan bukan perintah
  terjadwal, jadi tidak ditangkap `pm2-*-out.log` pada operasi normal
  (`ecosystem.production.config.cjs` hanya menjalankan `serve`/`queue:work`/`schedule:work`).
- **Bukti:** `AuthenticateTenantBot.php:26`; `HermesProvisionProfile.php:83-88`
  (cetak) & `:196` (`'sec_'.Str::random(40)`); `ecosystem.production.config.cjs`.
- **Kenapa penting:** INFO. Bukan regresi Fase 10 (kolom sudah ada sebelumnya),
  tetapi T-80 adalah yang pertama memunculkan token ini. Keamanannya bergantung
  pada operator tidak merekam output terminal (shell history, screen capture, CI
  yang menangkap stdout), dan pada DB tetap rahasia. Layak keputusan Bos apakah
  token bot tenant harus di-hash saat penyimpanan.
- **Saran arah perbaikan:** Pertimbangkan menyimpan hash token (bandingkan
  dengan `hash_equals`) alih-alih plaintext, konsisten dengan prinsip "DB bebas
  kredensial" yang dianut untuk node secret.

### QA-09 — Cabang DELETE memakai `$payload ?: $query` (laten)

- **Klaim yang diperiksa:** Pemetaan tempat parameter untuk semua metode (klaim
  25, cakupan cabang).
- **Kenyataan:** `call()` menjalankan `'DELETE' => ...->delete($url, $payload ?: $query)`.
  Kalau kelak ada endpoint `DELETE` ber-`PROFILE_QUERY`, profil ditaruh di
  `$query`; bila `$payload` juga terisi, `$payload ?: $query` memilih `$payload`
  dan **menjatuhkan** `$query` (termasuk profil). Dua DELETE yang ada sekarang
  (`/api/profiles/{name}` = PROFILE_IN_PATH, `/api/messaging/whatsapp/onboarding/{pairing_id}`
  = PROFILE_NONE) tidak terpengaruh.
- **Bukti:** `HermesControlPlaneClient.php:120`; `ControlPlanePaths::ALLOWED`.
- **Kenapa penting:** INFO/laten. Persis kelas kesalahan "salah tempat profil
  tanpa galat" yang jadi perhatian utama prompt — tetapi tidak dapat dipicu oleh
  daftar-putih saat ini.
- **Saran arah perbaikan:** Perlakukan query dan payload secara terpisah untuk
  DELETE (kirim keduanya bila perlu), atau tambahkan penjaga bahwa DELETE tidak
  boleh ber-`PROFILE_QUERY`.

### QA-10 — Penyegar status tanpa `withoutOverlapping`

- **Klaim yang diperiksa:** "Penjadwal menjalankan penyegaran status tiap sepuluh
  menit" (klaim 11) — nilai dampaknya.
- **Kenyataan:** `Schedule::command('bos:hermes-profile-status')->everyTenMinutes()`
  tanpa `withoutOverlapping()`. Perintah memeriksa **semua** profil secara
  berurutan; tiap ping ke node tak terjangkau menunggu `timeout` (10 dtk bawaan).
  Dengan N profil tak terjangkau, satu putaran bisa memakan ~N×10 dtk dan
  berpotensi tumpang tindih dengan putaran berikutnya bila N besar.
- **Bukti:** `routes/console.php:29`; `ProfileStatusRefresher::refresh()` (ping
  per profil); `BridgeGateway::ping()` (`timeout`).
- **Kenapa penting:** INFO untuk armada kecil (klien pertama). Menjadi nyata saat
  jumlah profil tumbuh atau banyak node lambat: putaran menumpuk dan menahan
  slot penjadwal.
- **Saran arah perbaikan:** Tambahkan `->withoutOverlapping()` dan/atau turunkan
  `timeout` untuk jalur penyegaran; pertimbangkan memeriksa hanya profil ber-alamat.

### QA-11 — Perekaman peringatan dunning bergantung pada hari persis

- **Klaim yang diperiksa:** "`DunningLadder` tidak lagi mencatat peringatan yang
  gagal terkirim" (klaim 5) — konsekuensi cabang H+90.
- **Kenyataan:** Peringatan direkam hanya saat `daysOverdue` **persis** 30, 60,
  atau 83. `billing:check-expiring` berjalan harian; satu hari terlewat (deploy,
  downtime, jeda penjadwal) berarti nilai persis itu tidak pernah tercapai dan
  peringatan tak pernah direkam — tak bisa disusulkan karena gerbangnya `=== N`.
  Digabung dengan perubahan perilaku "rekam hanya bila terkirim", ambang
  "3 peringatan" pada H+90 makin sulit tercapai. Cabang H+90 sendiri hanya
  `Log::info(...)` — **tidak ada penghapusan data nyata**.
- **Bukti:** `app/Services/Billing/DunningLadder.php:36-60` (gerbang `=== 30/60/83`
  dan H+90 `count(...) >= 3` → hanya `Log::info`). Tak ada jalur lain yang menulis
  atau membaca `dunning_notified_at` selain `recordNotification`/`restore` (grep).
- **Kenapa penting:** INFO. Arah writer (tidak maju ke penghapusan selama bot CS
  belum siap) memang disengaja dan dinyatakan. Yang **belum** dinyatakan writer:
  gerbang hari-persis membuat penghitungan peringatan rapuh terhadap jeda
  penjadwal, terlepas dari status pengiriman. Karena penghapusan belum
  diimplementasikan, risiko data hari ini nihil.
- **Saran arah perbaikan:** Kalau perekaman peringatan penting sebagai basis
  keputusan, ganti `=== N` dengan jendela (`>= N && belum direkam untuk tahap
  ini`) supaya jeda satu hari tidak menghilangkan peringatan; keputusan desain,
  bukan cacat mendesak.

## Klaim writer yang terbukti benar

- **A1 — tidak ada jatuh kembali ke bot dev.** `PlatformHermesNodeClient::senderProfile()`
  memfilter `is_platform_provided=true AND type=<sender_profile_type>` (bawaan
  `addon`); bot dev bertipe `primary` tak pernah terpilih. Diuji nyata
  (`PlatformDeliveryTest::test_negative_the_dev_bot_is_not_a_fallback_sender`).
  Catatan sadar: env `HERMES_PLATFORM_SENDER_TYPE=primary` bisa menjadikan bot dev
  pengirim — pilihan konfigurasi terdokumentasi, bukan lubang tersembunyi.
- **A2/A3/26 — redaksi rahasia & tidak ada nomor/isi pesan di log.** `BridgeGateway`
  sengaja tidak menulis log; pemanggil hanya mencatat sebab. `HermesControlPlaneClient`
  meredaksi `REDACTED_KEYS` (termasuk `qr_payload`, `soul`, `token`) dua lapis dan
  menyapu nilai rahasia terkonfigurasi dari teks bebas; galat menyebut **nama
  referensi** saja. Diuji (`ControlPlaneClientTest::test_negative_the_qr_payload_and_other_secrets_never_reach_the_log`,
  `..._by_reference_name_not_by_value`).
- **A4 — setiap pemanggil kontrak platform memeriksa hasil.** Tiga pemanggil
  (`BillingCheckExpiring`, `HermesSend`, `DunningLadder::notify`) semuanya
  memeriksa nilai `bool` yang dikembalikan; tak ada yang membuangnya.
- **A7 (TLS) — verifikasi TLS tidak dimatikan.** Tak ada `withoutVerifying()`
  di kedua klien (lihat QA-03 untuk sisi redirect).
- **B8/B9 (arah aman) — tak ada jalur produksi yang mengetik status *siap*.**
  Hanya `ProfileStatusRefresher` menulis `paired` (lihat QA-06 untuk kualifikasi).
- **B10 — profil tanpa alamat bridge tidak dihubungi.** `ProfileStatusRefresher::refresh()`
  mengembalikan lebih awal bila `address === ''`; panel admin menghitungnya
  terpisah, bukan menghubunginya.
- **C13 (alur interaktif) — token tak ditulis ke log aplikasi** dan perintahnya
  bukan perintah terjadwal (lihat QA-08 untuk kualifikasi plaintext-DB).
- **C14 — `max_capacity` dihormati saat pembuatan** (`>= max_capacity` menolak),
  diuji; lemahnya ada pada penghitung yang menyimpang (QA-02), bukan pada
  pengecekannya.
- **D15 — T-63a.** Penjaga struktur benar-benar menegakkan (hapus middleware dari
  rute bot → test merah). Penegakan **runtime** tool scoping juga diuji terpisah
  (`EnforceBotToolScopingTest` menegaskan 403 untuk `update_settings`/`destructive_action`
  pada `addon`), jadi bukan sekadar penjaga config.
- **D16 — T-65.** `DocumentStandardController` GET-saja, tak ada rute tulis
  terdaftar, dan menolak pemanggil yang bukan owner company (403) di atas
  scoping `AuthenticateTenantBot`.
- **D17 — T-68.** Kelonggaran `billing_addon_id` sempit: hook `saving` menolak
  `addon` tanpa billing kecuali `is_platform_provided=true`, dan jalur platform
  hanya bisa dibuat owner super admin.
- **D18 — T-70.** Rute `/admin/hermes-nodes` di grup `['auth', RequireSuperAdmin]`;
  form memvalidasi URL berskema, referensi rahasia wajib, dan menolak control
  plane non-loopback tanpa rahasia; `checkHealth()` melaporkan node tak
  terjangkau sebagai gagal alih-alih menjatuhkan halaman.
- **E23 — daftar-putih tidak bisa dilewati.** Pemeriksaan (`isForbidden` →
  `profileScopeFor` → wajib-profil) terjadi **sebelum** permintaan HTTP di semua
  cabang; template (bukan path terisi) yang dicocokkan; parameter di-`rawurlencode`
  per segmen; `Http::assertNothingSent()` membuktikan tak ada permintaan pada
  penolakan.
- **E25 — setiap path dipatok URL akhir + tempat profil, cocok dengan sumber
  Hermes.** Saya verifikasi sendiri di `web_server.py`/`web_routers/profiles.py`:
  `GET /api/pairing` (query), `approve`/`revoke`/`onboarding/start` (body,
  `_pairing_store(body.profile)` / `body.profile`), `clear-pending` (query),
  `apply` (query, dgn fallback body/record), `status`/`platforms` (query),
  `onboarding/{pairing_id}` GET & DELETE (PROFILE_NONE), profil `{name}` di path.
  **Tidak ada yang salah tempat.** `{pairing_id}` (bukan `{id}`) benar.
  `POST /api/profiles/active` (pengubah state bersama) & `/open-terminal` benar
  di luar daftar-putih / di daftar terlarang.
- **E27 — pemetaan kode status.** 401/403→Unauthorized, 404→NotFound,
  410→SessionExpired, 429→RateLimited, 5xx→Unavailable, sisanya default; diuji
  dgn `Http::fakeSequence()` (perbaikan atas cacat `Http::fake()` menumpuk yang
  writer catat sendiri).
- **E28 — `/api/health` & `/api/status` publik, sisanya butuh auth.** Terverifikasi
  di sumber: `PUBLIC_API_PATHS` memuat keduanya (plus `config/defaults`,
  `config/schema`, `model/info`, `dashboard/themes`, `dashboard/plugins`,
  `cron/fire`); `should_require_auth(host)` mengaktifkan gerbang hanya untuk host
  non-loopback. `/api/dashboard/plugins` yang publik juga ada di daftar terlarang
  kita — pertahanan berlapis yang benar.
- **E29 — rahasia control plane tidak masuk DB.** `config/hermes.php` memetakan
  `control_secrets` nama→env; `hermes_nodes` hanya menyimpan
  `control_secret_reference` (nama).
- **E30 — runbook rotasi lima langkah tanpa jeda.** Urutan tambah-dulu →
  pasang-berdampingan → pindah kolom → buktikan → cabut-lama; rollback satu kolom.
  Bergantung pada asumsi H-05 mendukung dua token berdampingan (writer sudah
  menyatakan H-05 belum mendarat).
- **F20 — test negatif menggigit.** Test negatif control plane & platform memakai
  `Http::assertNothingSent()` dan record model nyata, bukan sekadar "melempar".
- **F21 — penyuntingan test lama tidak melemahkan.** `DunningLadderFailClosedTest`
  & `BillingCheckExpiringTest` kini **menyatakan** `FakeHermesNodeClient` secara
  eksplisit; dulu mewarisi binding bawaan (yang sejak T-69 berubah jadi transport
  nyata). Ini memindahkan deklarasi, bukan melonggarkan jaminan.

## Yang tidak bisa saya verifikasi

- **Pengiriman WhatsApp sungguhan** (lajur platform maupun tenant). Yang terbukti
  hanya rantai penolakan dan **bentuk** permintaan terhadap `Http::fake()`. Butuh
  nomor CS yang sudah paired pada profil Hermes tersendiri + node hidup. Sesuai
  yang writer nyatakan; saya tidak menjalankan `bos:hermes-send` ke nomor nyata
  (aturan 5).
- **Aksi tulis control plane** (buat profil, tulis SOUL, approve pairing,
  onboarding). Semua 401 sampai H-05 mendarat; hanya `/api/health` & `/api/status`
  hidup. Pemetaan 401 & penolakan lokal terbukti terhadap node hidup (laporan
  writer), sisanya hanya terhadap fake.
- **HTTP 410 & 429 dari node sungguhan.** Hanya terbukti terhadap `Http::fake()`;
  butuh sesi pairing nyata / lockout untuk melihatnya dari Hermes.
- **QA-03 (redirect) secara empiris.** Saya membacanya dari perilaku bawaan
  Guzzle/klien HTTP Laravel, bukan dari memicu redirect terhadap node sungguhan.
  Perilaku pelucutan header `Authorization` lintas-host adalah perilaku
  terdokumentasi Guzzle, bukan yang saya uji di sini.
- **Runbook `RUNBOOK_KLIEN_PERTAMA.md` end-to-end** dan **putaran manual dari HP**
  — belum pernah dijalankan (sesuai pernyataan writer); saya tidak menjalankannya.
- **Asumsi H-05 mendukung dua token berdampingan** (langkah 2 rotasi) — H-05
  belum ada di repo Hermes; tidak dapat dibuktikan sekarang.

---

# Tindak lanjut writer — 2026-09-22

Diputuskan Bos: **perbaiki dulu sebelum lanjut task baru**, dan QA-08 **diamankan**
(token di-hash), bukan dibiarkan sebagai keputusan terbuka.

## Status akhir tiap temuan

| ID | Severity | Status | Cara ditutup |
|---|---|---|---|
| QA-01 | MEDIUM | **DIPERBAIKI** | `provisionPlatform()` memakai `firstOrCreate` berkunci `owner_user_id` + `type` + `is_platform_provided`; penghitung hanya naik saat baru. 2 test baru, termasuk jalur `--type=primary` yang dulu **gagal** (bukan idempoten) karena hook `saving` melempar |
| QA-02 | MEDIUM | **DIPERBAIKI** | Kenyataan jadi sumber kebenaran: penjaga kapasitas membandingkan `max_capacity` dengan jumlah profil yang **nyata** menempel (`HermesNode::isAtCapacity()`), kolom `active_profiles` diturunkan ke peran cache yang diselaraskan (`syncActiveProfiles()`) setelah provisioning dan setelah pencabutan trial. Tidak ada lagi `increment`/`decrement` — dua mekanisme untuk satu angka pasti menyimpang |
| QA-03 | MEDIUM | **DIPERBAIKI** | `withoutRedirecting()` + `connectTimeout()` pada bridge (`sendText`, `ping`) dan control plane. **Temuan tambahan saat memperbaiki:** 3xx bukan `failed()`, jadi `withoutRedirecting()` sendirian hanya mengubah "diikuti" menjadi **sukses palsu** — control plane akan mengembalikan `['raw' => '']` sebagai keberhasilan. Ditambah cabang `redirect()` eksplisit di ketiga jalur. 3 test negatif di `RedirectGuardTest` |
| QA-04 | MEDIUM | **DIPERBAIKI** | Penjaga dibalik dari daftar-hitam permukaan tenant menjadi **daftar-putih berkas yang boleh** (`HermesControlPing`, `app/Livewire/Admin/`, `app/Services/Hermes/`). Diverifikasi bukan hijau palsu: berkas probe sementara di akar `app/Livewire/` — persis titik buta yang QA tunjukkan — tertangkap penjaga baru, lalu probe dihapus |
| QA-05 | LOW | **DITERIMA, tidak diubah** | Urutan validasi memang berubah (profil/node diperiksa sebelum nomor/pesan). Tidak ada dampak keamanan atau data; hanya pesan galat pada kombinasi yang tidak diuji siapa pun. Klaim "perilaku tidak berubah" dikoreksi di sini, dan itu bentuk tanggung jawab yang benar — bukan mengubah kode supaya klaim lama jadi benar |
| QA-06 | LOW | **KLAIM DIKOREKSI** | Yang benar: **tidak ada jalur produksi yang mengetik status _siap_ dengan tangan**; hanya `ProfileStatusRefresher` menulis `paired`. Penulisan `unpaired` oleh provisioning dan cleanup adalah arah aman dan disengaja |
| QA-07 | LOW | **DIPERBAIKI** | Factory default `connected` → `paired`. Tidak ada test yang bergantung pada literal lama. Efek sampingnya: kosakata permisif `ready_statuses` sekarang benar-benar tinggal untuk baris lama saja, seperti yang diklaim semula |
| QA-08 | INFO | **DIAMANKAN** (keputusan Bos) | Basis data menyimpan **SHA-256** token, bukan tokennya. Lihat bagian di bawah |
| QA-09 | INFO | **DITERIMA, dibiarkan laten** | Tidak bisa dipicu daftar-putih saat ini (dua DELETE yang ada bukan `PROFILE_QUERY`). Menutupnya sekarang berarti menulis kode untuk endpoint yang belum ada; yang menahannya adalah `ControlPlanePathCoverageTest` yang memaku tempat profil untuk setiap path, jadi endpoint `DELETE` ber-query baru wajib menambah kasus uji dan akan langsung terlihat |
| QA-10 | INFO | **DITERIMA, belum diubah** | `withoutOverlapping()` benar untuk armada besar, tetapi armada sekarang satu node satu profil. Dicatat sebagai pekerjaan yang menyertai T-105 (penempatan node), bukan tambalan terpisah |
| QA-11 | INFO | **DITERIMA, belum diubah** | Gerbang `=== 30/60/83` memang rapuh terhadap jeda penjadwal. Tidak diubah karena cabang H+90 belum menghapus apa pun, jadi risiko data hari ini nihil — dan menggantinya dengan jendela adalah keputusan desain tangga dunning, bukan perbaikan cacat |

## QA-08 — bagaimana token diamankan

`webhook_secret_reference` sekarang menyimpan **SHA-256** dari token; plaintextnya
hanya pernah ada sekali di terminal operator.

**Kenapa SHA-256 telanjang, bukan bcrypt/argon.** Token dicari **berdasarkan
nilainya** (satu query), bukan diverifikasi terhadap baris yang sudah diketahui,
jadi hash bersalt menuntut pemindaian seluruh tabel. Yang membuat SHA-256 memadai
adalah entropi tokennya sendiri: 40 karakter acak, bukan kata sandi buatan manusia.
Salt dan work factor ada untuk melawan rendahnya entropi kata sandi manusia; di sini
keduanya tidak menambah perlindungan apa pun.

**Plaintext tidak diterima sebagai cadangan.** Menerima keduanya berarti tidak
mengamankan apa pun. Konsekuensinya dinyatakan terbuka lewat migration
`2026_09_23_140000_revoke_plaintext_bot_tokens`: baris lama ditandai
`needs_reissue_*` dan **setiap bot yang sudah terpasang berhenti bisa memanggil
TenantBot API sampai operator menerbitkan ulang tokennya**. Nilai lama tidak
di-hash di tempat, karena itu justru akan membuat token yang mungkin sudah bocor
lewat backup kembali sah.

**Satu jalur baru yang dituntut oleh perubahan ini:** `bos:hermes-profile --reissue`.
Menyimpan hash berarti token yang hilang tidak bisa ditampilkan ulang; tanpa jalan
menerbitkan ulang, satu-satunya pilihan operator adalah menghapus profil — dan itu
memutus peta company↔profil (D-37). Perintah juga tidak lagi mencetak apa pun yang
menyerupai token pada jalankan kedua, karena menampilkan nilai yang tidak akan
pernah bekerja lebih buruk daripada mengatakan apa adanya.

**Sisa yang tidak ditutup di sini:** `HermesProfileProvisioner` (dua method yang
sampai sekarang tidak dipanggil dari mana pun) kini juga menyimpan hash, tetapi ia
tidak punya cara mengembalikan plaintext ke pemanggilnya. Kalau jalur itu kelak
dipakai untuk onboarding, ia harus mengembalikan token sekali — bukan menyimpannya.

## Temuan QA yang menyentuh pekerjaan di luar Fase 10

QA (dan sub-agent yang saya pakai) sama-sama menemukan **suite ini tidak aman
dijalankan dua kali bersamaan**: dua proses `php artisan test` berbagi
`storage/framework/testing/disks` dan penyimpanan JSON yang sama, sehingga muncul
`UnableToWriteFile`, `UnableToCreateDirectory`, dan galat identitas usaha yang
**berubah antar-jalankan**. Setiap berkas hijau saat dijalankan sendiri. Ini layak
task tersendiri: gate yang hasilnya berubah antar-jalankan tidak bisa dipakai
sebagai gate.

Satu lagi, kecil tapi berguna diketahui: preset `custom` **tidak** menyalakan
`system.ai_agent`, jadi test jalur bot harus memakai preset yang menyalakannya
(mis. `bengkel`).

## Gate sesudah seluruh perbaikan

| Perintah | Hasil |
|---|---|
| `$env:DATA_SOURCE="json"; php artisan test` | **1.239 passed / 5.813 assertions**, 0 gagal |
| `vendor/bin/pint --test` | PASS, 554 berkas |
| `php artisan migrate --force` | migration pencabutan token DONE |
| `npm run build` | PASS |
