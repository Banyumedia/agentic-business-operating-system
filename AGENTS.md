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
  Bos says otherwise. That means: do not open, run, edit, or read config from its
  repo or services; do not add dependencies, webhooks, crons, or deploy paths
  pointing at it.
  - Tenant WhatsApp delivery goes through `App\Services\HermesNodeClient`, which
    is fail-closed. With no node registered it **refuses to send** — that is
    correct behaviour, not a bug to "fix" by calling some other service.
  - The Hermes running on the development PC is the Hermes **agent** (WebUI on
    port `9119` plus its chat gateway). It has **no WhatsApp send endpoint**; the
    route list was checked. Do not infer otherwise from the word "gateway".
  - The node request shape currently in the code is an **assumption**, recorded in
    `docs/HERMES_NODE_CONTRACT.md`. Aligning it needs a decision from the Bos, not
    a guess.
- Do **not** confuse the two types named `HermesNodeClient`.
  `App\Contracts\HermesNodeClient` is the **platform** sender (subscription
  dunning, D-23/D-49): not company-scoped, and its default implementation only
  writes to the log. Anything sent on behalf of a tenant must use
  `App\Services\HermesNodeClient` (D-63).

## Verify Before Writing

```powershell
php artisan test
vendor/bin/pint --test
npm run build
```

Stack facts are in `composer.json` and `package.json`. Check them before
proposing architectural changes.
