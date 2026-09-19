# PRESET BATCH 1 — Fase 6 Ekspansi Preset

**Worktree:** `D:\PROJECTS\agentic-bos` (`main`)

**Commit awal:** `86b169e`

**State:** CORRECTED oleh T-24dR

Laporan awal Kiro tidak lagi menjadi bukti final. Audit menemukan komposisi
menu/widget yang lolos test statis tetapi tidak sesuai runtime. Bagian di bawah
ini adalah hasil koreksi yang telah diverifikasi.

## Preset batch

| Slug | Tier | Capability utama | Workflow |
|---|---|---|---|
| `warnet_gaming` | A | bookings, inventory, pos, finance.cashbook | `bookings` |
| `cuci_sepatu` | A | inventory, pos, finance.cashbook | `orders` |
| `percetakan` | A | quotations, inventory, pos, finance.cashbook | `orders` |
| `service_ac` | A | bookings, inventory, pos, finance.cashbook | `bookings` |
| `toko_bangunan` | A | quotations, inventory, pos, finance.cashbook | `orders` |
| `cleaning_service` | A | bookings, projects, quotations, timesheet | `bookings` |

Semua capability tambahan pada tabel tetap generik. Slug dan nama bisnis tidak
ditambahkan sebagai conditional/class/route/widget pada `app/` atau
`resources/`.

## Temuan audit dan koreksi

1. **Quotation tidak terjangkau**
   - Sebelumnya `percetakan` dan `toko_bangunan` mengaktifkan `quotations`
     tetapi memakai menu `orders` yang tidak terdaftar, sementara route lama
     bergantung pada `projects`.
   - Registry sekarang menyediakan modul generik `quotations` pada
     `/app/quotations`. Deep link lama `/app/projects/quotations` tetap
     terdaftar tetapi tidak diduplikasi di navigasi.
   - Preset batch yang memakai quotations mendeklarasikan menu `quotations`.

2. **Menu key tidak dikenal**
   - `orders` di `cuci_sepatu`, `percetakan`, dan `toko_bangunan` telah
     diganti dengan key registry yang benar.
   - Test batch sekarang memeriksa setiap menu key terhadap
     `DynamicMenuRegistry`, bukan hanya struktur JSON.

3. **Widget `pending_approvals` salah entity**
   - Sebelumnya widget menghitung quotation berstatus pending.
   - Sekarang widget membaca `approval_tickets`, hanya menghitung tiket
     `pending` yang belum kedaluwarsa, dan tetap terisolasi per company.
   - Effect `approval.request` pada driver JSON mempersist tiket melalui
     `EntityRepository`, sehingga alur workflow nyata muncul di widget.
   - Ditambahkan schema JSON generik `approval_tickets` dengan enum status dan
     channel, serta negative test untuk company lain, tiket consumed/expired,
     nilai enum tidak sah, dan integrasi `WorkflowEngine` ke widget.

4. **Copy workflow `cuci_sepatu` tidak sesuai effect**
   - Deskripsi sekarang menyatakan notifikasi owner setelah pesanan diambil,
     sesuai effect `notify.owner_wa` pada transisi aktual.

5. **Capability manufaktur tidak sinkron**
   - D-57 sudah mengunci `manufacturing.production_order`; tidak memerlukan
     keputusan baru.
   - Key tersebut sekarang sinkron di `FeatureResolver` dan
     `PresetDefinitionValidator` sebagai Tier B, fail-closed dengan dependency
     `inventory.bom` + `inventory.batch_expiry` + `finance.accounting`.

6. **Coverage terlalu statis**
   - Enam slug ditambahkan ke test render Eloquent end-to-end.
   - Preset dengan quotations diuji dapat membuka `/app/quotations`.
   - Seeder, JSON source, registry menu, widget JSON/Eloquent, tenant
     isolation, dan anti-hardcode diuji.
   - Anti-hardcode batch memeriksa slug underscore, slug hyphen, dan nama
     tampilan preset secara case-insensitive.

7. **Dokumentasi acceptance belum ada**
   - `docs/INDUSTRY_PRESETS.md` §6/§7 disinkronkan.
   - `docs/PRESET_COVERAGE.md` dibuat sebagai matriks coverage aktual 28
     preset dan kontrak T-24d.

## Koreksi atas klaim lingkungan test

Klaim awal tentang 314 error baseline berasal dari cache konfigurasi production
(`bootstrap/cache/config.php`), bukan defect source. Full suite harus memakai
cache konfigurasi PHPUnit yang terisolasi:

```bash
APP_CONFIG_CACHE=storage/framework/cache/phpunit-config.php php artisan test
```

Dengan command tersebut suite hijau. Karena itu catatan bahwa
`RefreshDatabase`, seeder, atau render preset rusak tidak lagi berlaku.

## Bukti verifikasi koreksi

```text
Focused: 119 passed, 769 assertions
Full suite: 527 passed, 2311 assertions
Pint: PASS, 386 files
Build: PASS, 1.02s
JSON: 47 valid
```

Command:

```bash
APP_CONFIG_CACHE=storage/framework/cache/phpunit-config.php php artisan test \
  tests/Unit/DynamicMenuRegistryTest.php \
  tests/Unit/PresetDefinitionValidatorTest.php \
  tests/Unit/SchemaValidatorTest.php \
  tests/Feature/PresetCompositionBatch1Test.php \
  tests/Feature/PresetCompositionTest.php \
  tests/Feature/BusinessPresetSeederTest.php \
  tests/Feature/DashboardTest.php \
  tests/Feature/DashboardEloquentTest.php \
  tests/Feature/EloquentFeatureResolverTest.php \
  tests/Feature/WorkflowEngineTest.php \
  tests/Feature/Workflow/ApprovalTicketTest.php
APP_CONFIG_CACHE=storage/framework/cache/phpunit-config.php php artisan test
php vendor/bin/pint --test
npm run build
```

## Sisa batas scope

- Capability `manufacturing.production_order` sudah terdaftar dan gated sesuai
  D-57, tetapi tidak diaktifkan oleh keenam preset Tier A ini.
- `addon.loyalty` dan `addon.managed_storage` bukan bagian batch T-24d dan tidak
  diaktifkan oleh preset ini; sinkronisasi katalog add-on tetap pekerjaan
  terpisah.
