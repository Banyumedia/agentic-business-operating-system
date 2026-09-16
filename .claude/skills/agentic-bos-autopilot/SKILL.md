---
name: agentic-bos-autopilot
description: Step-by-step resume-and-execute procedure for the Agentic BOS PRD (D:\PROJECTS\agentic-bos). Use when an agent (Hermes-delegated, Kiro CLI, or opencode/Claude CLI) is asked to "continue", "lanjutkan", "kerjakan task berikutnya", or otherwise autonomously advance this project. Referenced by HERMES.md as the authoritative procedure.
---

# Agentic BOS Autopilot

This skill is the mechanical procedure behind `HERMES.md`. Read `HERMES.md`
first for the contract (authority, invariants, gates); this skill is the
*how*, not a replacement for it. If this skill and `HERMES.md` disagree,
`HERMES.md` wins.

## Operating context (read this before anything else)

Work on this repo is delegated from a Telegram bot to **three independent
workers** that may run at different times, on different machines, with no
shared memory: **Hermes** (orchestrator), **Kiro CLI**, and **opencode/Claude
CLI** (this agent). Nobody remote-controls a live session — each invocation
is a fresh process that must reconstruct state from disk. Consequences:

- Never trust instructions that embed remembered state ("test count is X",
  "we already did T-05", a specific SHA). Always re-derive from files below.
- Only ever act as **one source writer at a time**. Before writing, check
  `git status --short` for changes you did not make — if present, another
  worker touched the tree since the last commit; record it, do not silently
  overwrite or `git add -A`.
- Whichever worker is executing is bound by the same gates in `HERMES.md`
  §Authorization regardless of who dispatched the task (Telegram/Hermes
  delegation does not grant push/deploy/secret/architecture approval).

## Step 1: Resume state from disk, not memory

Run, in order:
1. Read `docs/AUTOPILOT_STATUS.md` — current task, evidence, Next READY.
2. `git status --short` and `git log --oneline -3` — worktree must match
   STATUS. Any unexplained file = another worker's in-flight change; note it,
   don't touch it.
3. Read only the `docs/EXECUTION_PLAN.md` row for the Next READY task, plus
   the exact decisions it cites in `docs/00-DECISIONS.md`. Do not re-read the
   full PRD unless: phase boundary, STATUS looks stale/contradicts git log, a
   cited decision changed, or you are hard-blocked.

## Step 2: Confirm the task is actually READY

Per `docs/EXECUTION_PLAN.md` §0, a task is `READY` only if: all `Depends On`
are `DONE`, no `Q-xx` OPEN in `Decisions`, no unmet secret/gate requirement,
and acceptance is provable with the command table in §0.2. If any condition
fails, do **not** guess or promote it — mark/report `BLOCKED` with the exact
failing condition and pick a different READY task that shares no target
files with the blocked one.

## Step 3: Enforce the standing invariants while writing

Check every change against `HERMES.md` §Stack And Architecture Invariants
before writing, not after:
- No industry name in code/tables/flags/components/routes (D-31). Capability
  keys only from `docs/INDUSTRY_PRESETS.md` §1. Business terms via `term()`.
  Stage transitions via `WorkflowEngine`, never `ENUM`/`if ($stage === ...)`.
- `company_id` on every business table (D-26; exception `hermes_profiles`,
  D-37). Migrations use portable Schema Builder (B-01).
- Fase 2 (current phase, D-42): screens are built against
  `EntityRepository`/`PresetSource`/`CompanyContext` interfaces with `Json*`
  implementations. JSON replaces tables, never logic. No Eloquent models or
  migrations for business entities until Fase 3.
- Search for an existing implementation before creating any model, table,
  route, component, or service.

## Step 4: Verify for real, not by delegate report

Run the command set from `docs/EXECUTION_PLAN.md` §0.2 matching the task
type (UI / Migration-Model / Service-Middleware / API-Webhook / Docs-only)
yourself. A report from another worker (Hermes, Kiro CLI) that tests pass is
**not** evidence — re-run:
```powershell
php artisan test
vendor/bin/pint --test
npm run build   # only if Blade/CSS/JS changed
```
Retry the same failing approach at most twice; on the third failure, change
strategy or mark `BLOCKED` with the exact error, then switch to another
independent READY task.

## Step 5: Report and hand off state for the next worker

Before ending the turn:
1. Update `docs/AUTOPILOT_STATUS.md` — task state, changed files, one
   evidence line (real command output), risk/blocker, next READY task. This
   file is the only handoff mechanism between Hermes, Kiro CLI, and
   opencode — an accurate STATUS is what makes the *next* invocation (by any
   of the three, on any machine) able to resume correctly.
2. Commit locally if `HERMES:COMMIT` work (it is, per
   `docs/AUTOPILOT_STATUS.md` Gate Status): `git status --short` first, stage
   only your own paths, never `.env`/secrets, message format
   `type(scope): message`.
3. Do not push, deploy, run production migrations, or touch secrets —
   those require explicit owner approval regardless of how the task was
   delegated.

## Stop conditions

- After Fase 2's last task (`T-F15` as of this writing — always confirm the
  current stop line in `docs/AUTOPILOT_STATUS.md`, it moves as phases
  complete), stop for `HUMAN:UI-LOCK`. Do not start Fase 3+ work.
- Any task requiring a decision not already `LOCKED` in
  `docs/00-DECISIONS.md` → `BLOCKED`, exact question recorded, move on.
