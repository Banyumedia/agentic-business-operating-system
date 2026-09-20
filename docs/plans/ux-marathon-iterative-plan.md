# PLAN MARATON UI/UX — ITERASI BERKESINAMBUNGAN

## Model orkestrasi (Hermes = orkestrator, hemat token)
Loop per iterasi (satu batch kecil per putaran, bukan banjir sekaligus):
1. **Writer** = Claude Code di worktree terpisah (`task/ux-iter-N`), scope file sempit.
2. **QA independen** = OpenCode `--agent plan` (read-only) review diff lane itu.
   - Jika QA tidak bisa memverifikasi (izin tool) → Hermes final-gate sendiri.
   - Jika ragu → QA wajib, tidak boleh skip.
3. **Final gate** = Hermes: `DATA_SOURCE=json php artisan test`, `pint --test`, `npm run build`, `git diff --check`.
4. **Merge serial** ke main satu lane per satu waktu; full regression setelah tiap merge.
5. Restart app 8002 agar Bos bisa cek dari HP; lapor ringkas.

## Aturan keras tiap iterasi
- Scope file sempit & eksplisit; tidak menyentuh file lane lain.
- D-31 (industri=data, `term()`), D-24 (`/app/{module}`), D-45 (konfirmasi bertingkat), D-26 (`company_id`).
- Fail-closed untuk uang/export/erasure/void; negative test untuk aksi berisiko.
- Tidak ada migration, dependency baru, push, atau deploy tanpa izin Bos.
- A11y (aria/focus/kontras) + mobile-first selalu.
- Berhenti & tanya Bos bila butuh keputusan di luar docs.

## Antrian iterasi (prioritas, satu per satu)
Diambil dari temuan nyata + umpan balik Bos. Batch pertama (kecil, terukur):
1. **I1 — Onboarding lanjutan:** pastikan alur onboarding baru benar-benar menyelesaikan setup company (bukan hanya form), termasuk membuat `BusinessIdentity` + preset tersetting + redirect ke dashboard yang terisi. Uji end-to-end owner baru.
2. **I2 — Polesan visual HP:** cek kontras, tap-target ≥44px, dan loading/empty state di halaman yang paling sering dibuka (dashboard, POS, contacts, accounting).
3. **I3 — Command palette & navigasi:** pastikan hasil pencarian bisa diklik dan membawa ke halaman benar di HP (bukan hanya desktop).

Iterasi berikutnya ditentukan dari temuan Bos saat cek HP (loop sampai Bos bilang "cukup").

## Watchdog
Cronjob tiap 15 menit: cek app publik + proses writer; bangunkan Hermes hanya bila ada lane selesai, error, atau butuh keputusan. Tidak polling kosong.
