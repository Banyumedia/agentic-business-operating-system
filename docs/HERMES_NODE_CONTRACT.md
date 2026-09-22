# Kontrak Node Hermes (pengiriman WhatsApp tenant)

Status: **ASUMSI, belum dikonfirmasi.** NalarPesan dikesampingkan (D-67), jadi
tidak ada node sungguhan yang bisa dipakai untuk memastikan bentuk di bawah ini.

Dokumen ini ada supaya asumsinya **tertulis**, bukan tersembunyi di dalam kode.
Siapa pun yang nanti menyambungkan node sungguhan cukup membandingkan bentuk di
bawah dengan bentuk node yang asli, lalu menyesuaikan satu tempat.

## Yang sudah pasti (ada di basis data, bukan asumsi)

| Tempat | Isi |
|---|---|
| `hermes_nodes.api_url` | Alamat dasar node, mis. `https://node-01.contoh.test` |
| `hermes_nodes.api_secret_reference` | **Nama** rahasia, bukan nilainya |
| `hermes_nodes.status` | Harus `active` agar dipakai |
| `hermes_profiles.instance_id` | Identitas sesi WhatsApp milik satu owner |
| `hermes_profiles.status` | Harus siap; `unpaired` ditolak |
| `hermes_profile_companies` | Pivot penentu company mana yang dilayani profil |

Nilai rahasianya dipetakan di `config/hermes.php` → `node_secrets`, diisi dari
environment. Basis data **tidak pernah** menyimpan kredensial (COMMERCIAL
§Hermes Profile).

## Yang diasumsikan (perlu dikonfirmasi ke node asli)

Permintaan kirim:

```
POST {api_url}{HERMES_SEND_PATH}      # default /api/wa/send
X-Hermes-Secret: {rahasia dari config('hermes.node_secrets')}
Content-Type: application/json

{
  "instance_id": "inst_primary_12_ab12cd34",
  "to": "6281234567890",
  "message": "Tagihan jatuh tempo besok: INV-202609-0001 ..."
}
```

Respons yang dianggap berhasil: HTTP 2xx **dan** badan JSON yang memuat
`"ok": true` atau `"sent": true`.

Pemeriksaan kesehatan:

```
GET {api_url}{HERMES_HEALTH_PATH}     # default /api/health
X-Hermes-Secret: {rahasia}
```

## Bagian mana yang murah diubah, bagian mana yang butuh kode

| Bagian | Cara menyesuaikan |
|---|---|
| Path kirim & path health | `HERMES_SEND_PATH`, `HERMES_HEALTH_PATH` di `.env` — **tanpa ubah kode** |
| Batas waktu | `HERMES_TIMEOUT` |
| Kosakata status profil yang dianggap siap | `config('hermes.delivery.ready_statuses')` |
| **Nama field payload** (`instance_id`/`to`/`message`) | butuh ubah `App\Services\HermesNodeClient` |
| **Nama header auth** (`X-Hermes-Secret`) | butuh ubah kelas yang sama |
| **Cara menyatakan sukses** di badan respons | butuh ubah kelas yang sama |

Tiga baris terakhir itulah yang saya maksud ketika meminta "kontrak endpoint":
path bisa dikonfigurasi, tetapi bentuk payload, nama header, dan penanda sukses
ada di dalam kode. Kalau node asli memakai, misalnya,
`{"session": ..., "phone": ..., "text": ...}` dengan header `Authorization:
Bearer`, maka yang berubah adalah kode — bukan `.env`.

## Perilaku sekarang tanpa node

`App\Services\HermesNodeClient` **menolak** mengirim bila tidak ada node
terdaftar, profil belum siap, node tidak aktif, atau rahasia belum dipasang. Itu
**benar**, bukan bug. Pemanggilnya menangani penolakan itu dengan baik:

- `ReceivableReminderService` (T-49) menghapus jejak pengingatnya, jadi putaran
  berikutnya mencoba lagi — tidak ada pengingat yang hilang atau ganda.
- `NotifyOwnerWa` menggagalkan transisi workflow, jadi tidak ada state yang
  berubah seolah pemberitahuan sudah terkirim.
- `TeamInvitationService` (T-51) gagal sebelum undangan dianggap beredar.

Pemeriksaan konfigurasi bisa dijalankan tanpa mengirim pesan ke siapa pun:

```powershell
php artisan bos:hermes-ping
php artisan bos:hermes-ping --url=https://node-01.contoh.test --secret-ref=node_01
```

## Jangan disamakan dengan dua hal ini

1. **Hermes agent di PC pengembangan** (WebUI port `9119`, `hermes.nalar.army`).
   Itu antarmuka agent; daftar rutenya tidak punya endpoint kirim WhatsApp.
   Istilah "gateway" di sana berarti gateway chat agent.
2. **`App\Contracts\HermesNodeClient`.** Antarmuka itu untuk WhatsApp **platform**
   (dunning langganan D-23/D-49), tidak ter-scope company, dan implementasi
   bawaannya hanya menulis log. Pesan atas nama tenant wajib lewat
   `App\Services\HermesNodeClient` (D-63).
