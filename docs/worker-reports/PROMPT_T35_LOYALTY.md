# Prompt T-35 — Loyalty Pelanggan (poin/voucher) untuk claude-cli

Salin blok di bawah ini persis ke claude-cli.

---

Kerjakan T-35: Add-on Loyalty Pelanggan (poin/voucher), kapabilitas
`addon.loyalty`, sesuai D-59 (`docs/00-DECISIONS.md`).

## Keputusan Bos yang WAJIB diikuti persis (D-59)

1. **Opt-in per company**, lewat `ModuleSetting`/`PlanCapabilityGate`
   (pola sama seperti `addon.branches`, `addon.efaktur`, dst — lihat
   `app/Services/FeatureResolver.php`). Company yang tidak mengaktifkan
   `addon.loyalty` tidak melihat fitur ini sama sekali.
2. **Aturan hitungan poin TIDAK BOLEH hardcode satu rumus.** Setiap
   company memilih mode + rasio sendiri saat setup, disimpan sebagai DATA
   konfigurasi (tabel `loyalty_rules` atau serupa), bukan `if` di kode:
   - **Mode nominal**: tiap Rp X transaksi = Y poin (X dan Y dikonfigurasi
     per company, bukan angka tetap di kode).
   - **Mode per-item/kategori**: produk/kategori tertentu bernilai poin
     berbeda (mis. produk A = 5 poin, produk B = 2 poin) — mapping
     item→poin disimpan sebagai data per company.
   - **Keduanya sekaligus**: company boleh mengaktifkan mode nominal DAN
     mode per-item bersamaan (akumulasi poin dari kedua sumber untuk 1
     transaksi jika keduanya match).
3. **Expiry poin opsional per company.** Company memilih sendiri: (a)
   poin punya masa berlaku dengan durasi dikonfigurasi (mis. field
   `expiry_months` nullable), atau (b) poin tidak pernah kedaluwarsa.
   **Default aman jika company tidak mengatur apa pun: TIDAK expire**
   (nullable/tanpa expiry).
4. **D-26**: `company_id` WAJIB di setiap tabel baru (`loyalty_rules`,
   `loyalty_points`/`loyalty_ledger`, `loyalty_vouchers` bila ada).
5. **D-31**: tidak ada nama industri (salon/klinik/laundry/dst) di
   flag/tabel/model/kondisi. Loyalty berlaku lintas industri manapun yang
   mengaktifkannya.

## Desain minimal yang disarankan (boleh disesuaikan asal prinsip di atas terpenuhi)

- `loyalty_rules` — per company: `mode` (enum: `nominal`/`per_item`/`both`),
  `points_per_amount` (nullable, contoh: 1 poin per Rp 10.000),
  `amount_unit` (nullable, nominal pembagi), `expiry_months` (nullable —
  null berarti tidak pernah expire).
- `loyalty_item_rules` (jika mode per_item/both aktif) — per company:
  `product_id`/`category_id` (sesuaikan dengan model produk yang sudah
  ada), `points_value`.
- `loyalty_ledger` (atau `loyalty_points_transactions`) — riwayat
  penambahan/pengurangan poin per pelanggan (`contact_id`/`customer_id`
  yang sudah ada di skema), `points` (+/-), `source` (order_id atau
  referensi transaksi), `expires_at` (nullable, dihitung dari
  `expiry_months` saat poin diberikan jika company mengaktifkan expiry),
  `company_id` wajib.
- Service kalkulasi poin (mis. `LoyaltyPointsCalculator`) yang membaca
  `loyalty_rules` milik company (bukan hardcode), menghitung poin dari
  order/transaksi, dan mencatat ke `loyalty_ledger`.
- Saldo poin pelanggan = jumlah `loyalty_ledger` yang belum expired
  (query filter `expires_at IS NULL OR expires_at > now()`).

## Test WAJIB (pola sama seperti add-on T-28..T-34 sebelumnya)

1. Company dengan `addon.loyalty=false` → tidak bisa akses fitur loyalty
   sama sekali (403/hidden), tidak ada poin tercatat meski ada transaksi.
2. Company mode `nominal`: transaksi Rp 100.000 dengan rasio 1 poin/Rp
   10.000 → tercatat 10 poin. Ubah rasio company lain jadi 1 poin/Rp
   20.000 → transaksi sama hanya dapat 5 poin (BUKTIKAN rasio benar-benar
   dari data company, bukan angka tetap).
3. Company mode `per_item`: item dengan poin custom tercatat sesuai
   mapping, bukan dihitung dari nominal.
4. Company mode `both`: poin dari kedua sumber terakumulasi benar.
5. **Negative test isolasi tenant**: ledger/rules company A tidak pernah
   terlihat/terhitung untuk company B (query filter `company_id` benar).
6. **Expiry**: company yang mengaktifkan expiry (`expiry_months` diisi)
   — poin lama yang sudah lewat `expires_at` tidak dihitung ke saldo aktif.
   Company yang TIDAK mengaktifkan expiry — poin lama manapun umurnya
   tetap dihitung ke saldo (tidak pernah exclude).

## Verifikasi wajib sebelum lapor selesai

```
php artisan test
vendor/bin/pint --test
npm run build   # jika ada perubahan Blade/CSS/JS
```

Commit lokal per file/tema (bukan `git add -A`), tempel output ASLI di
laporan. Update `docs/AUTOPILOT_STATUS.md` dengan SHA nyata
(`git rev-parse --short HEAD`) — placeholder dilarang. Uang/poin adalah
nilai bernilai ekonomi bagi pelanggan — fail-closed di semua kondisi
tanpa opt-in, dan negative test wajib ada sebelum dianggap selesai.

Push/deploy/migration produksi TETAP butuh izin eksplisit Bos — jangan
diasumsikan dari prompt ini.
