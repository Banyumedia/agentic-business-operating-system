# W1 — Paywall "Kuota Habis → Pilih Paket" (D-61)

Worker: W1 (Claude writer) · Branch: `task/pay-w1` · Commit: `3741269`
Status: DONE — all verifications green.

## Files changed

| File | Change |
|---|---|
| `app/Livewire/Paywall.php` | NEW — Livewire page; config-driven free quota (D-60), active plans from `membership_plans` (D-52), theme-safe mount |
| `resources/views/livewire/paywall.blade.php` | NEW — mobile-first, a11y (`role=status`, `aria-labelledby`, `sr-only`, min-h-11), `--erp-*` tokens, empty state hubungi admin |
| `routes/web.php` | `GET /app/paywall` → `app.paywall` inside `EnsureCompanyContext` group |
| `app/Exceptions/Billing/InsufficientTokenQuotaException.php` | `render()`: web → redirect paywall + flash `paywall_reason=token_quota`; API/JSON → 402 `{reason: insufficient_token_quota}`; guest → login |
| `app/Exceptions/Billing/WaGroupQuotaExceededException.php` | Same pattern, `wa_group_quota` / `wa_group_quota_exceeded` |
| `tests/Feature/PaywallTest.php` | NEW — 9 tests, 27 assertions |

## Design notes

- Fail-closed preserved: exception render only changes presentation (redirect/402); the denied action stays denied. API gets 402, never a redirect-masked success.
- No new files in `app/Exceptions/` — used Laravel per-exception `render()` instead of touching `bootstrap/app.php`.
- Paywall mount defensive: company context / settings failure falls back to default theme so page always renders.
- Plans queried only when `Schema::hasTable('membership_plans')` (JSON datasource compat).
- Choose-plan button is a stub (no payment processing in this lane) — noted in UI.

## Verification (real output)

```
DATA_SOURCE=json php artisan test
{"tool":"phpunit","result":"passed","tests":731,"passed":731,"assertions":3846,"duration_ms":46872}

php vendor/laravel/pint/builds/pint --test
{"tool":"pint","result":"passed"}

npm run build
✓ built in 1.55s

git diff --check
(clean; exit 0)
```

`storage/app/json/1/workflow_log.json` polluted by full-suite run → restored via `git checkout`. `package-lock.json` modification pre-existing (other process), left untouched.
