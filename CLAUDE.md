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
  Bos says otherwise. See `AGENTS.md` §Do Not and `docs/HERMES_NODE_CONTRACT.md`
  for the full rule, including why the Hermes agent on port `9119` is **not** a
  WhatsApp gateway and why a refused send is correct behaviour.
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
