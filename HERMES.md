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

- One source writer per worktree at any given moment. Never `git add -A`,
  never touch/delete/commit foreign files; if a foreign change overlaps your
  scope, record it and switch task.
- Smallest coherent slice; no unrelated refactors. RED test for a proven
  defect; acceptance test for new behavior.
- For tenant isolation, payment, WhatsApp authorization, token balances,
  financial data, or destructive actions: negative tests and fail-closed
  behavior before code.
- Retry a failed approach at most twice; then record error + cause in STATUS
  and switch task.

## Parallel Writer Policy

Parallel writing across worktrees (`agentic-bos-worker-a/b/c` + `main`) is
**conditionally permitted**, not banned and not free-for-all. A task may be
worked in parallel with others only if **all six** hold; otherwise it is
serial:

1. Every task in its `Depends On` column is `DONE` (not "in progress").
2. No open `Q-xx` and no unmet `HUMAN:*` gate blocks it.
3. It creates or edits **no migration**. Migrations are always serial.
4. It changes **no dependency** (`composer.json`/`package.json`). Dependency
   changes are always serial.
5. Its file targets are **disjoint** from every task currently in flight in
   any worktree. If two soon-to-be-parallel tasks would edit the same file,
   that shared edit must land first as its own serial prep commit - only
   then may the group run in parallel.
6. It runs in its **own worktree** on its **own branch** (see Claim
   Mechanism), never directly on `main` while other parallel tasks are live.

**Claim mechanism:** claiming a task means creating branch `task/{TASK-ID}`
in your own worktree. `git worktree list` is the live claim registry - Git
itself refuses to let two worktrees check out the same branch, so claim
collisions are prevented mechanically, not by convention. Do not claim a
task by writing your name into a status file.

**Shared-file discipline while parallel:** workers must **not** write
`docs/AUTOPILOT_STATUS.md` while other parallel workers are active - that
file is a merge-conflict magnet. Instead write evidence to
`docs/worker-reports/{TASK-ID}.md`. Only the writer merging into `main`
folds those reports into `AUTOPILOT_STATUS.md`.

**Merge protocol:** merges into `main` are always serial, one branch at a
time, full `php artisan test` + `vendor/bin/pint --test` after each merge
before starting the next. A failing merge is fixed on the worker's own
branch, never patched directly on `main`.

**Always serial regardless of the six-point test:** migrations, dependency
changes, commits/merges into `main`, and any task marked as a convergence
point in `docs/EXECUTION_PLAN.md` (e.g. an anti-hardcode/regression sweep
that depends on several prior tasks, or a full-regression gate).

See `docs/EXECUTION_PLAN.md` §Matriks Grup Paralel for the current phase's
precomputed parallel groups and barriers - do not re-derive the dependency
graph from memory each session.

## Verification And Evidence

Always: `php artisan test`, `vendor/bin/pint --test`; `npm run build` when
Blade/CSS/JS changed. Run them yourself - a delegate report is not a PASS
without real output. Parallel read-only review lanes (diff, tenant/security,
a11y) only for tasks touching schema, authorization, money, public API, or
more than ~8 files. Tests may run in parallel only with isolated SQLite
`:memory:` per process and distinct output paths.

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
