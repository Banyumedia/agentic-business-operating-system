# Worker Reports (Paralel Execution)

Folder ini menampung evidence per task **selama** task itu dikerjakan sebagai
salah satu dari beberapa worker paralel (lihat `HERMES.md` §Parallel Writer
Policy dan `docs/EXECUTION_PLAN.md` §Matriks Grup Paralel).

## Kenapa folder terpisah, bukan langsung ke STATUS

`docs/AUTOPILOT_STATUS.md` adalah satu file yang dibaca dan ditulis semua
worker saat resume. Kalau 2+ worker paralel menulis ke situ bersamaan,
konflik merge nyaris pasti. Aturan: **worker paralel dilarang menulis
STATUS**; mereka tulis laporan sendiri di sini. Hanya writer yang melakukan
merge serial ke `main` yang merangkum laporan-laporan ini ke STATUS.

## Kapan dipakai

- **Dipakai** bila task sedang berjalan di worktree terpisah bersamaan
  dengan task paralel lain (grup lebar > 1 di §Matriks Grup Paralel).
- **Tidak dipakai** bila mengerjakan serial langsung di `main` — dalam kasus
  itu update `docs/AUTOPILOT_STATUS.md` langsung seperti biasa.

## Format file

Satu file per task: `docs/worker-reports/{TASK-ID}.md` (mis.
`docs/worker-reports/T-F10.md`).

```markdown
# {TASK-ID} — Worker Report

**Worktree:** D:\PROJECTS\agentic-bos-worker-x
**Branch:** task/{TASK-ID}
**State:** DONE | PARTIAL | BLOCKED

## Implemented
- ...

## Files
- ...

## Evidence
- `php artisan test` → ...
- `vendor/bin/pint --test` → ...
- `npm run build` → ... (jika ada perubahan Blade/CSS/JS)

## Risk / Remaining
- ...
```

## Setelah merge

Writer yang menjalankan `git merge --no-ff task/{TASK-ID}` di `main` wajib:
1. Re-run `php artisan test` + `vendor/bin/pint --test` di `main` (bukan
   percaya evidence di laporan worker apa adanya).
2. Pindahkan ringkasan laporan ini ke `docs/AUTOPILOT_STATUS.md` Completion
   Ledger + Detail Task Selesai.
3. Boleh menghapus file laporan ini setelah dirangkum (opsional — riwayat
   git tetap menyimpannya).
