# Agentic BOS Execution Contract

Apply this contract to every request that asks to build, continue, or
autonomously execute the Agentic BOS PRD.

## Sources Of Truth

- Read the full PRD set at the start of a phase or whenever context is stale.
  For a later task in the same verified phase, read only the relevant
  requirement, decision, data/UX contract, and status entry rather than loading
  every document again.
- `docs/00-DECISIONS.md` is authoritative when another PRD document conflicts
  with it. Do not invent a business decision to resolve a conflict.
- The canonical repository is `D:\PROJECTS\agentic-bos`. Before any source
  write, confirm its branch, working directory, worktree state, and whether it
  already contains the required architecture.

## Mandatory Pre-Execution Review

For each implementation wave, first produce an evidence-backed task list:

1. Reconcile the requirement, locked decisions, data model, UX contract, and
   existing code. Record conflicts, unknowns, affected files, dependencies,
   risks, and acceptance criteria.
2. Inspect the git worktree, current branch, recent history, instructions,
   available tests, migrations, routes, and existing equivalent features.
3. Search for existing implementations before creating a new model, table,
   route, API, component, or service.
4. For changes affecting tenant isolation, payment, WhatsApp authorization,
   token balances, financial data, or destructive actions, define negative
   tests and fail-closed behavior before writing code.
5. Do not start a writer while the task has an unresolved business decision,
   unknown target repository, conflicting data contract, required secret, or
   production/financial approval gate.

## Autopilot And Parallelism

- A broad instruction to complete the approved PRD authorizes continuous work
  only after the pre-execution review passes. Continue until the ready queue is
  complete, blocked, or reaches an approval gate.
- Keep exactly one source writer in a shared worktree. The writer owns one
  narrow task with explicit files, invariants, tests, and forbidden scope.
- Parallelize only read-only and non-interfering lanes: PRD/code reconciliation,
  architecture and security review, affected-test discovery, documentation
  review, lint/build checks, and test groups that do not share mutable state.
- Never run parallel migrations, source writers, or dependency changes. Test
  suites may run in parallel only when they do not share mutable state; this
  project's `phpunit.xml` uses SQLite `:memory:`, which is process-isolated, so
  test shards are safe as long as they do not write to the same output paths.
- Verify the stack from `composer.json` and `package.json` before changing UI
  architecture. This project runs Laravel 13 + Livewire v4 + Blade + Tailwind v4
  + Vite. Alpine ships inside Livewire; never add a standalone `alpinejs`
  package, and never replace working Livewire components with another runtime
  without an explicit owner decision.
- **Composable Capability rule (D-31): industry is data, capability is code.**
  Never introduce a feature flag, table, model, Blade component, widget, or
  conditional that names an industry (`agency`, `pharmacy`, `rental`, …).
  Use capability keys from `docs/INDUSTRY_PRESETS.md` §1 only; render business
  terms via `term()`, never literals; drive stage transitions through
  `WorkflowEngine`, never `ENUM` or `if ($stage === …)` chains. Adding an
  industry must be achievable by adding one preset JSON. Adding a capability is
  an owner decision (D-32). If a task seems to require industry-specific code,
  stop and check whether it is really a missing generic capability or a Tier B
  domain rule (D-33) — record which, do not guess.
- Reconcile every parallel result against the current worktree before the next
  writer starts. A delegated task is evidence only after its commands, findings,
  and scope have been inspected.
- At the start of a wave, delegate the PRD traceability, code/test discovery,
  and risk review as separate read-only tasks in one batch. Do not delegate the
  same broad question to several agents. After source mutation, delegate
  disjoint diff, security, and UX/test review lanes against a frozen snapshot.
- Retry a failed approach at most twice. Preserve the exact error and change
  strategy; a third identical attempt is not progress.
- Do not stop after producing a plan when the user authorized implementation.
  If the queue is missing, create it from the reviewed PRD/code evidence, mark
  safe tasks READY, and begin the first one in the same run.
- Continue across task boundaries without asking for routine technical choices.
  Ask only for a true business ambiguity or an approval-gated action. When one
  lane is blocked, continue independent READY work.
- Keep durable progress in `docs/AUTOPILOT_STATUS.md`: current phase/task,
  completed evidence, blockers, and next READY task. Update it at every task
  boundary so a new Telegram turn can resume without relying on chat context.

## Quality Gates

- Implement the smallest coherent slice. Write focused tests before a real
  defect fix; use characterization tests when the behavior already complies.
- Independently review the resulting diff, including untracked files, against
  the task scope, tenant boundaries, authorization, and UX/accessibility
  contracts.
- Run focused tests first, then the relevant integration/build checks. Do not
  call a task complete from static inspection or an agent report alone.
- Do not commit, push, deploy, run production migrations, alter production data,
  or use secrets unless the user explicitly authorizes that action. **Local
  commits are pre-authorized** for this repository (gate `HUMAN:COMMIT` open,
  2026-09-16); before each commit run `git status --short` and confirm no
  `.env`/secret is staged. Push still requires a configured remote plus
  explicit approval.
- Task states (`READY`, `BLOCKED`, `PARTIAL`, `DONE`), human gates, per-task-type
  verification commands, and the failure policy are defined mechanically in
  `docs/EXECUTION_PLAN.md` §0. Evaluate `READY` against that checklist; never
  promote a `BLOCKED` task by guessing an open decision (`Q-xx` in
  `docs/00-DECISIONS.md`). Each `Q-xx` carries a recommended default — apply it
  only after the owner confirms.
- Local source/test/doc edits, local builds, linting, disposable/local database
  migrations, and local browser verification are within the standing autopilot
  mandate once their task passes pre-execution review.
- On failure, preserve the worktree, record the exact blocker and evidence, and
  continue only with independent ready tasks that cannot conflict with it.

## Required Reporting

For each completed wave, report the task IDs, changed files, independent review
result, commands and outcomes, remaining risks, blockers, and the next ready
task. Distinguish focused-task completion from PRD completion, release-ready,
and deployed.
