# Runbook & Panduan Setup Hindsight Persistent Memory (T-108)

Status: **PERSIAPAN (ISOLATED DRAFT)**. 
Disiapkan agar tidak bertabrakan (*disjoint*) dengan writer lain yang sedang aktif di `main` (MP-10: Penyesuaian Stok).

---

## 1. Prinsip Utama (Sesuai D-69, Q-16, dan Arahan Bos)

1. **LLM Ditanggung Platform:** Ekstraksi dan sintesis memori percakapan dijalankan lewat API platform (Gemini Flash / OpenAI-compatible), tidak membebani VRAM server lokal.
2. **Isolasi Penuh (Zero-Leakage):** Template wajib menyertakan `{profile}` dan `{user}`:
   ```yaml
   bank_id_template: "hermes-{profile}-{user}"
   ```
   Setiap nomor WhatsApp pelanggan memiliki partisi memori sendiri. Pelanggan A **tidak akan pernah** membaca memori Pelanggan B.
3. **Memori BUKAN Otoritas Angka/Fakta Bisnis:**
   * Hindsight hanya mengingat **preferensi, konteks percakapan, dan kebiasaan obrolan**.
   * Angka stok, saldo uang, dan data transaksi **wajib** selalu dibaca dari API TenantBot Agentic BOS (`T-107`), bukan dari ingatan LLM.
4. **Hak Hapus Data (D-50 / T-27d):**
   * Data memori satu pengguna wajib dapat dihapus bersih jika ada permintaan privasi tanpa memengaruhi pengguna lain.

---

## 2. Struktur Konfigurasi di Profil Hermes

File konfigurasi berada di profil Hermes:
`%LOCALAPPDATA%\hermes\hermes-agent\profiles\<nama_profil>\config.yaml`

### Blok Konfigurasi Hindsight (Mode `local_embedded`):

```yaml
memory:
  provider: "hindsight"
  memory_enabled: true
  user_profile_enabled: true

  hindsight:
    # Mode embedded: PostgreSQL daemon internal Hermes (auto-idle 5 menit)
    mode: "local_embedded"
    
    # KUNCI ISOLASI: Jangan biarkan default 'hermes'!
    bank_id_template: "hermes-{profile}-{user}"
    
    # Ekstraksi otomatis saat sesi WhatsApp berakhir (jeda obrolan)
    extraction:
      enabled: true
      on_session_end: true
      # Model ekstraksi yang ditanggung platform
      model: "gpt-4o-mini" # atau model OpenAI-compatible platform
      api_key_env: "HERMES_MEMORY_EXTRACTION_KEY"
      max_tokens_per_extraction: 1500

    # Retensi & Kurasi
    retention:
      auto_retain: true
      similarity_threshold: 0.75
      max_memories_retrieved: 5
```

---

## 3. Variabel Environment yang Dibutuhkan (`.env` profil)

Di file `.env` pada folder profil Hermes terkait:
```bash
# Kredensial LLM Ekstraksi Platform (Ditanggung Platform)
HERMES_MEMORY_EXTRACTION_KEY="sk-..."
HERMES_MEMORY_EXTRACTION_BASE_URL="https://api.openai.com/v1" # atau endpoint custom platform
```

---

## 4. Rencana Skenario Pengujian (Test Protocol)

Sebelum diterapkan ke seluruh tenant, uji coba dilakukan pada **satu profil dev/internal** dengan protokol berikut:

### Tes 1: Isolasi Antar 2 Nomor WhatsApp
1. **Nomor A (Penguji 1):** Kirim chat: *"Saya alergi kacang dan seafood, tolong catat ya."*
2. Biarkan sesi selesai (tunggu 2–3 menit hingga ekstraksi berjalan).
3. **Nomor B (Penguji 2):** Kirim chat: *"Tolong rekomendasikan menu makan malam untuk saya."*
   * **Kriteria Lulus:** Bot **TIDAK BOLEH** menyebut kacang atau seafood kepada Nomor B.
4. **Nomor A (Penguji 1) Chat Lagi:** *"Menu apa yang cocok buat saya?"*
   * **Kriteria Lulus:** Bot mengingat bahwa Nomor A punya alergi kacang dan seafood.

### Tes 2: Hak Hapus Memori (Privasi)
1. Jalankan pembersihan memori untuk Nomor A:
   ```bash
   # Hapus partisi bank_id milik Nomor A
   DELETE FROM memories WHERE bank_id = 'hermes-dev-6281111111';
   ```
2. Nomor A chat kembali dan bertanya: *"Kamu tahu alergi saya apa?"*
3. **Kriteria Lulus:** Bot menjawab tidak tahu / belum memiliki informasi alergi Nomor A.

---

## 5. Integrasi ke Agentic BOS (Langkah Selanjutnya setelah MP-10 Selesai)

Ketika writer `main` selesai dengan task MP-10, implementasi formal **T-108** akan menyentuh file berikut:
1. `config/hermes.php`: Menambahkan default template `bank_id_template` dan provider settings.
2. `app/Services/Hermes/HermesProfileProvisioner.php`: Menegakkan bahwa setiap pembuatan profil baru otomatis memuat konfigurasi Hindsight terisolasi.
3. `tests/Feature/Hermes/ProfileMemoryIsolationTest.php`: Test otomatis penegakan template isolasi.
