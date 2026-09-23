# TX-ISO — Isolasi disk test agar full suite jadi gate tepercaya

Ditemukan saat TX-03; ditutup terpisah karena diagnosis awal di
`EXECUTION_PLAN.md` (bug di `JsonCompanySettingsStore::update()`) **tidak
tepat** — perlu koreksi sebelum siapa pun menganggap perbaikan itu masih
relevan.

## Diagnosis awal di plan, dan mengapa ternyata salah sasaran

Plan menuduh `JsonCompanySettingsStore::update()`: resolve `Storage::disk()->path()`
lalu menulis lewat `fopen`/`Filesystem::replace()` native, "melewati
`Storage::fake`". Diverifikasi dengan test reproduksi langsung
(`Storage::fake('company-json')` lalu `update()`, assert file real tidak
tersentuh): **tidak reproduksi**. `Illuminate\Support\Facades\Storage::fake()`
membangun ulang root disk itu sendiri (`LocalFilesystemAdapter` baru dengan
root fake), jadi `$disk->path()` yang dipanggil SESUDAH fake sudah menunjuk ke
root fake, bukan ke `storage/app/json` nyata. Kelas yang sama persis
(`JsonWorkflowLog::append()`, pola identik) juga terbukti fake-aware lewat
percobaan yang sama.

## Akar sebab sesungguhnya

`WorkflowEngine::transition()` menulis **audit JSON tanpa syarat**, terlepas
dari `config('datasource.driver')`. Baris relevan:

```php
if ($this->usesEloquentLog()) {
    $this->writeToDbLog(...);
} else {
    $this->log->append(...);   // <- masih jalan meski Eloquent
}
```

Cabang `transitioned` (jalur paling sering dipakai) malah menulis JSON dulu
**tanpa kondisi apa pun**, baru menambahkan tulis-DB kalau `usesEloquentLog()`.
Ini bukan bug — `JsonWorkflowLog` **tidak punya consumer** di mode Eloquent
(nol schema bernama `workflow_log`, nol pembaca lain di `app/`; satu-satunya
pembaca adalah test JSON-mode yang sengaja meng-assert isinya, mis.
`WorkflowEngineTest`). Sempat dicoba menggerbang tulis JSON di belakang
`! usesEloquentLog()` (menghapus dual-write) — **ditolak setelah gagal test**:
`WorkflowEngineTest::setUp()` sengaja set `datasource.driver = 'eloquent'`
(untuk alasan lain, kemudahan resolve dependency) **namun tetap mengasumsikan**
log JSON ditulis dan diuji isinya. Mengubah kontrak produksi berarti
mematahkan test yang sudah ada dan berpotensi memutus asumsi lain yang tidak
terlihat dari sini — di luar scope TX-ISO yang hanya minta isolasi test, bukan
perubahan perilaku dual-write.

**Kesimpulan:** dual-write itu disengaja dan dipertahankan apa adanya. Yang
sebenarnya rusak adalah test **Eloquent-mode (`RefreshDatabase`)** yang memicu
`WorkflowEngine::transition()` tanpa pernah memanggil
`Storage::fake('company-json')` — mereka mengira `RefreshDatabase` sudah cukup
mengisolasi mereka, padahal `JsonWorkflowLog` menulis file nyata terlepas dari
isolasi database.

## Bukti residu nyata (sebelum perbaikan)

```
git clean -fdq storage/app/json   # kondisi bersih
DATA_SOURCE=json php artisan test # 1338 passed, 0 gagal
git status --porcelain storage/app/json
# M storage/app/json/1/workflow_log.json
```

Isi diff: dua entri `transitioned` company `"1"` — satu preset `laundry`
(`terima`→`proses`), satu preset `bengkel` (`siap_diambil`→`selesai` +
`journal.post` amount 100000). Full suite tetap **hijau** karena tidak ada
test JSON-mode yang membaca `storage/app/json/1/*` (id numerik, bukan slug
demo) — tapi treenya tercemar dan setiap run mengubahnya lagi, membuat
`git status` sesudah test tidak pernah bersih.

## Lima test diperbaiki

Ditambahkan `Storage::fake('company-json')` di `setUp()`/sebelum aksi yang
memicu transisi, pada test yang terbukti memicu `WorkflowEngine::transition()`
tanpa pernah mem-fake disk itu:

| Berkas | Pemicu |
|---|---|
| `tests/Feature/DashboardEloquentTest.php` | `$engine->transition($record, 'dibatalkan', ...)` |
| `tests/Feature/ProjectWorkflowTest.php` | `$this->engine->transition($project, ...)` |
| `tests/Feature/BookingWorkflowEffectsTest.php` | `$engine->transition($this->booking, 'confirmed', ...)` |
| `tests/Feature/LaundryPresetDatabaseTest.php` | `$workflow->transition($order, 'proses', 'owner')` |
| `tests/Feature/OrderServiceTest.php` | `$service->payOrder($order, ...)` → `WorkflowEngine::transition()` |

`tests/Feature/Models/DealAndContactTest.php` diperiksa dan **tidak disentuh**:
sudah aman lewat `app()->bind(JsonWorkflowLog::class, ...)` yang meng-override
`append()` jadi no-op.

Produksi (`app/Services/Workflow/WorkflowEngine.php`,
`app/Services/Json/JsonCompanySettingsStore.php`) — **tidak diubah sama
sekali**. Perilaku dual-write tetap seperti semula.

## Verifikasi acceptance

```
git clean -fdq storage/app/json
DATA_SOURCE=json php artisan test   # 1338 passed / 6085 assertions
git status --porcelain storage/app/json   # kosong
DATA_SOURCE=json php artisan test   # 1338 passed / 6085 assertions (lagi)
git status --porcelain storage/app/json   # kosong (lagi)
```

Dua kali berturut hijau, tanpa residu — sesuai teks acceptance persis.

## Gate

- `DATA_SOURCE=json php artisan test`: **1338 passed / 6085 assertions**, dua
  kali berturut, 0 residu.
- `vendor/bin/pint --test`: PASS 567 file (seluruh repo, tidak ada pelanggaran
  pre-existing tersisa).
- `npm run build`: PASS (tidak ada perubahan Blade/CSS/JS; dijalankan sebagai
  verifikasi rutin, bukan karena diwajibkan perubahan ini).

## Utang yang sengaja tidak disentuh

- Dual-write JSON+DB di `WorkflowEngine::transition()` untuk mode Eloquent
  tetap ada. Kalau kelak ada yang ingin menghapusnya, `WorkflowEngineTest`
  (yang meng-assert isi `workflow_log.json` sambil `datasource.driver =
  'eloquent'`) harus ditinjau ulang dulu — task tersendiri, bukan bagian
  TX-ISO.
- Pola yang sama (menulis lewat `Storage::disk()->path()` + `fopen` native)
  ada juga di `app/Services/Json/JsonEntityRepository.php`, tapi kelas itu
  memakai `config('datasource.json_path')`, bukan disk `company-json`, dan
  tidak disentuh sama sekali di sini — di luar file target yang dinyatakan
  plan (`JsonCompanySettingsStore.php` + `tests/TestCase.php`), dan
  perubahannya jauh lebih besar (journal lintas-file, atomicity `tempnam`/
  `rename`) sehingga butuh task sendiri kalau memang perlu diisolasi juga.
