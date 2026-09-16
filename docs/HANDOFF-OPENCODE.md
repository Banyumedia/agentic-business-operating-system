# HANDOFF — Agentic BOS (untuk sesi OpenCode baru)

Dokumen ini merangkum satu sesi kerja panjang (2026-09-16) yang tidak
sepenuhnya tercermin di commit message. Baca ini dulu sebelum menyentuh
apa pun, lalu baca `HERMES.md` dan `docs/AUTOPILOT_STATUS.md` seperti biasa.

## Apa proyek ini

Agentic BOS: Laravel 13 + Livewire v4 + Tailwind v4. Bukan software 6
industri — arsitekturnya **Composable Capability (D-31)**: industri = data
(preset JSON), kapabilitas = kode (~20 modul generik). Target ≥50 jenis
bisnis. Owner: dipanggil "Bos", komunikasi Bahasa Indonesia.

Sumber kebenaran, urutan prioritas bila bertentangan:
`docs/00-DECISIONS.md` > `HERMES.md` > `docs/EXECUTION_PLAN.md` > dokumen lain.

## Keputusan yang baru dikunci sesi ini (D-31 sampai D-46)

Semua di `docs/00-DECISIONS.md`. Yang paling mengubah arah proyek:

- **D-31**: industri = data, kapabilitas = kode. Tidak ada nama industri di
  flag/tabel/kode.
- **D-42 (paling besar)**: **frontend-first**. Fase 2 membangun SELURUH
  aplikasi dengan data JSON di balik interface (`EntityRepository`,
  `PresetSource`, `CompanyContext`); Fase 3 hanya mengganti `Json*` →
  `Eloquent*` lewat `.env` `DATA_SOURCE`, **Blade tidak berubah**. JSON
  menggantikan tabel, bukan logika. Karena ini, task lama T-05/T-06/T-08b-e
  dipindah jadi T-F1..T-F15 di Fase 2 (lihat `EXECUTION_PLAN.md`).
- **D-43**: 5 tema tetap (A Slate+Emerald default, B Zinc+Amber, C Navy+Sky,
  D Stone+Terracotta, E terang). **Per-USAHA** (owner set, semua staf lihat
  sama) — BUKAN per-user/localStorage. Nilai CSS lengkap ada di
  `UX_UI_SPEC.md §7.3a`.
- **D-44**: non-PKP tidak pernah melihat kata "DPP"/"PPN" — disembunyikan
  total, bukan tampil Rp 0.
- **D-45**: konfirmasi 3 tingkat menurut akibat (ketik YA hanya untuk aksi
  fiskal ireversibel; 2 tombol untuk yang bisa dipulihkan; tanpa konfirmasi
  untuk reversibel). Tombol modal **inert 400ms** sejak muncul (bukan delay
  setelah klik) untuk cegah tap-tembus.
- **D-46**: validator preset **menolak** workflow dengan dead-end atau stage
  tak terjangkau; tahap QC/pemeriksaan wajib ada cabang mundur (rework
  loop); transisi mundur wajib `requires_note`.
- **D-37**: satu bot Hermes per OWNER (bukan per company) — owner banyak
  usaha cukup 1 nomor WA, bot tanya kalau konteks company ambigu. Bot
  tambahan (mis. untuk manager cabang) = add-on berbayar.
- **D-47**: Super Admin (Anda/tim platform) **boleh** membantu setup akun
  klien yang tidak sempat/gaptek, lewat **"Login As" beraudit** — bukan
  tahu password klien. Banner permanen "mode Bantuan Admin" saat aktif,
  semua perubahan tercatat `changed_by_type=admin_impersonation`, aksi
  finansial tetap lewat D-45 (tidak ada bypass approval), admin tidak bisa
  lihat secret/password klien. Ini **melengkapi** U-01 (owner self-
  onboarding), bukan menggantikannya — keduanya jalan berdampingan.
  Task-nya **T-17c**, di Fase 4, `BLOCKED` sampai ada mockup panel admin
  (belum digarap sama sekali, beda dari mockup `/app/*` yang sudah ada).
- Q-01..Q-08 semua terjawab → jadi D-34..D-41. **Tidak ada item OPEN.**

`docs/INDUSTRY_PRESETS.md §7` berisi peta 63 bisnis potensial → kapabilitas
(95% Tier A tanpa kode). `EXECUTION_PLAN.md` Fase 6 = ekspansi preset pasca
UI-LOCK.

## Rencana eksekusi saat ini

Urutan: T-01..T-04 (DONE) → **T-07 (READY, task berikutnya)** → T-F1..T-F15
(Fase 2 frontend-first) → gate `HUMAN:UI-LOCK` → Fase 3a/b/c (fondasi
tenant, ganti ke database) → Fase 4 (API/AI) → Fase 5 (QA) → Fase 6.

T-07 = design token 5 tema + `ThemeContrastTest` (5×11 = 55 kasus AA) +
Settings shell. Belum dikerjakan.

**Tag rollback:** `pre-fase-2` — kembali ke sini kalau ada yang kacau.

## Autopilot Hermes (Telegram)

Profile `agentic-bos-coding-agent`, bot `@NalarinArkaBot`. Kontrak sudah
dipangkas 45% (SOUL 6KB→1.5KB, HERMES 7KB→4KB, tools 30→10 toolset). Model
saat ini `gpt-5.6-sol` — **belum diganti** meski direkomendasikan pindah ke
`gpt-5.3-codex` (lebih murah, tanpa cache-write cost, cocok agentic loop).
Ganti model = edit `config.yaml` lalu `hermes gateway restart`.

Aturan penting yang sudah ditulis di `HERMES.md`/skill:
- Resume dari `AUTOPILOT_STATUS.md` dulu, jangan sweep ulang tiap task.
- Commit lokal pre-authorized; **jangan `git add -A`** — stage by-path.
- Kalau ada file yang bukan buatan sendiri di worktree: jangan sentuh,
  jangan commit, pindah task.
- D-31 guard: berhenti kalau task tampak butuh kode khusus industri.

## Kiro CLI — writer/reviewer alternatif (BARU, belum dipakai eksekusi nyata)

`C:\Users\User\AppData\Local\Kiro-Cli\kiro-cli.exe`. Login: Builder ID
`didik.w.yudi@gmail.com`. MCP Playwright terpasang (browser sungguhan +
screenshot — ini yang membuatnya lebih unggul dari review baca-kode).

Cara pakai headless (PENTING — bug PowerShell):
```powershell
# JANGAN taruh prompt panjang/berkarakter kutip langsung sebagai argumen —
# PowerShell merusak quoting dan argumen native exe terpecah.
# Solusi: tulis prompt ke file .md, minta Kiro membacanya sendiri:
& kiro-cli chat --no-interactive --trust-all-tools "Baca file X.md lalu kerjakan semua instruksi di dalamnya"
```
`--trust-all-tools` diperlukan untuk MCP Playwright (tool approval tidak
didukung di mode non-interactive). Sesi headless **sering mati mendadak**
("Error: Internal error") di tengah tugas panjang — gunakan
`chat --resume --no-interactive --trust-all-tools "lanjutkan dari mana
tadi berhenti..."` untuk melanjutkan tanpa mengulang dari awal.

Kiro sudah TERBUKTI lebih baik dari saya untuk **verifikasi UI**: saya
sempat salah menyatakan drawer mobile "sudah benar" hanya dari membaca
atribut HTML (`role=dialog` ada di kode) — Kiro membuktikan lewat klik
sungguhan bahwa drawer itu **tidak pernah terbuka** (bug nyata). Pelajaran:
jangan percaya kode statis untuk klaim interaksi; minta Kiro uji langsung.

## Worktree kedua: mockup (jangan dihapus, jangan di-merge ke main)

```
D:\PROJECTS\agentic-bos          -> branch main       (produk nyata)
D:\PROJECTS\agentic-bos-mockup   -> branch mockup/ux-dummy (visual only)
```

17+ layar mockup sudah dibuat Antigravity (agent lain, di luar kendali
saya) sebagai **referensi visual untuk UI-LOCK**, BUKAN kode produksi.
Jangan pernah menyalin file mockup langsung ke `main` — pola datanya
per-layar, bertentangan dengan kontrak D-42 (data per-entitas/preset).

Status terkini mockup (per laporan Kiro review ke-2,
`docs/mockup-review/kiro-review-2/LAPORAN-VERIFIKASI.md` di repo mockup):
- FIXED & terverifikasi: drawer mobile, 4 tombol kritis kasir (44px),
  search interaktif, modul kontraktor lengkap (pipeline/detail/quotation/
  retention-preview dengan badge "Tier B" jelas).
- **BELUM BERES (N-01, PENTING)**: preset Klinik/Salon/Laundry di dropdown
  tidak mengubah konten dashboard (masih tampil data Bengkel). Prompt
  perbaikan sudah dikirim ke Antigravity, **belum dikonfirmasi selesai**
  saat sesi ini berakhir — cek `git -C agentic-bos-mockup log` untuk
  status terbaru sebelum lanjut.
- 3 temuan minor (fokus tak kembali ke tombol Menu, 2 tombol sekunder
  <44px) — tidak blocker.

**UI-LOCK BELUM DIBERIKAN Bos.** Jangan mulai Fase 2 produksi sampai Bos
eksplisit bilang "UI-LOCK, lanjutkan".

## Akses jarak jauh (Bos sering di luar, kerja dari HP)

```
https://agentic-bos.nalar.army/       -> main, port 8000 (basic auth)
https://bos-mockup.nalar.army/mockup  -> mockup, port 8001 (basic auth sama)
User: bos / Password: HxxTi_93rx7hnMiS
```
Basic auth via Caddy (`D:\PROJECTS\nalarin\Caddyfile`) karena aplikasi
BELUM punya login sendiri (T-00c masih di Fase 3). **Jangan hapus basic
auth sebelum ada auth aplikasi.** Dev server autostart lewat
`%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\AgenticBOS_DevServers.vbs`
(bukan folder di dalam repo ini). Catatan DNS: Cloudflare Universal SSL
cuma nutup 1 tingkat subdomain — makanya host mockup `bos-mockup.nalar.army`
bukan `mockup.agentic-bos.nalar.army` (yang itu gagal TLS).

Telegram: bot minta topic mode diaktifkan manual oleh Bos di HP untuk 3
kanal (Progress/Approval/Hasil) — terakhir dicek, Topics belum aktif di
chat itu, config `dm_topics` sudah menunggu.

## Yang TIDAK boleh ditebak / masih perlu keputusan Bos

- Tidak ada Q-xx terbuka di dokumen. Tapi **N-01 mockup** perlu ditunggu
  selesai dari Antigravity sebelum Bos bisa menilai UI-LOCK secara adil
  untuk preset non-Bengkel/Kontraktor.
- Model Hermes belum diganti (rekomendasi ada, keputusan final Bos).
- Modul Tier B berikutnya (manufaktur BOM/WIP atau pinjaman/angsuran) —
  T-25 di Fase 6, keputusan Bos nanti, bukan sekarang.

## Kesalahan yang jangan diulang (pelajaran sesi ini)

1. Jangan klaim "sudah oke"/"siap lock" dari membaca kode atau HTTP 200
   saja. Interaksi nyata (drawer, klik, JS hydration) hanya bisa dibuktikan
   dengan uji browser sungguhan.
2. Jangan `git add -A` di worktree yang mungkin disentuh agent lain.
3. Jangan taruh prompt panjang ke Kiro CLI sebagai argumen langsung kalau
   ada tanda kutip — tulis ke file dulu.
4. Kalau sebuah keputusan (mis. cakupan tema) sudah "terlanjur" digambar di
   mockup dengan asumsi berbeda dari yang Bos putuskan, **dokumen resmi
   menang** — mockup dikoreksi mengikuti dokumen, bukan sebaliknya.
