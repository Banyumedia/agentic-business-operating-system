# Agentic BOS Execution Contract

Applies to every request to build, continue, or autonomously execute the PRD.
Authority: `docs/00-DECISIONS.md` > this file > `docs/EXECUTION_PLAN.md` >
other docs. Step-by-step procedure lives in skill `agentic-bos-autopilot`.

## Resume, Then Read Narrowly

1. `docs/AUTOPILOT_STATUS.md` - current task, evidence, next READY.
2. `git status --short`, `git log --oneline -3` - worktree must match STATUS;
   note files you did not create.
3. Only the EXECUTION_PLAN row for the next READY task plus the decisions it
   cites. Full PRD sweep only at a phase boundary, stale state, changed
   decision, or hard blocker.

## Stack And Architecture Invariants

- Laravel 13 + Livewire v4 + Blade + Tailwind v4 + Vite. Alpine ships inside
  Livewire: never add standalone `alpinejs`, Laravel Boost, or any dependency
  unless the task row says so. Never swap the UI runtime.
- **D-31 - industry is data, capability is code.** No flag, table, model,
  Blade component, widget, route, or conditional may name an industry.
  Capability keys only from `docs/INDUSTRY_PRESETS.md` §1; business terms via
  `term()`; stage transitions via `WorkflowEngine`, never `ENUM` or
  `if ($stage === ...)`. A new industry = one preset JSON. A new capability =
  owner decision (D-32). Tier B domain rules per D-33. If a task seems to need
  industry code, stop and record which case it is - do not guess.
- `company_id` on every business table (D-26; exception: `hermes_profiles`
  per D-37). Migrations use portable Schema Builder (B-01).
- Search for an existing implementation before creating any model, table,
  route, component, or service.

## Task States And Gates

Defined mechanically in `docs/EXECUTION_PLAN.md` §0 (`READY`, `BLOCKED`,
`PARTIAL`, `DONE`; gates `HUMAN:*`; verification commands per task type).
Never promote `BLOCKED` by guessing an open decision. There are currently no
open `Q-xx`; if a task needs a decision not in `00-DECISIONS.md`, mark it
`BLOCKED` with the exact question and take another READY task.

**Stop line:** after Fase 2 (`T-07 -> T-F1 ... T-F15`), stop. Fase 3+ is
behind `HUMAN:UI-LOCK`. Fase 2 is frontend-first per D-42: data comes from
JSON behind `EntityRepository`/`PresetSource`/`CompanyContext`; JSON replaces
tables, never logic. No Eloquent models or migrations for business entities in
Fase 2.

## Writing Rules

- One source writer per worktree. Other agents may write here: never
  `git add -A`, never touch/delete/commit foreign files; if a foreign change
  overlaps your scope, record it and switch task.
- Smallest coherent slice; no unrelated refactors. RED test for a proven
  defect; acceptance test for new behavior.
- For tenant isolation, payment, WhatsApp authorization, token balances,
  financial data, or destructive actions: negative tests and fail-closed
  behavior before code.
- Retry a failed approach at most twice; then record error + cause in STATUS
  and switch task.

## Verification And Evidence

Always: `php artisan test`, `vendor/bin/pint --test`; `npm run build` when
Blade/CSS/JS changed. Run them yourself - a delegate report is not a PASS
without real output. Parallel read-only review lanes (diff, tenant/security,
a11y) only for tasks touching schema, authorization, money, public API, or
more than ~8 files. Tests may run in parallel only with isolated SQLite
`:memory:` per process and distinct output paths. Writers, migrations,
dependency changes, commits: serial.

## Authorization

Pre-authorized: local source/test/doc edits, local builds, lint, disposable
DB migrations, read-only subagents, **local commits** (`git status --short`
first; stage own paths; no `.env`/secrets; `type(scope): message`).
Requires explicit owner approval: push, deploy, production migration or data
mutation, secrets, paid services, architecture change outside locked
decisions. Do not ask for routine technical choices decidable from decisions,
framework conventions, or existing code - choose the safest default, note it,
continue.

## Reporting

Update `docs/AUTOPILOT_STATUS.md` at every task boundary (state, changed
files, evidence line, risk, blocker, next READY). Telegram only at boundaries
and blockers: `[T-xx] state | files | test/pint/build | blocker or next`.
Distinguish task complete / phase complete / PRD complete / release-ready /
deployed.
