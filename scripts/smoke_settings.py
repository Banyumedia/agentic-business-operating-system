"""
Smoke test untuk halaman Settings (/app/settings) via Playwright.

Kredensial WAJIB datang dari environment dan tidak punya nilai bawaan. Versi
pertama skrip ini menuliskan email pilot beserta password apa adanya, dan
menyasar port 8010 yang di mesin ini dipakai server produksi - kredensial di
dalam repo tidak boleh jadi kebiasaan, sekecil apa pun lingkupnya.

    $env:SMOKE_BASE_URL = "http://127.0.0.1:8005"
    $env:SMOKE_EMAIL    = "..."
    $env:SMOKE_PASSWORD = "..."
    python scripts/smoke_settings.py
"""
import os
import sys
from playwright.sync_api import sync_playwright

BASE_URL = os.environ.get("SMOKE_BASE_URL", "http://127.0.0.1:8005")
EMAIL = os.environ.get("SMOKE_EMAIL")
PASSWORD = os.environ.get("SMOKE_PASSWORD")

def run_smoke():
    if not EMAIL or not PASSWORD:
        print("[!] SMOKE_EMAIL dan SMOKE_PASSWORD wajib diisi lewat environment.")
        sys.exit(2)

    print(f"[*] Menghubungkan ke {BASE_URL}...")
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1280, "height": 800})
        page = context.new_page()

        # 1. Login
        print("[*] Melakukan login sebagai pilot...")
        page.goto(f"{BASE_URL}/login")
        page.fill('input[name="email"]', EMAIL)
        page.fill('input[name="password"]', PASSWORD)
        page.click('button[type="submit"]')
        page.wait_for_load_state("networkidle")

        print(f"[*] URL setelah login: {page.url}")

        # 2. Akses halaman Settings
        print("[*] Membuka halaman /app/settings...")
        page.goto(f"{BASE_URL}/app/settings")
        page.wait_for_load_state("networkidle")

        title = page.title()
        print(f"[*] Page title: {title}")

        # Cek tablist
        tabs = page.query_selector_all('button[role="tab"]')
        tab_names = [t.inner_text().strip() for t in tabs]
        print(f"[*] Tab terdeteksi ({len(tabs)}): {', '.join(tab_names)}")

        # 3. Verifikasi Tab Tema
        print("[*] Memeriksa Tab Tampilan & Tema...")
        page.click('#tab-theme')
        page.wait_for_timeout(500)
        theme_cards = page.query_selector_all('button[wire\\:click^="selectTheme"]')
        print(f"[*] Jumlah pilihan tema kartu: {len(theme_cards)}")

        # 4. Verifikasi Tab Fitur Bisnis
        print("[*] Memeriksa Tab Fitur Bisnis...")
        page.click('#tab-features')
        page.wait_for_timeout(500)
        has_features = page.query_selector('select[wire\\:model\\.live="selectedPreset"]') is not None
        print(f"[*] Form pemilihan preset aktif: {has_features}")

        # 5. Verifikasi Tab Karyawan AI
        print("[*] Memeriksa Tab Karyawan AI...")
        page.click('#tab-assistant')
        page.wait_for_timeout(500)
        assistant_content = page.inner_text('#panel-assistant')
        print(f"[*] Isi panel asisten AI: {assistant_content[:80]}...")

        # 6. Verifikasi Tab Penggunaan & Paket
        print("[*] Memeriksa Tab Penggunaan & Paket...")
        page.click('#tab-usage')
        page.wait_for_timeout(500)
        has_usage = page.query_selector('#panel-usage') is not None
        print(f"[*] Panel kuota penggunaan aktif: {has_usage}")

        # 7. Cek apakah ada error visual / banner merah
        has_error = page.query_selector('.border-red-500, .bg-red-100, [role="alert"]')
        if has_error:
            print(f"[!] Warning: Ada elemen alert/error: {has_error.inner_text()}")
        else:
            print("[+] Smoke test Settings UI: SEMUA BERSIH / TANPA ERROR!")

        browser.close()

if __name__ == "__main__":
    run_smoke()
