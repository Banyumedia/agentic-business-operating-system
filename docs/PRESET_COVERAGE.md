# Preset Coverage

Dokumen ini mencatat cakupan preset yang benar-benar tersedia pada
`database/presets/*.json`. Industri tetap data (D-31): nama bisnis hanya ada
di preset/dokumentasi, sedangkan runtime memakai capability, terminology,
workflow, widget, dan menu generik.

## Ringkasan

- Preset tersedia: **40**.
- Tier A: **37**.
- Tier B: **3** (`contractor`, `pharmacy`, `praktek_dokter`).
- Semua preset divalidasi oleh `PresetDefinitionValidator` saat dibaca melalui
  `PresetSource`.
- Coverage pasar adalah daftar preset yang tersedia, bukan klaim bahwa semua
  variasi bisnis di pasar telah selesai.

## T-24d — Batch 1

| Preset | Capability utama | Workflow | Widget utama | Navigasi utama |
|---|---|---|---|---|
| `warnet_gaming` | bookings, inventory, pos, finance.cashbook | bookings | upcoming_schedule, low_stock, kpi_cashflow, pending_approvals | bookings, inventory, pos |
| `cuci_sepatu` | inventory, pos, finance.cashbook | orders | low_stock, kpi_cashflow, pending_approvals | contacts, inventory, pos |
| `percetakan` | quotations, inventory, pos, finance.cashbook | orders | low_stock, kpi_cashflow, pending_approvals | quotations, inventory, pos |
| `service_ac` | bookings, inventory, pos, finance.cashbook | bookings | upcoming_schedule, low_stock, kpi_cashflow, pending_approvals | bookings, inventory, pos |
| `toko_bangunan` | quotations, inventory, pos, finance.cashbook | orders | low_stock, kpi_cashflow, pending_approvals | quotations, inventory, pos |
| `cleaning_service` | bookings, projects, quotations, timesheet | bookings | upcoming_schedule, kpi_cashflow, pending_approvals | bookings, projects, quotations |

Kontrak runtime yang dibuktikan test:

1. Semua capability berasal dari katalog `FeatureResolver`.
2. Semua key menu dapat di-resolve oleh `DynamicMenuRegistry`.
3. Capability `quotations` selalu memiliki route generik `/app/quotations`,
   tidak bergantung pada capability `projects`.
4. `pending_approvals` membaca tiket approval aktif dan belum kedaluwarsa,
   bukan quotation; repository tetap terisolasi per company.
5. Workflow valid, setiap stage dapat mencapai terminal, dan transisi mundur
   mewajibkan catatan.
6. Slug preset tidak muncul sebagai percabangan di `app/` atau `resources/`.

## Seluruh Preset Tersedia

| Tier | Preset |
|---|---|
| A | `agency`, `bakery_preorder`, `barbershop`, `bengkel`, `cleaning_service`, `cuci_mobil`, `cuci_sepatu`, `custom`, `desain_interior`, `eo`, `fnb`, `fotografi`, `gym`, `it_support`, `kantor_hukum`, `katering`, `kedai_kopi`, `klinik`, `kos_coworking`, `kurir_lokal`, `kursus`, `laundry`, `mebel_custom`, `optik`, `penjahit`, `percetakan`, `petshop`, `rental`, `rental_sound`, `salon`, `service_ac`, `toko_bangunan`, `toko_bunga`, `toko_frozen`, `toko_hp`, `travel_umroh`, `warnet_gaming` |
| B | `contractor`, `pharmacy`, `praktek_dokter` |

## Capability Tier B Manufaktur

D-57 mengunci `manufacturing.production_order` sebagai capability Tier B.
Kontrak runtime dan validator sekarang mensyaratkan
`inventory.bom` + `inventory.batch_expiry` + `finance.accounting`. Capability
ini bukan nama industri dan
belum dinyalakan oleh preset batch T-24d; aktivasi preset produksi tetap harus
memenuhi dependency dan plan gate.

## Verifikasi

```bash
APP_CONFIG_CACHE=storage/framework/cache/phpunit-config.php php artisan test tests/Feature/PresetCompositionBatch1Test.php tests/Feature/BusinessPresetSeederTest.php
APP_CONFIG_CACHE=storage/framework/cache/phpunit-config.php php artisan test
php vendor/bin/pint --test
```
