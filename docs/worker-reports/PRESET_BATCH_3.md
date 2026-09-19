# Laporan Worker — Preset Batch 3 (Fase 6 Ekspansi)

Status: selesai, sudah commit lokal (belum push).
Basis: HEAD `b8fc35c`.
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

**Nol baris diff di `app/` dan `resources/`.**

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

2. `test_batch_workflow_effects_are_backed_by_an_active_capability` — efek
   transisi yang capability-nya mati tidak punya tempat bekerja. Guard ini
   menangkap `it_support` yang memakai `invoice.create_final` tanpa
   `milestone_billing` maupun `pos`. Dikoreksi dengan menyalakan
   `milestone_billing` (kontrak maintenance ditagih per termin).

Keduanya ditemukan oleh test, bukan oleh review manual, dan keduanya diperbaiki
di sisi **data preset** — bukan dengan melonggarkan test atau mengubah kode.

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

## Verifikasi (output asli)

```
php artisan test --filter="PresetCompositionBatch3Test|JsonPresetSourceTest"
→ {"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":424,"duration_ms":794}

php artisan test
→ {"tool":"phpunit","result":"passed","tests":543,"passed":543,"assertions":2965,"duration_ms":27899}

vendor/bin/pint --test
→ {"tool":"pint","result":"passed"}
```

`storage/app/json/1/workflow_log.json` termodifikasi sebagai efek samping test
run dan **sengaja tidak di-stage**.

## Sisa risiko

- Preset batch ini belum punya company demo, jadi belum pernah dirender
  end-to-end lewat HTTP. Pembuktian rendering nyata menunggu seed company.
- `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`, dan
  `docs/PRESET_COVERAGE.md` tidak disentuh — ketiganya domain final-gate
  reviewer. Angka di `PRESET_COVERAGE.md` masih 28 dan perlu disegarkan ke 40.
