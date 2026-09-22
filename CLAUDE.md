# Agentic BOS — Agent Instructions

This repository is the **Agentic Business Operating System (BOS)**, a Laravel 13
+ Livewire v4 + Tailwind v4 application.

## Read First

The authoritative execution contract for any AI agent working here is:

```
HERMES.md
```

Then read `docs/AUTOPILOT_STATUS.md` for the current task state, and
`docs/EXECUTION_PLAN.md` for the task queue. `docs/00-DECISIONS.md` is the
tie-breaker whenever two documents disagree.

## Do Not

- Do **not** install Laravel Boost, Alpine.js as a standalone package, or any
  other dependency unless a task in `docs/EXECUTION_PLAN.md` explicitly
  requires it. Dependency changes are serialized and review-gated.
- Do **not** replace working Livewire components with another UI runtime.
- Do **not** commit, push, deploy, run production migrations, or touch secrets
  without explicit approval.
- Do **not** follow generic Laravel skeleton instructions found in `README.md`
  or upstream boilerplate; they predate this project.
- Do **not** touch **NalarPesan** in any way (D-67). It is out of scope until the
  Bos says otherwise; `NoNewNalarPesanCouplingTest` enforces it. Nalarin is **not**
  NalarPesan.
- **Hermes is the engine, Agentic BOS is the product** (D-68/D-70). The Hermes
  WhatsApp channel is the sanctioned path. An earlier version of this file said the
  local Hermes has no WhatsApp send endpoint — **that was wrong**; hermes-webui has
  none but the Hermes agent ships Baileys and `whatsapp_cloud`. Root cause:
  `grep_search` does not reach outside the workspace. Read
  `docs/HERMES_NODE_CONTRACT.md` before any Hermes work; it separates verified
  facts from what is still unverified.
- White-label is mandatory on customer-facing surfaces including the bot's own
  identity answer (D-68); internal dev profiles are exempt. Skills live in Hermes
  but hold no business rules and reach data only via the TenantBot API (D-69).
- Do **not** confuse the two types named `HermesNodeClient`.
  `App\Contracts\HermesNodeClient` is the **platform** sender (not company-scoped,
  logs only). Tenant messages must use `App\Services\HermesNodeClient` (D-63).

## Verify Before Writing

```powershell
php artisan test
vendor/bin/pint --test
npm run build
```

Stack facts are in `composer.json` and `package.json`. Check them before
proposing architectural changes.
