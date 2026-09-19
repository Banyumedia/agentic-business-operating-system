# Laporan Worker — Preset Batch 2 (Fase 6 Ekspansi)

Status: selesai, sudah commit lokal (belum push).
Basis: HEAD `8b095b9`.
Jumlah preset sebelum batch ini: 28. Setelah batch ini: **34**.

## Preset yang ditambahkan

| Slug | Nama | Tier | Entity alur kerja | Kapabilitas |
|---|---|---|---|---|
| `rental_sound` | Rental Sound System & Rigging Event | A | `bookings` | contacts, scheduling, bookings, bookings.deposit, inventory, quotations, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `desain_interior` | Jasa Desain Interior | A | `projects` | contacts, deals, projects, quotations, milestone_billing, timesheet, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `toko_frozen` | Toko Oleh-oleh & Frozen Food | A | `orders` | contacts, inventory, inventory.batch_expiry, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `kurir_lokal` | Jasa Kurir & Antar Lokal | A | `orders` | contacts, scheduling, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `penjahit` | Penjahit / Tailor & Permak | A | `orders` | contacts, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `toko_bunga` | Toko Bunga & Florist | A | `orders` | contacts, scheduling, inventory, inventory.batch_expiry, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |

Semua Tier A: tidak ada kapabilitas baru, tidak ada kapabilitas Tier B/C yang dipakai.

## Metrik D-31

Diff batch ini:

```
database/presets/desain_interior.json        (baru)
database/presets/kurir_lokal.json            (baru)
database/presets/penjahit.json               (baru)
database/presets/rental_sound.json           (baru)
database/presets/toko_bunga.json             (baru)
database/presets/toko_frozen.json            (baru)
tests/Feature/PresetCompositionBatch2Test.php (baru)
tests/Feature/JsonPresetSourceTest.php       (daftar preset kanonik 28 -> 34)
docs/worker-reports/PRESET_BATCH_2.md        (baru)
```

**Nol baris diff di `app/` dan `resources/`.** Dijaga otomatis oleh
`test_batch_slugs_never_appear_in_application_code`, yang memindai seluruh
`app/` + `resources/` (php/css/js) untuk slug, slug ber-dash, dan nama preset.

## Temuan penting: kegagalan "314 errors" itu artefak config cache

Baseline lama yang saya catat di Batch 1 (`errors=314`) **bukan kerusakan kode**.
Penyebabnya file `bootstrap/cache/config.php` yang basi di working copy lokal:
karena config ter-cache, override `APP_ENV=testing` di `phpunit.xml` tidak
pernah dipakai, sehingga guard seperti
`JsonCompanyContext::assertDemoEnvironment()` melempar
`JsonCompanyContext hanya tersedia di environment demo.` dan jalur seeder
memunculkan error `askQuestion` pada Mockery.

Setelah `php artisan config:clear`:

```
php artisan test
→ {"tool":"phpunit","result":"passed","tests":534,"passed":534,"assertions":2564,"duration_ms":26034}
```

Suite **hijau penuh**. Tiga kegagalan yang saya laporkan sebagai pre-existing di
`PRESET_BATCH_1.md` (`BusinessPresetSeederTest`, `PresetCompositionTest`,
`RenderAllPresetsTest` laundry) juga hilang. Laporan Batch 1 pada bagian itu
perlu dianggap batal. Tidak ada satu pun file kode yang diubah untuk
mencapai ini; `config:clear` hanya membuang artefak lokal yang di-gitignore.

## Keputusan desain

1. **Widget dibatasi 5 yang benar-benar dirender** (`upcoming_schedule`,
   `low_stock`, `kpi_cashflow`, `deals_pipeline`, `pending_approvals`).
   Validator preset menerima 18 nama widget, tapi `WidgetRegistry::REQUIREMENTS`
   hanya mengimplementasikan 5. Memakai sisanya menghasilkan dashboard kosong
   tanpa error. Dijaga oleh
   `test_batch_dashboard_widgets_are_backed_by_an_active_capability`.

2. **Tidak membuat folder `storage/app/json/{slug}-*`.** Semua preset gelombang
   T-24b/c/d juga tidak punya; hanya 4 company demo era Fase 2 yang punya.
   Sejak datasource Eloquent aktif, company dirakit dari DB (factory +
   `BusinessPresetSeeder`). Menambah folder JSON hanya melahirkan data mati.

3. **Kunci menu memakai nama grup registry, bukan nama entity.** Draf awal
   empat preset POS memakai `"orders"`, yang bukan kunci di
   `DynamicMenuRegistry::catalog()` — grupnya bernama `pos`. Sudah dikoreksi;
   sekarang dijaga oleh `test_every_declared_menu_key_resolves_...`.

4. **Penawaran punya dua jalur sah.** `rental_sound` memakai grup `quotations`
   tersendiri; `desain_interior` yang berpusat proyek memakai submenu
   `projects > quotations` yang sudah disediakan registry. Test menjaga
   keterjangkauannya, bukan bentuk menunya.

5. **`kurir_lokal` dan `toko_bunga` mendapat grup `bookings`** karena keduanya
   mengaktifkan `scheduling`; tanpa itu kapabilitas jadwalnya tidak terjangkau
   dari navigasi.

## Kandidat yang di-skip (butuh keputusan terpisah)

- **Bengkel las/bubut, konveksi skala produksi** — butuh kapabilitas
  perintah produksi (`manufacturing.production_order`) yang **tidak ada** di
  `PresetDefinitionValidator::CAPABILITIES` maupun `FeatureResolver::CAPABILITIES`.
  Kandidat D-32/D-33 baru, bukan pekerjaan preset.
- **Program loyalitas pelanggan (`addon.loyalty`) dan gudang titipan
  (`addon.managed_storage`)** — ada di `FeatureResolver::CAPABILITIES` tapi
  belum masuk katalog validator preset. Tidak bisa dipakai preset sampai
  katalog disinkronkan.
- **Katering rumahan, studio foto** — duplikat/beririsan `katering` dan
  `fotografi`.

## Verifikasi (output asli)

```
php artisan test --filter="PresetCompositionBatch1Test|PresetCompositionBatch2Test|JsonPresetSourceTest"
→ {"tool":"phpunit","result":"passed","tests":20,"passed":20,"assertions":534,"duration_ms":1158}

php artisan test
→ {"tool":"phpunit","result":"passed","tests":534,"passed":534,"assertions":2564,"duration_ms":26034}

vendor/bin/pint --test
→ {"tool":"pint","result":"passed"}
```

`git diff --stat` + `git status --short` dicek: hanya path di daftar metrik
di atas yang di-stage. `storage/app/json/1/workflow_log.json` termodifikasi
sebagai efek samping test run dan **sengaja tidak di-stage**.

## Sisa risiko

- Preset batch ini belum punya company demo, jadi belum pernah dirender
  end-to-end lewat HTTP. `RenderAllPresetsTest` melewati preset tanpa entri
  slug map. Rendering nyata baru terbukti saat ada seed company yang memakainya.
- `docs/EXECUTION_PLAN.md` dan `docs/AUTOPILOT_STATUS.md` tidak disentuh,
  sesuai pembagian tugas dengan final-gate reviewer.
