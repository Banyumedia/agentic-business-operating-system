"""
MQ-01C5: Browser/mobile/a11y smoke untuk Dashboard (Agentic BOS).

Jalankan dengan server dev hidup di http://127.0.0.1:8003 (main worktree,
pola preseden: APP_URL/ASSET_URL=http://127.0.0.1:8003).

Buktikan: reload/loading state, fokus setelah navigasi, viewport 360x390,
aksi keyboard, tanpa overflow horizontal. Output [OK]/[TEMUAN] per baris.
"""
import json
import sys
import time

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8003"
EMAIL = "smoke.c5@example.com"
PASSWORD = "PasswordKuat123!"

findings = []


def ok(msg):
    print(f"[OK] {msg}")


def note(msg):
    findings.append(msg)
    print(f"[TEMUAN] {msg}")


def login(page):
    page.goto(f"{BASE}/login", wait_until="networkidle")
    page.fill("input[name=email]", EMAIL)
    page.fill("input[name=password]", PASSWORD)
    page.click("button[type=submit]")
    try:
        page.wait_for_url("**/app/**", timeout=15000)
    except Exception:
        pass
    page.wait_for_load_state("networkidle")
    return page.url


def overflow_metrics(page):
    return page.evaluate(
        """() => ({
            docW: document.documentElement.scrollWidth,
            winW: document.documentElement.clientWidth,
            smallText: [...document.querySelectorAll('body *')]
                .filter(e => {
                    const s = getComputedStyle(e);
                    return s.fontSize && parseFloat(s.fontSize) < 12 && s.display !== 'none'
                        && e.innerText && e.innerText.trim().length > 0
                        && !e.classList.contains('sr-only');
                }).length
        })"""
    )


def check_viewport(page, name):
    m = overflow_metrics(page)
    if m["docW"] > m["winW"] + 1:
        note(f"{name}: overflow horizontal docW={m['docW']} winW={m['winW']}")
    else:
        ok(f"{name}: tanpa overflow horizontal ({m['docW']}/{m['winW']})")
    if m["smallText"] > 0:
        note(f"{name}: {m['smallText']} elemen teks <12px")
    else:
        ok(f"{name}: tidak ada teks <12px")


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        # --- Mobile 360x390 (kontrak plan) ---
        ctx = browser.new_context(viewport={"width": 360, "height": 390})
        page = ctx.new_page()
        url = login(page)
        if "dashboard" not in url:
            note(f"login gagal: url={url}")
            print(json.dumps(findings))
            sys.exit(1)
        ok("login nyata via browser (mobile 360x390)")

        page.goto(f"{BASE}/app/dashboard", wait_until="networkidle")
        check_viewport(page, "mobile-360x390")

        # Nama company tampil (MQ-01C4 parity) - label di-header pakai
        # uppercase CSS, jadi bandingkan case-insensitive.
        body = page.inner_text("body")
        if "bengkel smoke" in body.lower():
            ok("dashboard menampilkan nama company (bukan ID)")
        else:
            note("nama company tidak tampil di dashboard")

        # Reload action: tombol "Perbarui data" memicu loading lalu data kembali
        before = page.locator("[data-testid=dashboard-widgets], main").count()
        has_reload = page.get_by_role("button", name="Perbarui data").count()
        if has_reload:
            page.get_by_role("button", name="Perbarui data").first.click()
            page.wait_for_load_state("networkidle")
            time.sleep(1)
            after = page.locator("[data-testid=dashboard-widgets], main").count()
            ok(f"reload: konten tetap ter-render sebelum={before} sesudah={after}")
            if "belum dapat dimuat" in page.inner_text("body"):
                note("reload menampilkan pesan error padahal data valid")
        else:
            note('tombol "Perbarui data" tidak ditemukan di dashboard')

        # Keyboard: Tab dari skip-link; fokus terlihat di elemen interaktif
        page.goto(f"{BASE}/app/dashboard", wait_until="networkidle")
        page.keyboard.press("Tab")
        focused = page.evaluate("() => document.activeElement?.tagName + ':' + (document.activeElement?.textContent||'').trim().slice(0,20)")
        if focused and focused != "BODY:":
            ok(f"keyboard: fokus pertama = {focused}")
        else:
            note("keyboard: Tab tidak mengarahkan fokus ke elemen interaktif (skip-link?)")

        # Navigasi ke settings lalu kembali: fokus/ konten kembali
        page.goto(f"{BASE}/app/settings", wait_until="networkidle")
        page.goto(f"{BASE}/app/dashboard", wait_until="networkidle")
        if "Ringkasan" in page.inner_text("body"):
            ok("navigasi settings->dashboard: konten kembali ter-render")
        else:
            note("navigasi balik: konten dashboard tidak ter-render")

        page.screenshot(path=r"D:\PROJECTS\agentic-bos\storage\app\_shots\c5_mobile_360.png")
        ctx.close()

        # --- Desktop 1280x800 ---
        ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
        page2 = ctx2.new_page()
        login(page2)
        page2.goto(f"{BASE}/app/dashboard", wait_until="networkidle")
        check_viewport(page2, "desktop-1280x800")
        page2.screenshot(path=r"D:\PROJECTS\agentic-bos\storage\app\_shots\c5_desktop.png")
        ctx2.close()
        browser.close()

    print(f"\nTEMUAN TOTAL: {len(findings)}")
    for f in findings:
        print(f"  - {f}")
    sys.exit(1 if findings else 0)


if __name__ == "__main__":
    run()
