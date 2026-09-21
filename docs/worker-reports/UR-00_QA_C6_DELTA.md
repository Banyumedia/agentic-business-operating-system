[0m
> plan · rap
[0m
[0m$ [0mgit show --stat b743ed5 | head -60
commit b743ed542e235d92e193e7b48440004600b3c31e
Merge: 697158c 0db1850
Author: masgant99 <masgant99@users.noreply.github.com>
Date:   Mon Sep 21 08:42:19 2026 +0100

    merge: MQ-01C6 remediate QA findings F1-F5

 .../Admin/AdminImpersonationController.php         |   3 +
 app/Http/Middleware/EnsureCompanyAccess.php        |   4 +
 app/Services/Eloquent/EloquentCompanyContext.php   |  12 ++-
 app/Services/Preset/PresetDefinitionValidator.php  |  20 +---
 ...es_at_to_admin_impersonation_sessions_table.php |  24 +++++
 resources/views/layouts/app.blade.php              |  12 +--
 tests/Feature/CompanyDisplayNameParityTest.php     |  19 +++-
 tests/Feature/DataErasureTest.php                  |   2 +-
 .../Feature/ImpersonationExpiryFailClosedTest.php  | 111 +++++++++++++++++++++
 tests/Feature/PresetWidgetCapabilityMatrixTest.php |   2 +-
 tests/Feature/ValidatorWidgetSingleSourceTest.php  |  44 ++++++++
 11 files changed, 221 insertions(+), 32 deletions(-)
[0m
[0m$ [0mgit log --oneline -5 && git status --short | head -20
9e0364b docs(status): MQ-01C6 done - QA findings F1-F5 remediated, MQ-01 fully closed
b743ed5 merge: MQ-01C6 remediate QA findings F1-F5
0db1850 fix(security): QA MQ-01 findings F1-F5 - impersonation expiry fail-closed, validator single-source, displayName fallback, banner contract (MQ-01C6)
697158c docs(status): MQ-01 independent QA verdict LAYAK, findings F1-F5 recorded
750fd31 docs(status): MQ-01C5 done - MQ-01 C1-C5 complete, next QA independen
 M storage/app/json/1/workflow_log.json
?? cache/
[0m
[0m✱ [0mGlob "**/2026_09_21_120000*.php"[90m 1 match[0m
[0m✱ [0mGlob "**/ImpersonationExpiryFailClosedTest.php"[90m 1 match[0m
[0m
[0m$ [0mgit show --stat 0db1850
commit 0db185025b52037692b49656fbfa582a9d9ca8c8
Author: masgant99 <masgant99@users.noreply.github.com>
Date:   Mon Sep 21 08:42:12 2026 +0100

    fix(security): QA MQ-01 findings F1-F5 - impersonation expiry fail-closed, validator single-source, displayName fallback, banner contract (MQ-01C6)

 .../Admin/AdminImpersonationController.php         |   3 +
 app/Http/Middleware/EnsureCompanyAccess.php        |   4 +
 app/Services/Eloquent/EloquentCompanyContext.php   |  12 ++-
 app/Services/Preset/PresetDefinitionValidator.php  |  20 +---
 ...es_at_to_admin_impersonation_sessions_table.php |  24 +++++
 resources/views/layouts/app.blade.php              |  12 +--
 tests/Feature/CompanyDisplayNameParityTest.php     |  19 +++-
 tests/Feature/DataErasureTest.php                  |   2 +-
 .../Feature/ImpersonationExpiryFailClosedTest.php  | 111 +++++++++++++++++++++
 tests/Feature/PresetWidgetCapabilityMatrixTest.php |   2 +-
 tests/Feature/ValidatorWidgetSingleSourceTest.php  |  44 ++++++++
 11 files changed, 221 insertions(+), 32 deletions(-)
[0m
[0m→ [0mRead database/migrations/2026_09_21_120000_add_expires_at_to_admin_impersonation_sessions_table.php
[0m
[0m$ [0mgit show --name-status 0db1850
commit 0db185025b52037692b49656fbfa582a9d9ca8c8
Author: masgant99 <masgant99@users.noreply.github.com>
Date:   Mon Sep 21 08:42:12 2026 +0100

    fix(security): QA MQ-01 findings F1-F5 - impersonation expiry fail-closed, validator single-source, displayName fallback, banner contract (MQ-01C6)

M	app/Http/Controllers/Admin/AdminImpersonationController.php
M	app/Http/Middleware/EnsureCompanyAccess.php
M	app/Services/Eloquent/EloquentCompanyContext.php
M	app/Services/Preset/PresetDefinitionValidator.php
A	database/migrations/2026_09_21_120000_add_expires_at_to_admin_impersonation_sessions_table.php
M	resources/views/layouts/app.blade.php
M	tests/Feature/CompanyDisplayNameParityTest.php
M	tests/Feature/DataErasureTest.php
A	tests/Feature/ImpersonationExpiryFailClosedTest.php
M	tests/Feature/PresetWidgetCapabilityMatrixTest.php
A	tests/Feature/ValidatorWidgetSingleSourceTest.php
[0m
[0m✱ [0mGlob "**/EloquentCompanyContext.php"[90m 1 match[0m
[0m✱ [0mGlob "**/EnsureCompanyAccess.php"[90m 1 match[0m
[0m
[0m$ [0mgit diff-tree --no-commit-id --name-status -r 0db1850
M	app/Http/Controllers/Admin/AdminImpersonationController.php
M	app/Http/Middleware/EnsureCompanyAccess.php
M	app/Services/Eloquent/EloquentCompanyContext.php
M	app/Services/Preset/PresetDefinitionValidator.php
A	database/migrations/2026_09_21_120000_add_expires_at_to_admin_impersonation_sessions_table.php
M	resources/views/layouts/app.blade.php
M	tests/Feature/CompanyDisplayNameParityTest.php
M	tests/Feature/DataErasureTest.php
A	tests/Feature/ImpersonationExpiryFailClosedTest.php
M	tests/Feature/PresetWidgetCapabilityMatrixTest.php
A	tests/Feature/ValidatorWidgetSingleSourceTest.php
[0m
[0m→ [0mRead app/Services/Eloquent/EloquentCompanyContext.php
[0m→ [0mRead app/Http/Middleware/EnsureCompanyAccess.php
[0m→ [0mRead app/Http/Controllers/Admin/AdminImpersonationController.php
[0m✱ [0mGrep "AdminImpersonationSession"[90m in app · 10 matches[0m
[0m→ [0mRead app/Models/Company.php
[0m→ [0mRead app/Models/ModuleSetting.php
[0m→ [0mRead app/Models/AdminImpersonationSession.php
Two more impersonation paths found — Company.php and ModuleSetting.php. Checking expiry handling there:
[0m→ [0mRead app/Services/Preset/PresetDefinitionValidator.php
[0m✱ [0mGrep "WIDGETS"[90m in . · 6 matches[0m
F2 paths covered. Now F1 validator + F3/F4 displayName + banner:
[0m✱ [0mGrep "displayName|impersonat"[90m in resources/views/layouts/app.blade.php · 4 matches[0m
[0m✱ [0mGrep "displayName"[90m in app · 4 matches[0m
F1 done. Now F3/F4 displayName drivers + banner, tests:
[0m→ [0mRead app/Services/Json/JsonCompanyContext.php[90m [offset=50, limit=30][0m
[0m→ [0mRead resources/views/layouts/app.blade.php[90m [offset=10, limit=35][0m
[0m→ [0mRead tests/Feature/CompanyDisplayNameParityTest.php
[0m→ [0mRead app/Services/Json/JsonCompanyContext.php[90m [limit=45, offset=79][0m
[0m→ [0mRead tests/Feature/ImpersonationExpiryFailClosedTest.php
[0m→ [0mRead tests/Feature/ValidatorWidgetSingleSourceTest.php
F3/F4 checked. Now tests + TENANT middleware + MONEY + dashboard.blade.php:
[0m✱ [0mGlob "**/CashFlowCalculator.php"[90m 1 match[0m
[0m✱ [0mGrep "EnsureCompanyAccess|middleware"[90m in app/Providers · 4 matches[0m
[0m
[0m$ [0mgit diff 697158c..b743ed5 -- resources/views/app/dashboard.blade.php resources/views/livewire 2>/dev/null | head -5; git diff --name-only 697158c..b743ed5
app/Http/Controllers/Admin/AdminImpersonationController.php
app/Http/Middleware/EnsureCompanyAccess.php
app/Services/Eloquent/EloquentCompanyContext.php
app/Services/Preset/PresetDefinitionValidator.php
database/migrations/2026_09_21_120000_add_expires_at_to_admin_impersonation_sessions_table.php
resources/views/layouts/app.blade.php
tests/Feature/CompanyDisplayNameParityTest.php
tests/Feature/DataErasureTest.php
tests/Feature/ImpersonationExpiryFailClosedTest.php
tests/Feature/PresetWidgetCapabilityMatrixTest.php
tests/Feature/ValidatorWidgetSingleSourceTest.php
[0m
[0m→ [0mRead app/Providers/AppServiceProvider.php
[0m→ [0mRead app/Services/Dashboard/CashFlowCalculator.php
[0m→ [0mRead app/Services/Dashboard/WidgetCapabilityMap.php
[0m
[0m$ [0mgit diff 697158c..b743ed5 -- tests/Feature/DataErasureTest.php tests/Feature/PresetWidgetCapabilityMatrixTest.php
diff --git a/tests/Feature/DataErasureTest.php b/tests/Feature/DataErasureTest.php
index 8c404ed..f67b1dc 100644
--- a/tests/Feature/DataErasureTest.php
+++ b/tests/Feature/DataErasureTest.php
@@ -206,7 +206,7 @@ public function test_erasure_tab_is_deep_linkable_and_renders_for_owner(): void
         $this->assertStringContainsString('Penghapusan', $response->getContent());
     }
 
-    public function test_erasure_tab_is_absent_from_staff_dom(): void
+    public function test_intruder_cannot_set_foreign_company_context(): void
     {
         $owner = User::factory()->create();
         $company = Company::factory()->create(['owner_user_id' => $owner->id]);
diff --git a/tests/Feature/PresetWidgetCapabilityMatrixTest.php b/tests/Feature/PresetWidgetCapabilityMatrixTest.php
index 9258136..0874fad 100644
--- a/tests/Feature/PresetWidgetCapabilityMatrixTest.php
+++ b/tests/Feature/PresetWidgetCapabilityMatrixTest.php
@@ -86,7 +86,7 @@ public function test_validator_rejects_widget_unknown_to_runtime_contract(): voi
         $definition['dashboard']['industry_zone'][] = ['widget' => 'kpi_revenue'];
 
         $this->expectException(InvalidArgumentException::class);
-        $this->expectExceptionMessage('Widget tidak dikenal kontrak runtime: kpi_revenue');
+        $this->expectExceptionMessage('Widget tidak terdaftar: kpi_revenue');
 
         app(PresetDefinitionValidator::class)->validate($definition);
     }
[0m
[0m$ [0mgit diff --stat 697158c..b743ed5 -- app/Services/Dashboard/CashFlowCalculator.php tests/Feature/CashFlowCalculationTest.php 2>/dev/null; git log --oneline -1 -- app/Services/Dashboard/CashFlowCalculator.php
4a848c2 fix(dashboard): preserve exact cashflow precision
[0m
[0m✱ [0mGlob "tests/**/CashFlow*.php"[90m 0 matches[0m
[0m✱ [0mGrep "CashFlowCalculator"[90m in tests · 3 matches[0m
[0m→ [0mRead tests/Feature/DashboardCashFlowIntegrityTest.php[90m [limit=60, offset=70][0m
[0m✱ [0mGrep "AdminImpersonationSession::(create|factory)"[90m in . · 4 matches[0m
[0m✱ [0mGrep "impersonate|admin\."[90m in routes · 4 matches[0m
[0m✱ [0mGrep "active_company"[90m in app · 13 matches[0m
[0m
[0m$ [0mgit diff 697158c..b743ed5 --unified=20 -- tests/Feature/DataErasureTest.php | head -60
diff --git a/tests/Feature/DataErasureTest.php b/tests/Feature/DataErasureTest.php
index 8c404ed..f67b1dc 100644
--- a/tests/Feature/DataErasureTest.php
+++ b/tests/Feature/DataErasureTest.php
@@ -189,39 +189,39 @@ public function test_non_owner_is_rejected_without_mutation(): void
         $this->assertDatabaseMissing('access_logs', [
             'company_id' => $company->id,
             'action' => 'erasure',
         ]);
     }
 
     public function test_erasure_tab_is_deep_linkable_and_renders_for_owner(): void
     {
         $user = User::factory()->create();
         $company = Company::factory()->create(['owner_user_id' => $user->id]);
 
         $this->actingAs($user);
         app(CompanyContext::class)->setCurrent((string) $company->id);
 
         $response = $this->get('/app/settings/erasure');
         $response->assertOk();
         $this->assertStringContainsString('id="tab-erasure"', $response->getContent());
         $this->assertStringContainsString('Penghapusan', $response->getContent());
     }
 
-    public function test_erasure_tab_is_absent_from_staff_dom(): void
+    public function test_intruder_cannot_set_foreign_company_context(): void
     {
         $owner = User::factory()->create();
         $company = Company::factory()->create(['owner_user_id' => $owner->id]);
 
         // Akses /app milik owner; DOM staf diverifikasi lewat sesi role staff
         // pada datasource JSON (lihat SettingsCapabilityTabsTest) - di sini
         // cukup bukti non-owner ditolak penuh lewat middleware.
         $intruder = User::factory()->create();
         $intruder->update(['current_company_id' => $company->id]);
 
         $this->actingAs($intruder);
         // Fail-closed lebih awal (MQ-01C2): intruder non-owner ditolak di
         // lapisan context sebelum route/komponen tersentuh.
         $this->expectException(LogicException::class);
         $this->expectExceptionMessage('Akses lintas company ditolak.');
         app(CompanyContext::class)->setCurrent((string) $company->id);
     }
 }
[0m
F5 rename check — verify test body matches new name:
[0m→ [0mRead app/Http/Middleware/SetCurrentCompany.php
# QA Review — MQ-01C6 delta @ 9e0364b (READ-ONLY)

## (a) Verdict per question

**1. F2 expiry fail-closed — PASS (2 LOW concerns)**
- `EnsureCompanyAccess.php:51-56`: `whereNotNull('expires_at')->where('expires_at','>',now())` — middleware path fail-closed.
- `EloquentCompanyContext.php:108-114`: identical check in `assertAuthorizedFor()` — Livewire/service path fail-closed.
- Only creator: `AdminImpersonationController.php:17-26` — always sets `expires_at => now()->addHours(2)`. No `AdminImpersonationSession::create` elsewhere in `app/`.
- Migration `2026_09_21_120000`: pure Schema Builder, nullable timestamp + index, `down()` drops index before column. `->after()` ignored on SQLite — portable. PASS.
- Tests: `ImpersonationExpiryFailClosedTest.php:30-110` covers expired (context + HTTP 403) and valid session.
- Concern A (LOW): audit-stamp lookups `Company.php:23` and `ModuleSetting.php:14` query `AdminImpersonationSession` **without** expiry filter. Not an access grant (write access blocked upstream), but an expired/stale session row still stamps `changed_by_type='admin_impersonation'` on model saves — audit-attribution drift only.
- Concern B (LOW): no explicit test for `expires_at = null` row (code covers via `whereNotNull`, untested).

**2. F1 validator single-source — PASS**
- `WIDGETS` const deleted; only remaining mention is a comment (`PresetDefinitionValidator.php:305`). Check now `WidgetCapabilityMap::known()` at `:307`, `required()` at `:310`. `ValidatorWidgetSingleSourceTest.php:15-43` proves all map widgets accepted + unknown rejected. `PresetWidgetCapabilityMatrixTest.php` message updated to match.

**3. F3/F4 displayName + banner — PASS (1 LOW nit)**
- Eloquent fallback: `EloquentCompanyContext.php:27-36` (trim → empty → slug title-case). Parity test: `CompanyDisplayNameParityTest.php:79-96`.
- JSON driver: `JsonCompanyContext.php:85` rejects empty-string name → `:67` slug fallback. Parity holds.
- Banner: `app.blade.php:23` uses contract `displayName()` with try/catch fallback `'Klien'` (`:21-26`), "Akhiri Sesi" form intact.
- Nit (LOW): Eloquent trims whitespace-only names before check; JSON `identityName` checks `$name !== ''` untrimmed — whitespace-only JSON name passes raw. Cosmetic.

**4. TENANT Livewire persistent middleware — PASS**
- `AppServiceProvider.php:50-53`: `Livewire::addPersistentMiddleware([SetCurrentCompany, EnsureCompanyAccess])` intact; registered `EnsureCompanyAccess` is the same updated fail-closed class.

**5. MONEY CashFlowCalculator — PASS**
- Untouched by C6 (diff list confirms; last commit `4a848c2`). Fail-closed preserved: invalid direction `:30-31`, cents regex `:86-99`, aggregate overflow `:105-109`. Covered by `DashboardCashFlowIntegrityTest.php` (invalid direction via composer `:76-78`, DECIMAL(18,2) precision `:80-92`, overflow `:94-100`).

**6. A11Y/MOBILE dashboard.blade.php — PASS**
- `dashboard.blade.php` absent from C6 diff (`git diff 697158c..b743ed5` file list: only `layouts/app.blade.php` among views).

## (b) Findings

| ID | Severity | Finding |
|----|----------|---------|
| QA-C6-1 | LOW | `Company.php:23` / `ModuleSetting.php:14` audit stamps don't filter expired impersonation rows — attribution only, no access impact |
| QA-C6-2 | LOW | No test for `expires_at = null` (fail-closed by `whereNotNull`, untested) |
| QA-C6-3 | LOW | displayName trim-parity: JSON driver doesn't trim whitespace-only names |
| QA-C6-4 | INFO | Expired impersonation leaves `users.current_company_id` set until stop() — admin gets 403 (correct fail-closed) until hitting "Akhiri Sesi" |

## (c) Overall verdict for UR-00 baseline

**LAYAK** — F1-F5 remediations verified correct; both access-granting impersonation paths fail-closed on null + expired `expires_at`; migration portable; all checklist items (TENANT, MONEY, A11Y/MOBILE) intact. All findings LOW/INFO, non-blocking.

Note: tests not executed per read-only constraint (RefreshDatabase would migrate); verdicts are static-analysis based.
