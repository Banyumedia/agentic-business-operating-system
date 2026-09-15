# Agentic BOS Autopilot Status

**Updated:** 2026-09-16 05:02 SEAST  
**Mode:** BASELINE VERIFIED — READY FOR FASE 1 COMPLETION  
**Canonical workspace:** `D:\PROJECTS\agentic-bos`

## Verified Baseline

| Item | Value | Evidence |
|---|---|---|
| Laravel | 13.32.0 | `php artisan --version` |
| PHP | 8.3.30 | `php -v` |
| Livewire | 4.4 | `composer.json`, `route:list` livewire endpoints |
| Tailwind | 4.3 (CSS-first `@theme`) | `package.json`, `resources/css/app.css` |
| Test suite | 2 passed, 2 assertions | `php artisan test` |
| Business migrations | none | only `users`, `cache`, `jobs` |
| Business models | none | only `App\Models\User` |
| Git | not initialized | `.git` absent |

## Active Blockers

| ID | Blocker | Impact | Needs |
|---|---|---|---|
| B-01 | `.env` uses SQLite while `DATA_MODEL.md` specifies MySQL DDL (`ENUM`, `AUTO_INCREMENT`) | All Fase 3 migrations fail if written as raw MySQL DDL | Owner decision: MySQL for dev, or portable Laravel Schema Builder migrations |
| B-02 | Workspace is not a Git repository | No `git diff`, no baseline SHA, no commit gate, no worktree parallelism | `git init` + remote, or accept snapshot-based review only |

Fase 1 and Fase 2 UI work is **not** blocked by B-01.

## Operational Status

- `https://hermes.nalar.army/` restored on 2026-09-16 by starting the Hermes
  dashboard on the Caddy-configured upstream `0.0.0.0:9119`.
- Public verification: `/` returns HTTP 302 to `/login?next=%2F`; the sign-in
  page returns HTTP 200 and requires a password.

## Next READY Tasks

1. **T-02 completion** — link each Lobby app card to `route('app.module', ...)`
   instead of `href="#"`, then add a feature test asserting module navigation.
2. **T-03 completion** — move `Sidebar::getMenusProperty()` hardcoded arrays into
   `DynamicMenuRegistry`, gate items by feature flag, and assert disabled modules
   render zero DOM.
3. **Theme tokens** — declare the `--erp-*` token set inside the Tailwind v4
   `@theme` block in `resources/css/app.css` with WCAG AA contrast evidence.

## Standing Execution Rules

- One source writer at a time; no isolated-worktree second writer until B-02 is
  resolved.
- Read-only audits and isolated test shards may run in parallel; SQLite
  `:memory:` is process-isolated, so test shards do not share a database.
- Commit, push, deploy, production migration/data mutation, secrets, paid
  services, and new business decisions remain approval-gated.

## Completion Ledger

| Task | State | Evidence |
|---|---|---|
| PRD path migration Linux -> Windows | COMPLETE | canonical path updated |
| PRD contract reconciliation | COMPLETE | `docs/PRD_RECONCILIATION.md` |
| Dedicated Hermes/Telegram profile | COMPLETE | `agentic-bos-coding-agent`, Telegram connected and paired |
| G-01 baseline discovery | COMPLETE | versions, routes, tests captured above |
| T-01 setup | COMPLETE | Laravel 13.32 + Livewire 4.4 + Tailwind 4.3 |
| T-02 lobby/app switcher | PARTIAL | grid renders, cards still `href="#"` |
| T-03 dynamic sidebar | PARTIAL | menus hardcoded, no registry/feature flags |
| T-04 command palette | COMPLETE (dummy) | Alpine modal + `wire:model.live` verified |
| Fase 3 migrations | BLOCKED | B-01 |
| Restore Hermes dashboard endpoint | COMPLETE | `hermes dashboard --host 0.0.0.0 --port 9119 --no-open`; public 302 to authenticated login, then 200 |

## Correction Log

- 2026-09-16: An earlier reconciliation wrongly forbade Livewire and mandated a
  separate Alpine package. Code inspection proved Livewire v4.4 is installed,
  Fase 1 is built on it, and Alpine ships inside Livewire. That decision was
  reversed; adding a standalone `alpinejs` package is now explicitly forbidden.
