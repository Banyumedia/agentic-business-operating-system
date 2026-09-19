# Laporan Worker — Preset Batch 3 (Fase 6 Ekspansi)

Status: koreksi audit selesai; commit koreksi lihat `git log`.
Basis yang diaudit: commit Kiro `f8fb5ea`.
Jumlah preset sebelum batch ini: 34. Setelah batch ini: **40**.

## Preset yang ditambahkan

| Slug | Nama | Tier | Entity alur kerja | Kapabilitas |
|---|---|---|---|---|
| `toko_hp` | Toko & Service Handphone | A | `orders` | contacts, scheduling, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `petshop` | Petshop & Grooming Hewan | A | `bookings` | contacts, scheduling, bookings, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `optik` | Optik & Kacamata | A | `orders` | contacts, scheduling, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `it_support` | Jasa IT Support & Maintenance | A | `bookings` | contacts, deals, scheduling, bookings, projects, inventory, quotations, milestone_billing, timesheet, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `kantor_hukum` | Kantor Hukum & Konsultan Legal | A | `projects` | contacts, deals, projects, quotations, milestone_billing, timesheet, finance.cashbook, hr.employees, approval_flow, system.ai_agent |
| `mebel_custom` | Workshop Mebel Custom | A | `projects` | contacts, deals, projects, projects.progress_billing, quotations, milestone_billing, inventory, inventory.bom, finance.cashbook, hr.employees, approval_flow, system.ai_agent |

Semua Tier A. Tidak ada kapabilitas baru dan tidak ada kapabilitas Tier B yang
dinyalakan — termasuk `manufacturing.production_order` yang baru dikunci D-57,
karena aktivasinya masih butuh gate rencana tersendiri. `mebel_custom` sengaja
memakai `inventory.bom` (Tier A) saja untuk rincian material.

## Metrik D-31

```
database/presets/it_support.json              (baru)
database/presets/kantor_hukum.json            (baru)
database/presets/mebel_custom.json            (baru)
database/presets/optik.json                   (baru)
database/presets/petshop.json                 (baru)
database/presets/toko_hp.json                 (baru)
tests/Feature/PresetCompositionBatch3Test.php (baru)
tests/Feature/JsonPresetSourceTest.php        (daftar preset kanonik 34 -> 40)
docs/worker-reports/PRESET_BATCH_3.md         (baru)
```

## Koreksi audit runtime

Audit lanjutan membuktikan empat key efek dapat lolos test hardcoded meski
tidak memiliki handler di `WorkflowEngine`: `stock.reserve`, `stock.deduct`,
`invoice.create_dp`, dan `invoice.create_final`. Key tersebut dihapus dari
preset sampai kontrak input, idempotensi, dan model reservasi/invoice bisnis
diputuskan serta diimplementasikan. Alias deposit lama dan pemanggilan handler
deposit tanpa kontrak input runtime juga dihapus dari preset sampai mutasinya
benar-benar aman. `notify.owner_wa` dikeluarkan dari workflow preset sampai ada
outbox/idempotensi yang mencegah pengiriman ganda saat penulisan log gagal.

`WorkflowEngine` sekarang melakukan preflight seluruh efek terhadap registry
dan capability efektif tenant sebelum efek pertama, stage, atau log berubah.
Validator dan test komposisi membaca registry runtime yang sama.

## Dua guard baru di test batch ini

Batch 1 dan 2 masing-masing melahirkan satu kelas bug yang lolos sampai tahap
verifikasi. Keduanya sekarang dijaga otomatis:

1. `test_every_active_capability_has_a_navigable_home` — capability yang
   dinyalakan tapi grup menu pemiliknya tidak ada di `menus.order` adalah
   fitur mati: ikut dihitung di tier, tapi tidak pernah bisa dibuka. Guard ini
   menangkap `it_support` yang menyalakan `timesheet` tanpa grup `projects`
   (satu-satunya rumah submenu Timesheet di `DynamicMenuRegistry`). Dikoreksi
   dengan menambah capability + menu `projects` ("Kontrak Maintenance"),
   mengikuti preseden `cleaning_service` yang juga bookings + projects +
   timesheet.

2. `test_batch_workflow_effects_are_registered_and_capability_backed_at_runtime`
   — setiap efek wajib mempunyai handler runtime nyata dan capability induk
   aktif. Guard lintas-preset di `JsonPresetSourceTest` mencegah vocabulary
   validator kembali berbeda dari registry eksekusi.

Koreksi diterapkan pada data preset dan runtime; test hardcoded lama tidak lagi
menjadi sumber kebenaran effect handler.

## Temuan pada preset lama (tidak saya sentuh)

Audit guard nomor 1 dijalankan terhadap **seluruh 40 preset**. Delapan belas
preset Fase 6 (batch 1-3) bersih. Lima pelanggaran ada di preset era lebih awal:

| Preset | Capability aktif | Grup menu yang hilang |
|---|---|---|
| `bakery_preorder` | `pos` | `pos` |
| `bengkel` | `projects`, `scheduling`, `pos` | `projects`, `bookings`, `pos` |
| `katering` | `pos` | `pos` |
| `laundry` | `hr.employees` | `employees` |

Saya **tidak memperbaikinya**: di luar cakupan batch ini dan berisiko bentrok
dengan writer lain. Diserahkan ke final-gate reviewer untuk diputuskan, apakah
jadi task koreksi tersendiri atau guard ini dipromosikan ke test lintas-preset.
Perintah audit yang saya pakai ada di riwayat sesi; mapping capability ke grup
menu bisa dibaca langsung dari `test_every_active_capability_has_a_navigable_home`.

## Keputusan desain

1. **Tidak memakai `manufacturing.production_order`** walau D-57 sudah
   mengunciya sebagai Tier B dan validator sudah menerimanya.
   `docs/PRESET_COVERAGE.md` menyatakan aktivasinya butuh dependency lengkap
   (`inventory.bom` + `inventory.batch_expiry` + `finance.accounting`) **dan**
   plan gate. Batch preset bukan tempat membuka gate itu.
2. **Widget tetap dibatasi 5 yang benar-benar dirender** oleh
   `WidgetRegistry::REQUIREMENTS`.
3. **Tidak membuat folder `storage/app/json/{slug}-*`**, konsisten dengan batch
   1-2 dan seluruh preset gelombang T-24b/c/d.
4. **Kandidat yang di-skip karena beririsan preset lain**: katering rumahan
   (`katering`), studio foto (`fotografi`), rental mobil (`rental`), wedding
   organizer (`eo`), sablon kaos (`percetakan`), bengkel AC (`service_ac`).
   Gadai/pegadaian di-skip karena ranah kepatuhan, bukan komposisi preset.

## Verifikasi koreksi audit (output asli)

```
APP_CONFIG_CACHE=storage/framework/cache/phpunit-config.php php artisan test
→ Tests: 550 passed (3008 assertions)

php vendor/bin/pint --test
→ PASS, 388 files

npm run build
→ built in 926ms

validasi json
→ JSON valid: 40 preset
```

`storage/app/json/1/workflow_log.json` dipulihkan ke HEAD setelah test dan tidak
di-stage. Artefak operasional asing `caddy_check.json` tidak disentuh.

## Sisa risiko

- Preset batch ini belum punya company demo, jadi belum pernah dirender
  end-to-end lewat HTTP. Pembuktian rendering nyata menunggu seed company.
- Otomatisasi reservasi/pengurangan stok serta invoice DP/final tidak diklaim
  tersedia sampai handler transaksional dan idempoten benar-benar dibangun.
