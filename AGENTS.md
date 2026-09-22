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
  pointing at it. `NoNewNalarPesanCouplingTest` enforces this.
  - **Nalarin is not NalarPesan.** The ban covers NalarPesan (the WhatsApp channel
    product) only.
- **Hermes is the engine; Agentic BOS is the product** (D-68, D-70). The Hermes
  WhatsApp channel is the **sanctioned** path, not something to avoid.
  - Read `docs/HERMES_NODE_CONTRACT.md` before touching any Hermes integration. It
    records verified facts, and it is explicit about what is **not** yet verified.
  - **Correction on record:** an earlier version of this file claimed the local
    Hermes "has no WhatsApp send endpoint". That was wrong. **hermes-webui** has
    none, but the **Hermes agent** ships both a Baileys channel and a
    `whatsapp_cloud` channel. Root cause: `grep_search` does not reach outside the
    workspace and returns "no matches" with no warning. Never conclude "X does not
    exist" about another repo from a grep alone — open the files.
  - What is genuinely missing in Hermes today: an HTTP endpoint to **send** a
    message, to **pair a WA session / fetch a QR**, and to **approve user
    pairing**. `api_server` is an OpenAI-compatible *chat* API (`/v1/chat/completions`,
    `/v1/runs`, `/health`, key `API_SERVER_KEY`, port 8642, per-profile prefix
    `/p/<profile>/`) — it carries no messaging primitives.
  - Tenant WhatsApp delivery goes through `App\Services\HermesNodeClient`, which
    is fail-closed. With no node registered it **refuses to send** — that is
    correct behaviour, not a bug to "fix" by calling some other service.
  - One tenant = one Hermes **profile** = one soul + one WA session + one approved
    user list + one gateway. `profiles/<name>/` is a fully isolated home.
  - Three layers must not be conflated: *device pairing* (which WA account the bot
    runs as; QR; impossible over chat), *user pairing* (who may talk to the bot;
    owned by Hermes per D-70), and *authorization* (what they may do; ours, via
    `AuthenticateTenantBot` + `EnforceBotToolScoping` + `WhatsAppSenderIdentity`).
  - Integration direction: **Hermes is the client, Agentic BOS is the tool
    provider.** `routes/api.php` already exposes MasterBot and TenantBot. No
    inbound webhook is needed for normal tenant-bot operation.
- **White-label is mandatory on customer-facing surfaces** (D-68), including the
  bot's answer to "who are you". Internal dev profiles are exempt. Skills live in
  Hermes but must not hold business rules and must reach data only through the
  TenantBot API (D-69); customer-facing profiles must not have terminal/file/git
  tools, or every authorization guard we have becomes advisory.
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
