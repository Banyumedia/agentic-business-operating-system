# T-69 (sisa) — Lajur WhatsApp platform yang benar-benar mengirim

Ditulis di berkas terpisah karena `EXECUTION_PLAN.md`, `AUTOPILOT_STATUS.md`,
`HERMES_NODE_CONTRACT.md`, dan `00-DECISIONS.md` **sedang disunting writer lain**
dan belum di-commit (HERMES §Parallel Writer Policy). Penandaan state T-69 di
tabel antrean menyusul setelah perubahan writer itu mendarat.

## Cacat yang ditutup

`App\Contracts\HermesNodeClient` dibind ke `FakeHermesNodeClient` yang **hanya
menulis ke log lalu mengembalikan `true`**. Akibatnya:

- dunning langganan (D-23/D-49) dan peringatan H-3 **tidak pernah terkirim**;
- kegagalannya **tidak pernah terlihat**, karena tidak ada pemanggil yang
  memeriksa nilai kembaliannya;
- test lama justru mengunci teks `"FAKE WA to ..."`, jadi yang terbukti selama ini
  adalah fake-nya — bukan lajurnya. Ini pola yang sama dengan cacat T-57/T-58:
  test hijau di atas nilai fabrikasi.

Bagian T-69 yang menyangkut lajur **tenant** sudah mendarat di `72923c2`
(`App\Services\HermesNodeClient` diarahkan ke kontrak bridge nyata). Yang
diselesaikan sekarang adalah sisanya: lajur platform.

## Yang dikerjakan

**`App\Services\Hermes\BridgeGateway` (baru).** Kontrak HTTP bridge dipindah ke
satu tempat: `POST /send`, `GET /health`, aturan "tanpa autentikasi hanya sah
untuk loopback", penyelesaian rahasia dari referensi, dan dua jebakan bridge
(`/health` menjawab 200 walau WhatsApp terputus; `/send` bisa menjawab 200 tanpa
mengirim). Alasannya bukan kerapian: ada dua pemanggil dengan kebijakan profil
berbeda, dan kalau masing-masing menyalin aturan loopback, keduanya akan
menyimpang. `App\Services\HermesNodeClient` sekarang mendelegasikan ke gateway
ini; pesan galatnya **tidak berubah** sehingga seluruh test lamanya tetap hijau.

**`App\Services\Hermes\PlatformHermesNodeClient` (baru, jadi binding bawaan).**
Mengirim lewat profil `is_platform_provided` bertipe
`hermes.platform.sender_profile_type` (bawaan `addon` = bot CS).

Batas yang tidak dilonggarkan: **tidak ada jatuh kembali ke bot dev.** Bot dev
(`primary`) punya toolset penuh dan nomornya internal; memakainya untuk menagih
pelanggan membocorkan nomor itu **dan** memberi pelanggan kanal ke bot yang
berwenang menjalankan perintah. Kalau bot CS belum ada atau belum paired, lajur
ini menolak.

Kontraknya mengembalikan `bool`, jadi kegagalan tidak melempar. Supaya tidak
kembali menjadi kegagalan senyap, setiap penolakan dicatat dengan **sebabnya**;
nomor tujuan dan isi pesan tidak ikut dicatat, dan rahasia disebut lewat nama
referensinya saja.

**`DunningLadder`: peringatan dicatat hanya bila benar-benar terkirim.**
`recordNotification()` menulis `dunning_notified_at`, dan cabang H+90 memakai
"sudah 3 peringatan" sebagai dasar company boleh dihapus. Mencatat peringatan yang
tidak pernah sampai berarti menyiapkan penghapusan data atas dasar yang tidak
benar. Konsekuensi yang diterima sadar: **tanpa bot platform yang siap, tangga ini
tidak akan pernah sampai ke penghapusan** — dan itu arah yang benar.

**`BillingCheckExpiring`: hasil kirim diperiksa**, jumlah yang gagal dilaporkan
sebagai peringatan. Exit code tetap 0: tagihan harus tetap diproses walau
notifikasinya gagal, dan penjadwal tidak perlu dibanjiri alarm.

**`bos:hermes-send` (baru).** Membuktikan lajur hidup tanpa menunggu tagihan jatuh
tempo — sebelumnya satu-satunya cara adalah menunggu, sehingga lajur rusak baru
terlihat pada saat paling merugikan. Dua lajur dipisah persis seperti di kode
(D-63): `--platform` atau `--company=<id>`, dan memberi keduanya sekaligus
ditolak. Nomor tujuan wajib ditulis penuh; tidak ada bawaan dan tidak ada "kirim
ke semua".

## Test

`tests/Feature/Hermes/PlatformDeliveryTest.php` — 16 test, 11 di antaranya
negatif: tanpa profil platform, profil **tenant** tidak boleh dipakai, bot dev
bukan cadangan, profil `unpaired`, node tidak aktif, rahasia belum dipasang
(disebut lewat nama referensi, **bukan** nilainya), nomor dan isi pesan tidak
pernah masuk log, non-2xx, HTTP 200 tanpa konfirmasi, dunning tidak mencatat
peringatan yang gagal terkirim, dan perintah gagal keras saat lajur belum siap.
Ditambah satu penjaga berkas: tiga jalur tenant (`ReceivableReminderService`,
`TeamInvitationService`, `NotifyOwnerWa`) wajib memakai kelas yang ter-scope
company dan dilarang menyentuh antarmuka platform (D-63).

Dua berkas test lama disesuaikan: `BillingCheckExpiringTest` dan
`DunningLadderFailClosedTest` sekarang **menyatakan** `FakeHermesNodeClient` di
`setUp()`. Keduanya menguji tangga dunning, bukan transportnya — tetapi itu harus
dinyatakan, bukan diwarisi dari bawaan aplikasi.

## Gate

- `DATA_SOURCE=json php artisan test` → **1.197 passed / 5.675 assertions, 0 gagal**
- `vendor/bin/pint --test` → **PASS 536 berkas**
- `npm run build` → PASS
- `migrate:fresh` tidak dijalankan: task ini **tidak menambah migration**.

## Verifikasi terhadap lingkungan nyata

Dijalankan di DB dev terhadap Hermes lokal, dan rantainya berhenti di tempat yang
benar:

1. Tanpa profil platform → `Belum ada bot platform bertipe addon.` (log), perintah
   keluar dengan kode gagal.
2. Setelah `bos:hermes-profile --platform --owner=2 --node=2 --type=addon` →
   `Bot platform belum siap mengirim (status unpaired).`
3. `bos:hermes-profile-status` → `unpaired`, keterangan `cURL error 7: Failed to
   connect to 127.0.0.1 port 3000` (bridge Hermes sedang tidak berjalan; lebih awal
   di sesi yang sama ia menjawab `{"status":"disconnected"}`).

Tidak ada nomor maupun isi pesan yang muncul di log — hanya sebabnya.

## Yang **belum** terbukti

**Pesan sungguhan belum pernah keluar lewat lajur ini.** Untuk membuktikannya
dibutuhkan nomor CS yang sudah paired pada profil Hermes tersendiri
(`RUNBOOK_KLIEN_PERTAMA.md` §1). Sampai itu ada, yang terbukti adalah seluruh
rantai penolakannya dan bentuk permintaan ke bridge — bukan pengirimannya.

Perintah pembuktiannya sudah ada dan satu baris:

```powershell
php artisan bos:hermes-send --platform --to=628... --message="Uji"
```
