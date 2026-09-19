# PRESET BATCH 1 — Fase 6 Ekspansi Preset

**Worktree:** `D:\PROJECTS\agentic-bos` (branch `main`)
**Basis:** HEAD `0964089` saat batch dimulai
**State:** DONE

## Preset yang ditambahkan (6, semuanya Tier A)

| Slug | Nama | Kapabilitas aktif | Entity workflow |
|---|---|---|---|
| `warnet_gaming` | Warnet / Rental Konsol | contacts, scheduling, bookings, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent | `bookings` |
| `cuci_sepatu` | Jasa Cuci Sepatu | contacts, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent | `orders` |
| `percetakan` | Percetakan / Sablon / Digital Printing | contacts, quotations, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent | `orders` |
| `service_ac` | Jasa Service AC & Elektronik | contacts, scheduling, bookings, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent | `bookings` |
| `toko_bangunan` | Toko Bangunan & Material | contacts, inventory, pos, quotations, finance.cashbook, hr.employees, approval_flow, system.ai_agent | `orders` |
| `cleaning_service` | Jasa Cleaning Service | contacts, scheduling, bookings, projects, timesheet, quotations, finance.cashbook, hr.employees, approval_flow, system.ai_agent | `bookings` |

Nol kapabilitas baru. Nol kunci Tier B dipakai. Seluruh kunci berasal dari
katalog terkunci (D-32) dan lolos `PresetDefinitionValidator`.

## Metrik utama: preset ditambah : diff kode

**6 preset : 0 baris kode domain baru.** `git diff --stat` batch ini hanya
menyentuh:

- `database/presets/*.json` (6 file baru)
- `tests/Feature/PresetCompositionBatch1Test.php` (baru)
- `tests/Feature/JsonPresetSourceTest.php` (daftar preset kanonik 22 -> 28)
- `docs/worker-reports/PRESET_BATCH_1.md` (laporan ini)

Nol diff di `app/` dan `resources/` — dijaga otomatis oleh
`test_batch_slugs_never_appear_in_application_code`, yang memindai seluruh
`app/` + `resources/` dan gagal bila ada satu slug batch ini muncul di kode.

## Keputusan desain yang perlu diketahui reviewer

1. **Tidak ada folder `storage/app/json/{slug}-*`.** Preset gelombang
   sebelumnya (T-24b/T-24c/T-24d: kursus, katering, gym, cuci_mobil,
   barbershop, kedai_kopi, fotografi, dll.) juga tidak punya folder itu —
   hanya 4 company demo era Fase 2 (`bengkel-arka`, `klinik-sehat`,
   `salon-ayu`, `laundry-bersih`) yang punya. Sejak datasource Eloquent
   aktif, company dirakit dari DB (factory + `BusinessPresetSeeder`), bukan
   dari JSON folder. Menambah folder JSON untuk preset baru akan menjadi data
   mati yang tidak pernah dibaca. Karena itu batch ini mengikuti pola gelombang
   sebelumnya, bukan pola Fase 2.

2. **Widget dibatasi pada 5 yang benar-benar terimplementasi**
   (`upcoming_schedule`, `low_stock`, `kpi_cashflow`, `deals_pipeline`,
   `pending_approvals`). Katalog `INDUSTRY_PRESETS.md` §4 memuat 18 widget dan
   validator menerima semuanya, tetapi `WidgetRegistry::REQUIREMENTS` baru
   mengimplementasikan 5 — sisanya dilewati diam-diam oleh `DashboardComposer`
   sehingga dashboard tampak kosong tanpa error. Test
   `test_batch_dashboard_widgets_are_backed_by_an_active_capability` mengunci
   aturan ini sekaligus memastikan kapabilitas pendukung tiap widget aktif.

3. **`manufacturing.production_order` TIDAK dipakai.** Instruksi batch
   menyebutnya sebagai Tier B yang sudah ada, tetapi kunci itu tidak ada di
   `PresetDefinitionValidator::CAPABILITIES` maupun
   `FeatureResolver::CAPABILITIES`. Memakainya akan langsung ditolak validator.
   Tier B yang benar-benar tersedia hanya `pharmacy.prescription` dan
   `construction.retention`. Bila preset manufaktur dibutuhkan, itu perlu
   keputusan D-32/D-33 terpisah dulu (sejalan T-25/T-25b yang masih BLOCKED).

4. **`addon.loyalty` / `addon.managed_storage`** ada di
   `FeatureResolver::CAPABILITIES` tetapi **tidak** di katalog validator
   preset, jadi preset tidak dapat merujuknya. Dicatat sebagai
   ketidaksinkronan dua katalog — bukan blocker batch ini.

## Kandidat yang di-skip (tidak dipaksakan)

- **Katering rumahan** — duplikat `katering` yang sudah ada.
- **Studio foto/rekaman** — beririsan dengan `fotografi`; bagian "rekaman
  musik" lebih tepat sebagai penyewaan studio per jam dan akan tumpang tindih
  dengan `kos_coworking`/`rental`. Perlu keputusan produk, bukan ditebak.
- **Bengkel las/bubut, bengkel motor listrik** — beririsan `bengkel`;
  pembeda sesungguhnya (job order per spesifikasi teknis) butuh kapabilitas
  produksi yang belum ada (lihat poin 3).
- **Apotek non-resep / toko obat bebas** — secara kapabilitas identik
  `pharmacy` minus `pharmacy.prescription`; layak jadi preset tersendiri tapi
  menyentuh ranah kepatuhan D-50, sebaiknya lewat gate yang sama dengan
  klinik/apotek.

## Verifikasi (output asli, bukan klaim)

### `php artisan test` — batch ini

```
php artisan test --filter="PresetCompositionBatch1Test|JsonPresetSourceTest"
{"tool":"phpunit","result":"passed","tests":12,"passed":12,"assertions":279,"duration_ms":580}
```

### `php artisan test` — full suite

Suite **tidak** 100% hijau, dan itu **bukan** akibat batch ini. Diukur dua kali
dengan dan tanpa file batch ini (6 preset + test batch dipindah keluar repo
sementara, lalu dikembalikan):

| | tests | passed | failed | errors |
|---|---|---|---|---|
| Baseline (tanpa batch ini) | 507 | 166 | 27 | **314** |
| Dengan batch ini | 513 | 173 | 26 | **314** |

Batch ini **menambah 6 test lulus, memperbaiki 1 assertion daftar preset, dan
tidak menambah satu pun kegagalan/error baru**. Jumlah `errors` identik
(314 → 314).

### `vendor/bin/pint --test`

```
{"tool":"pint","result":"passed"}
```

### `git diff --stat` (tracked)

```
 tests/Feature/JsonPresetSourceTest.php | 7 ++++---
 1 file changed, 4 insertions(+), 3 deletions(-)
```

Sisanya file baru (untracked) milik batch ini. Tidak ada file di `app/` atau
`resources/` yang tersentuh.

## Kerusakan pre-existing yang ditemukan (bukan dari batch ini, perlu pemilik)

Semuanya sudah merah di HEAD `0964089` sebelum batch ini, dibuktikan dengan
menjalankan ulang tanpa file batch ini:

1. **314 errors di hampir seluruh suite** — mayoritas test ber-`RefreshDatabase`
   error. Ini masalah environment/migrasi, jauh lebih besar dari lingkup preset.
   Perlu ditangani lebih dulu sebelum angka "full suite hijau" bisa dipakai
   sebagai gate lagi.
2. **`BusinessPresetSeederTest::test_seeder_loads_all_presets_into_database`**
   dan **`PresetCompositionTest::test_new_presets_are_rendered_without_hardcoding`**
   — keduanya error:
   `Received Mockery_N_Illuminate_Console_OutputStyle::askQuestion(), but no expectations were specified`
   (trace: `SymfonyStyle.php:234` ← `tests/TestCase.php:16`). Terjadi saat
   seeding di dalam test. Karena itu `PresetCompositionBatch1Test` sengaja
   dibuat tanpa `RefreshDatabase`/seeder — verifikasinya murni terhadap definisi
   preset, sehingga tidak ikut tersandung bug ini.
3. **`RenderAllPresetsTest::test_laundry_preset_renders_pos_screen_and_kanban_without_exception`**
   — `/app/pos?company=laundry-bersih` mengembalikan 403 (bukan 200). Test ini
   ditulis untuk alur demo `?company=` era Fase 2; setelah auth/tenant Fase 3
   aktif, alur itu tidak lagi berlaku. Perlu ditulis ulang oleh pemilik area
   auth/tenant, bukan ditambal dari batch preset.

Test pertama `RenderAllPresetsTest::test_all_routes_render_for_all_presets_without_exception`
tetap **hijau**: preset tanpa entri di peta slug-nya dilewati, jadi 6 preset baru
tidak membuatnya merah.

## Sisa risiko

- Cakupan render end-to-end untuk 6 preset ini belum dibuktikan karena jalur
  seeder/`RefreshDatabase` sedang rusak (poin 1 & 2 di atas). Begitu itu
  dibereskan, cara termurah menutup celah ini adalah menambahkan keenam slug ke
  array `$presets` di `PresetCompositionTest`.
- Widget di luar 5 yang terimplementasi masih bisa dideklarasikan preset lain
  tanpa peringatan apa pun; hanya batch ini yang dijaga test. Kandidat untuk
  dijadikan aturan global bila disetujui.
