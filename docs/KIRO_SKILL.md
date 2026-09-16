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
- Paralel-write lintas worktree diizinkan **bersyarat** — lihat HERMES.md
  §Parallel Writer Policy sebelum klaim task apa pun
- Writer tetap serial kecuali 6 syarat paralel terpenuhi
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

Otoritas: `HERMES.md` §Parallel Writer Policy. Ringkasan cepat:

| Boleh paralel selalu | Boleh paralel bila lolos 6 syarat | Harus serial selalu |
|---|---|---|
| Search, read, audit | Edit file source (deps DONE, bukan migration, bukan dependency, file lepas, worktree+branch sendiri) | Migration, config dependency |
| Review lane (diff, a11y, tenant) | | Merge ke `main`, commit ke `main` |
| Build dengan output terpisah | | Push |
| Test dengan SQLite `:memory:` per proses | | Task konvergensi (lihat EXECUTION_PLAN §Matriks Grup Paralel) |

Jangan menulis `docs/AUTOPILOT_STATUS.md` saat sedang jadi salah satu dari
beberapa worker paralel — tulis ke `docs/worker-reports/{TASK-ID}.md`, biarkan
writer yang merge ke `main` yang merangkum ke STATUS.

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
folder dan SQLite sendiri. `vendor/` dan `node_modules/` adalah junction ke
`main` (shared, read-only dari sisi worker).

| Worker | Path |
|---|---|
| **main** | `D:\PROJECTS\agentic-bos` |
| **worker-a** | `D:\PROJECTS\agentic-bos-worker-a` |
| **worker-b** | `D:\PROJECTS\agentic-bos-worker-b` |
| **worker-c** | `D:\PROJECTS\agentic-bos-worker-c` |

**Assignment task tidak lagi statis per worker.** Task mana yang boleh
paralel dan kapan ditentukan oleh `docs/EXECUTION_PLAN.md` §Matriks Grup
Paralel, bukan tabel tetap di sini — grup paralel berubah tiap fase. Cek
matriks itu dulu sebelum dispatch.

**Klaim task = buat branch `task/{TASK-ID}`** di worktree yang dipakai (bukan
memakai nama branch `worker-a`/`worker-b`/`worker-c` tetap untuk task apa
pun). `git worktree list` adalah registry klaim yang hidup — Git menolak dua
worktree memakai branch sama, jadi tabrakan klaim dicegah mekanis, bukan
dengan disiplin manual.

### Aturan Penggunaan Worker

1. **Cek Matriks Grup Paralel dulu.** Task hanya boleh diklaim paralel bila
   berada di grup dengan lebar > 1 dan tanpa syarat khusus yang belum
   dipenuhi (lihat kolom "Syarat khusus").
2. **Cek file scope sebelum dispatch.** Dua worker tidak boleh menyentuh file
   yang sama. Periksa kolom `File Target` di EXECUTION_PLAN sebelum assign.
3. **Worker hanya menulis di branch klaimnya sendiri**
   (`task/{TASK-ID}`). Setelah task selesai, writer `main` merge branch itu:
   `git merge --no-ff task/{TASK-ID}`.
4. **Merge ke main = serial.** Satu merge selesai dulu (test+pint hijau),
   baru merge berikutnya.
5. **SQLite per worker = terisolasi.** Test boleh jalan paralel antar worker
   karena database tidak berbagi. Tapi jangan jalankan test yang menulis file
   di `storage/app/` yang sama.
6. **vendor/ adalah shared junction — jangan `composer install/update` dari worker.**
   Dependency changes harus dari `main`.
7. **Jangan tulis `docs/AUTOPILOT_STATUS.md` dari worker paralel.** Tulis
   `docs/worker-reports/{TASK-ID}.md`; writer `main` yang merangkum ke STATUS
   saat merge.

### Merge Workflow

Setelah worker menyelesaikan task T-F10 di branch `task/T-F10`:
```bash
cd D:\PROJECTS\agentic-bos        # main
git merge --no-ff task/T-F10 -m "feat(f10): PipelineScreen + CalendarScreen"
php artisan test && vendor/bin/pint --test
git worktree remove D:\PROJECTS\agentic-bos-worker-a  # kalau sudah tidak dipakai
git branch -d task/T-F10
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
