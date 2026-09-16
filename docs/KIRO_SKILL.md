# Kiro Delegation Contract

Dokumen ini adalah **kontrak prompt** antara Hermes bot dan Kiro CLI untuk
project Agentic BOS. Dibaca oleh Kiro saat menerima delegasi task.

Otoritas dokumen: `docs/00-DECISIONS.md` > `HERMES.md` > `docs/EXECUTION_PLAN.md` > dokumen ini.

---

## Identitas Kiro dalam Project Ini

Kiro adalah **coding executor** untuk Agentic BOS. Ia tidak mengambil
keputusan arsitektur — ia mengeksekusi task yang sudah didefinisikan di
`EXECUTION_PLAN.md` sesuai keputusan di `00-DECISIONS.md`.

---

## Format Prompt Delegasi dari Hermes

Hermes bot akan mengirim prompt dengan struktur berikut:

```
TASK: <task_id> — <nama task>
MODE: <execute | review-only | status-check | queue-edit>
COMPLEXITY: <normal | reasoning>

KONTEKS:
- Project: D:\PROJECTS\agentic-bos (Laravel 13 + Livewire v4 + Tailwind v4)
- Baca: HERMES.md > docs/AUTOPILOT_STATUS.md > docs/EXECUTION_PLAN.md
- Authority: docs/00-DECISIONS.md menang jika ada konflik

INSTRUKSI KHUSUS:
<instruksi spesifik atau kosong>

RULES:
- Auto-commit diizinkan (gate HUMAN:COMMIT sudah terbuka)
- Format commit: type(scope): message
- Jangan commit .env atau file asing
- Test + pint + build wajib lulus sebelum commit
- Parallel hanya untuk read-only (review, audit, search)
- Writer tetap serial
- Stop setelah Fase 2 (T-F15) — jangan mulai Fase 3

STOP CONDITION:
php artisan test → PASS
vendor/bin/pint --test → clean
npm run build → OK (jika ada perubahan Blade/CSS/JS)

LAPORAN BALIK (format Telegram):
[<task_id>] <state> | <files changed> | <test/pint/build> | <blocker atau next>
```

---

## Prosedur Standar Kiro (setiap sesi)

### 1. Resume — jangan rediscover

```
1. Baca docs/AUTOPILOT_STATUS.md — task aktif, evidence terakhir, next READY
2. git status --short && git log --oneline -3
3. Baca EXECUTION_PLAN.md — hanya baris task target + decisions yang dikutip
```

### 2. Validasi task

Sebelum menulis satu baris kode, periksa:
- State task = `READY`? (semua depends-on DONE, tidak ada Q-xx OPEN)
- Tidak melewati gate yang belum dibuka?
- Tidak butuh secret/service eksternal tanpa stub?

Jika tidak valid → lapor BLOCKED dengan alasan persis, lanjut ke task READY lain.

### 3. Kerjakan

- Tulis sub-steps di STATUS sebelum edit pertama
- Test dulu jika ini defect proven (RED test)
- Implementasi sekecil mungkin — tidak ada refactor di luar scope task
- Verifikasi: `php artisan test` → `vendor/bin/pint --test` → `npm run build`

### 4. Commit (pre-authorized)

```bash
git status --short          # pastikan hanya file milik task ini
git add <paths-spesifik>    # JANGAN git add -A atau git add .
git commit -m "type(scope): message"
```

Jangan commit: `.env`, `*.lock` yang tidak diubah task, file asing.

### 5. Update STATUS dan lapor

Update `docs/AUTOPILOT_STATUS.md`:
- State task → DONE / BLOCKED / PARTIAL
- Files changed
- Evidence (output command nyata, bukan klaim)
- Next READY task

Lapor ke Hermes dengan format:
```
[T-xx] DONE | app/X.php, tests/Y.php | 17 passed, pint clean, build OK | next: T-yy
```

---

## Aturan Paralel

| Boleh paralel | Harus serial |
|---|---|
| Search, read, audit | Edit file source |
| Review lane (diff, a11y, tenant) | Migration, config |
| Build dengan output terpisah | Commit |
| Test dengan SQLite :memory: per proses | Push |

---

## Auto-commit Rules

Gate `HUMAN:COMMIT` sudah terbuka. Commit tanpa tanya untuk:
- Source code, test, Blade/CSS/JS milik task aktif
- Docs update (STATUS, EXECUTION_PLAN)

Tetap butuh approval:
- Push ke remote
- Deploy / production migration
- Perubahan arsitektur di luar decisions yang sudah dikunci

---

## Model Selection Guide

Kiro menggunakan model Auto by default. Sampaikan ke Kiro jika task butuh
reasoning lebih dalam:

| Task | Rekomendasi |
|---|---|
| T-07 (CSS tokens + WAI-ARIA) | Auto |
| T-05, T-06 (UI screens) | Auto |
| T-08x (FeatureResolver, WorkflowEngine, DashboardComposer) | Reasoning |
| T-F1..T-F15 (screen patterns) | Auto |
| T-21c (bukti D-31 multi-preset) | Reasoning |
| Review, audit, diff | Auto |

---

## Queue Edit Contract

Hermes boleh meminta Kiro untuk mengubah antrian task sebelum eksekusi:

**Tambah task:**
- Edit `docs/EXECUTION_PLAN.md` — tambah baris dengan ID, description, depends-on, decisions, gate
- Update `docs/AUTOPILOT_STATUS.md` — catat penambahan di Completion Ledger

**Skip task:**
- Tandai task = `BLOCKED (skipped per owner: <tanggal>)` di EXECUTION_PLAN
- Catat di STATUS

**Ubah prioritas:**
- Hanya boleh mengubah urutan task yang belum `DONE`
- Tidak boleh mengubah depends-on yang menyebabkan dependency cycle

---

## Hard Stops

Kiro **harus berhenti dan lapor** jika:

1. Task membutuhkan nama industri di kode (violasi D-31)
2. Task membutuhkan Eloquent model/migration untuk business entity di Fase 2 (violasi D-42)
3. Task melewati `HUMAN:UI-LOCK` (Fase 3+)
4. Test tidak lulus setelah 2 pendekatan berbeda
5. Pint tidak clean pada file yang disentuh task
6. File asing (bukan milik task) perlu diubah untuk menyelesaikan task
7. Task butuh keputusan yang belum ada di `00-DECISIONS.md`

Format laporan hard stop:
```
[T-xx] BLOCKED | <file konflik> | <tes/pint/build status> | reason: <alasan persis>
```

---

## Worker Registry (Multi-Worktree Paralel)

Tiga worktree tambahan tersedia untuk paralel execution. Setiap worker punya
branch, folder, dan SQLite sendiri. `vendor/` dan `node_modules/` adalah
junction ke `main` (shared, read-only dari sisi worker).

| Worker | Path | Branch | Assigned Cluster |
|---|---|---|---|
| **main** | `D:\PROJECTS\agentic-bos` | `main` | T-07, T-F1, T-F2, T-F5, T-F9, T-F14, T-F15 (serial backbone) |
| **worker-a** | `D:\PROJECTS\agentic-bos-worker-a` | `worker-a` | T-F3 atau T-F4 (paralel setelah T-F2) |
| **worker-b** | `D:\PROJECTS\agentic-bos-worker-b` | `worker-b` | T-F6 atau T-F7 atau T-F8 (paralel setelah T-F5) |
| **worker-c** | `D:\PROJECTS\agentic-bos-worker-c` | `worker-c` | T-F10 atau T-F11 atau T-F12 atau T-F13 (paralel setelah T-F9) |

### Aturan Penggunaan Worker

1. **Cek file scope sebelum dispatch.** Dua worker tidak boleh menyentuh file yang sama.
   Periksa kolom `File Target` di EXECUTION_PLAN sebelum assign.

2. **Worker hanya menulis di branch-nya sendiri.** Setelah task selesai, Hermes
   merge branch worker ke `main` dengan `git merge --no-ff worker-x`.

3. **Merge ke main = serial.** Satu merge selesai dulu, baru merge berikutnya.
   Pastikan tidak ada konflik sebelum merge.

4. **SQLite per worker = terisolasi.** Test boleh jalan paralel antar worker
   karena database tidak berbagi. Tapi jangan jalankan test yang menulis file
   di `storage/app/` yang sama.

5. **vendor/ adalah shared junction — jangan `composer install/update` dari worker.**
   Dependency changes harus dari `main`.

### Merge Workflow

Setelah worker-a selesai task T-F3:
```bash
cd D:\PROJECTS\agentic-bos        # main
git merge --no-ff worker-a -m "feat(f3): JsonEntityRepository + JsonCompanyContext"
git worktree remove D:\PROJECTS\agentic-bos-worker-a  # kalau sudah tidak dipakai
git branch -d worker-a
# atau reset branch untuk task berikutnya:
git checkout worker-a && git reset --hard main
```

### Kapan Paralel Tidak Worth It

Jika dua task berikutnya di EXECUTION_PLAN **saling depends** atau **berbagi >3 file**,
lebih baik serial di `main`. Overhead merge lebih mahal dari waktu yang dihemat.

---

## Referensi Cepat

| File | Isi |
|---|---|
| `HERMES.md` | Kontrak eksekusi utama |
| `docs/AUTOPILOT_STATUS.md` | State task aktif, evidence, next READY |
| `docs/EXECUTION_PLAN.md` | Antrian task, dependencies, gates |
| `docs/00-DECISIONS.md` | Keputusan arsitektur, menang jika konflik |
| `docs/UX_UI_SPEC.md` | Spec UI, 36 token CSS, 5 tema, WAI-ARIA |
| `docs/INDUSTRY_PRESETS.md` | Katalog kapabilitas (§1), preset JSON |
| `docs/DATA_MODEL.md` | Skema tabel, konvensi, tenant isolation |
