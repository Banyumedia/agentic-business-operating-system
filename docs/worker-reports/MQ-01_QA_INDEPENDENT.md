[0m
> plan · rap
[0m
[0m→ [0mRead app/Services/Dashboard/CashFlowCalculator.php
[0m
[0m$ [0mgit log --oneline d0cc3fd..750fd31
750fd31 docs(status): MQ-01C5 done - MQ-01 C1-C5 complete, next QA independen
f2089f6 merge: MQ-01C5 browser/mobile/a11y smoke
c79b50b test(tooling): browser/mobile/a11y dashboard smoke via Playwright (MQ-01C5)
447e0a3 docs(status): MQ-01C4 done, next MQ-01C5 + QA independen
ea90b6c merge: MQ-01C4 company displayName parity
a7b66c8 feat(dashboard): company displayName contract parity JSON/Eloquent (MQ-01C4)
ba8a648 docs(status): MQ-01C3 done, next MQ-01C4
70d23e3 merge: MQ-01C3 single widget-capability contract + preset data fixes
5249e32 feat(dashboard): single widget-capability contract, fail-closed preset validation (MQ-01C3)
98d7080 docs(status): MQ-01C2 done, next MQ-01C3
4f7565b merge: MQ-01C2 fail-closed tenant context + persistent Livewire middleware
6bb172b fix(tenant): fail-closed company context + persistent Livewire middleware (MQ-01C2)
7ea1cb9 docs(status): record dashboard precision hardening
833240b merge: harden dashboard cashflow precision
4a848c2 fix(dashboard): preserve exact cashflow precision
8ee79a7 docs(status): MQ-01C1 done, next MQ-01C2
56b623d merge: MQ-01C1 dashboard cash integrity + theme error state
44416aa fix(dashboard): controlled error state for theme read failure + negative tests for cash entry integrity (MQ-01C1)
a87bb0c docs(status): record a11y mobile smoke + tap target fix evidence
da1d385 fix(a11y): 44px tap targets for skip-link and sidebar brand link (mobile smoke findings)
5d5c18f docs: draft Fase 7 WA-First Business Assistant (D-60)
6306e00 docs(qa): split dashboard hardening into serial slices
9076af8 docs(qa): plan dashboard quality slices
[0m
[0m→ [0mRead app/Services/Dashboard/DashboardComposer.php
[0m→ [0mRead app/Services/Eloquent/EloquentCompanyContext.php
[0m→ [0mRead app/Services/Dashboard/WidgetCapabilityMap.php
[0m→ [0mRead app/Services/Dashboard/WidgetRegistry.php
[0m→ [0mRead app/Services/Preset/PresetDefinitionValidator.php
[0m→ [0mRead app/Services/Json/JsonCompanyContext.php
[0m→ [0mRead app/Providers/AppServiceProvider.php
[0m→ [0mRead app/Livewire/Dashboard.php
[0m
[0m$ [0mgit show 5249e32 --stat && git show 5249e32 -- database/presets | head -300
commit 5249e323d4d123191429357e00dabbd140bf5c7d
Author: masgant99 <masgant99@users.noreply.github.com>
Date:   Mon Sep 21 07:25:42 2026 +0100

    feat(dashboard): single widget-capability contract, fail-closed preset validation (MQ-01C3)

 app/Services/Dashboard/DashboardComposer.php       | 12 ++-
 app/Services/Dashboard/WidgetCapabilityMap.php     | 43 ++++++++++
 app/Services/Dashboard/WidgetRegistry.php          | 13 +--
 app/Services/Preset/PresetDefinitionValidator.php  | 27 +++++--
 database/presets/agency.json                       |  9 +--
 database/presets/bakery_preorder.json              |  7 +-
 database/presets/barbershop.json                   |  4 +-
 database/presets/contractor.json                   | 10 +--
 database/presets/cuci_mobil.json                   |  7 +-
 database/presets/custom.json                       |  5 +-
 database/presets/eo.json                           | 10 +--
 database/presets/fnb.json                          |  7 +-
 database/presets/fotografi.json                    |  5 +-
 database/presets/gym.json                          |  4 +-
 database/presets/katering.json                     |  6 +-
 database/presets/kedai_kopi.json                   |  5 +-
 database/presets/kos_coworking.json                |  6 +-
 database/presets/kursus.json                       |  4 +-
 database/presets/pharmacy.json                     | 10 +--
 database/presets/rental.json                       |  9 +--
 tests/Feature/PresetWidgetCapabilityMatrixTest.php | 93 ++++++++++++++++++++++
 21 files changed, 209 insertions(+), 87 deletions(-)
commit 5249e323d4d123191429357e00dabbd140bf5c7d
Author: masgant99 <masgant99@users.noreply.github.com>
Date:   Mon Sep 21 07:25:42 2026 +0100

    feat(dashboard): single widget-capability contract, fail-closed preset validation (MQ-01C3)

diff --git a/database/presets/agency.json b/database/presets/agency.json
index f4f6d11..26407ea 100644
--- a/database/presets/agency.json
+++ b/database/presets/agency.json
@@ -119,13 +119,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "timesheet_summary"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -139,4 +136,4 @@
         ]
     },
     "description": "Solusi operasional untuk agensi kreatif dengan manajemen proyek, penagihan berbasis progres, dan timesheet terintegrasi."
-}
\ No newline at end of file
+}
diff --git a/database/presets/bakery_preorder.json b/database/presets/bakery_preorder.json
index f572a0d..0981cc2 100644
--- a/database/presets/bakery_preorder.json
+++ b/database/presets/bakery_preorder.json
@@ -86,14 +86,11 @@
     },
     "dashboard": {
         "industry_zone": [
-            {
-                "widget": "upcoming_schedule"
-            },
             {
                 "widget": "low_stock"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -109,4 +106,4 @@
         ]
     },
     "description": "Sistem manajemen pre-order kue dan roti, dengan pelacakan jadwal produksi dan serah terima."
-}
\ No newline at end of file
+}
diff --git a/database/presets/barbershop.json b/database/presets/barbershop.json
index 55bdd86..30871a6 100644
--- a/database/presets/barbershop.json
+++ b/database/presets/barbershop.json
@@ -83,7 +83,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_revenue"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
@@ -103,4 +103,4 @@
         ]
     },
     "description": "Sistem kasir dan antrean potong rambut terintegrasi untuk pangkas rambut, barbershop, dan salon pria."
-}
\ No newline at end of file
+}
diff --git a/database/presets/contractor.json b/database/presets/contractor.json
index 06950e5..acc5d97 100644
--- a/database/presets/contractor.json
+++ b/database/presets/contractor.json
@@ -121,16 +121,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "retention_held"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "pending_approvals"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -144,4 +138,4 @@
         ]
     },
     "description": "Sistem manajemen untuk kontraktor, pantau RAB, termin pembayaran, dan log material dalam satu platform."
-}
\ No newline at end of file
+}
diff --git a/database/presets/cuci_mobil.json b/database/presets/cuci_mobil.json
index d7e02c8..6be9a58 100644
--- a/database/presets/cuci_mobil.json
+++ b/database/presets/cuci_mobil.json
@@ -70,7 +70,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
+            },
+            {
+                "widget": "low_stock"
             }
         ]
     },
@@ -87,4 +90,4 @@
         ]
     },
     "description": "Sistem antrean cepat, tiket masuk, dan manajemen kas untuk cuci mobil dan motor."
-}
\ No newline at end of file
+}
diff --git a/database/presets/custom.json b/database/presets/custom.json
index 007a442..a600a0d 100644
--- a/database/presets/custom.json
+++ b/database/presets/custom.json
@@ -69,6 +69,9 @@
         "industry_zone": [
             {
                 "widget": "kpi_cashflow"
+            },
+            {
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -81,4 +84,4 @@
         ]
     },
     "description": "Bebas kustomisasi kapabilitas, terminologi, dan alur kerja sesuai dengan model bisnis spesifik Anda."
-}
\ No newline at end of file
+}
diff --git a/database/presets/eo.json b/database/presets/eo.json
index 00c8c69..df71ff2 100644
--- a/database/presets/eo.json
+++ b/database/presets/eo.json
@@ -98,16 +98,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "upcoming_schedule"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "pending_approvals"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -122,4 +116,4 @@
         ]
     },
     "description": "Manajemen acara dan event organizer. Kelola vendor, tiket, dan penagihan klien dari tahap awal hingga acara selesai."
-}
\ No newline at end of file
+}
diff --git a/database/presets/fnb.json b/database/presets/fnb.json
index 5273ed4..9b064fd 100644
--- a/database/presets/fnb.json
+++ b/database/presets/fnb.json
@@ -89,13 +89,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "kpi_revenue"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -112,4 +109,4 @@
         ]
     },
     "description": "Solusi kasir (POS) restoran dan kafe, dengan manajemen meja, cetak dapur, dan rekap shift kasir."
-}
\ No newline at end of file
+}
diff --git a/database/presets/fotografi.json b/database/presets/fotografi.json
index 07eeb96..ed8eaca 100644
--- a/database/presets/fotografi.json
+++ b/database/presets/fotografi.json
@@ -185,9 +185,6 @@
             {
                 "widget": "upcoming_schedule"
             },
-            {
-                "widget": "projects_progress"
-            },
             {
                 "widget": "kpi_cashflow"
             }
@@ -205,4 +202,4 @@
         ]
     },
     "description": "Sistem pemesanan sesi foto, penagihan deposit, dan pelacakan jadwal untuk studio foto maupun fotografer lepas."
-}
\ No newline at end of file
+}
diff --git a/database/presets/gym.json b/database/presets/gym.json
index a1c28ca..b8fbcaf 100644
--- a/database/presets/gym.json
+++ b/database/presets/gym.json
@@ -74,7 +74,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -90,4 +90,4 @@
         ]
     },
     "description": "Manajemen keanggotaan (membership), kelas terjadwal, dan penagihan bulanan otomatis untuk pusat kebugaran."
-}
\ No newline at end of file
+}
diff --git a/database/presets/katering.json b/database/presets/katering.json
index 285a316..8f578c7 100644
--- a/database/presets/katering.json
+++ b/database/presets/katering.json
@@ -87,10 +87,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "upcoming_schedule"
+                "widget": "pending_approvals"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "low_stock"
             }
         ]
     },
@@ -106,4 +106,4 @@
         ]
     },
     "description": "Sistem katering harian dan acara khusus, kelola pesanan, jadwal produksi, dan pengiriman dengan akurat."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kedai_kopi.json b/database/presets/kedai_kopi.json
index e0584d0..b7782e2 100644
--- a/database/presets/kedai_kopi.json
+++ b/database/presets/kedai_kopi.json
@@ -92,9 +92,6 @@
     },
     "dashboard": {
         "industry_zone": [
-            {
-                "widget": "kpi_revenue"
-            },
             {
                 "widget": "kpi_cashflow"
             },
@@ -116,4 +113,4 @@
         ]
     },
     "description": "Solusi kasir cepat untuk kedai kopi (coffee shop)."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kos_coworking.json b/database/presets/kos_coworking.json
index fdbd533..2d9cb19 100644
--- a/database/presets/kos_coworking.json
+++ b/database/presets/kos_coworking.json
@@ -74,10 +74,10 @@
     "dashboard": {
[0m
[0m$ [0mgit show 5249e32 --stat --format= | tail -25 && git show 5249e32 -- 'database/presets/*' | head -500
app/Services/Dashboard/DashboardComposer.php       | 12 ++-
 app/Services/Dashboard/WidgetCapabilityMap.php     | 43 ++++++++++
 app/Services/Dashboard/WidgetRegistry.php          | 13 +--
 app/Services/Preset/PresetDefinitionValidator.php  | 27 +++++--
 database/presets/agency.json                       |  9 +--
 database/presets/bakery_preorder.json              |  7 +-
 database/presets/barbershop.json                   |  4 +-
 database/presets/contractor.json                   | 10 +--
 database/presets/cuci_mobil.json                   |  7 +-
 database/presets/custom.json                       |  5 +-
 database/presets/eo.json                           | 10 +--
 database/presets/fnb.json                          |  7 +-
 database/presets/fotografi.json                    |  5 +-
 database/presets/gym.json                          |  4 +-
 database/presets/katering.json                     |  6 +-
 database/presets/kedai_kopi.json                   |  5 +-
 database/presets/kos_coworking.json                |  6 +-
 database/presets/kursus.json                       |  4 +-
 database/presets/pharmacy.json                     | 10 +--
 database/presets/rental.json                       |  9 +--
 tests/Feature/PresetWidgetCapabilityMatrixTest.php | 93 ++++++++++++++++++++++
 21 files changed, 209 insertions(+), 87 deletions(-)
commit 5249e323d4d123191429357e00dabbd140bf5c7d
Author: masgant99 <masgant99@users.noreply.github.com>
Date:   Mon Sep 21 07:25:42 2026 +0100

    feat(dashboard): single widget-capability contract, fail-closed preset validation (MQ-01C3)

diff --git a/database/presets/agency.json b/database/presets/agency.json
index f4f6d11..26407ea 100644
--- a/database/presets/agency.json
+++ b/database/presets/agency.json
@@ -119,13 +119,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "timesheet_summary"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -139,4 +136,4 @@
         ]
     },
     "description": "Solusi operasional untuk agensi kreatif dengan manajemen proyek, penagihan berbasis progres, dan timesheet terintegrasi."
-}
\ No newline at end of file
+}
diff --git a/database/presets/bakery_preorder.json b/database/presets/bakery_preorder.json
index f572a0d..0981cc2 100644
--- a/database/presets/bakery_preorder.json
+++ b/database/presets/bakery_preorder.json
@@ -86,14 +86,11 @@
     },
     "dashboard": {
         "industry_zone": [
-            {
-                "widget": "upcoming_schedule"
-            },
             {
                 "widget": "low_stock"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -109,4 +106,4 @@
         ]
     },
     "description": "Sistem manajemen pre-order kue dan roti, dengan pelacakan jadwal produksi dan serah terima."
-}
\ No newline at end of file
+}
diff --git a/database/presets/barbershop.json b/database/presets/barbershop.json
index 55bdd86..30871a6 100644
--- a/database/presets/barbershop.json
+++ b/database/presets/barbershop.json
@@ -83,7 +83,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_revenue"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
@@ -103,4 +103,4 @@
         ]
     },
     "description": "Sistem kasir dan antrean potong rambut terintegrasi untuk pangkas rambut, barbershop, dan salon pria."
-}
\ No newline at end of file
+}
diff --git a/database/presets/contractor.json b/database/presets/contractor.json
index 06950e5..acc5d97 100644
--- a/database/presets/contractor.json
+++ b/database/presets/contractor.json
@@ -121,16 +121,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "retention_held"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "pending_approvals"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -144,4 +138,4 @@
         ]
     },
     "description": "Sistem manajemen untuk kontraktor, pantau RAB, termin pembayaran, dan log material dalam satu platform."
-}
\ No newline at end of file
+}
diff --git a/database/presets/cuci_mobil.json b/database/presets/cuci_mobil.json
index d7e02c8..6be9a58 100644
--- a/database/presets/cuci_mobil.json
+++ b/database/presets/cuci_mobil.json
@@ -70,7 +70,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
+            },
+            {
+                "widget": "low_stock"
             }
         ]
     },
@@ -87,4 +90,4 @@
         ]
     },
     "description": "Sistem antrean cepat, tiket masuk, dan manajemen kas untuk cuci mobil dan motor."
-}
\ No newline at end of file
+}
diff --git a/database/presets/custom.json b/database/presets/custom.json
index 007a442..a600a0d 100644
--- a/database/presets/custom.json
+++ b/database/presets/custom.json
@@ -69,6 +69,9 @@
         "industry_zone": [
             {
                 "widget": "kpi_cashflow"
+            },
+            {
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -81,4 +84,4 @@
         ]
     },
     "description": "Bebas kustomisasi kapabilitas, terminologi, dan alur kerja sesuai dengan model bisnis spesifik Anda."
-}
\ No newline at end of file
+}
diff --git a/database/presets/eo.json b/database/presets/eo.json
index 00c8c69..df71ff2 100644
--- a/database/presets/eo.json
+++ b/database/presets/eo.json
@@ -98,16 +98,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "upcoming_schedule"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "pending_approvals"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -122,4 +116,4 @@
         ]
     },
     "description": "Manajemen acara dan event organizer. Kelola vendor, tiket, dan penagihan klien dari tahap awal hingga acara selesai."
-}
\ No newline at end of file
+}
diff --git a/database/presets/fnb.json b/database/presets/fnb.json
index 5273ed4..9b064fd 100644
--- a/database/presets/fnb.json
+++ b/database/presets/fnb.json
@@ -89,13 +89,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "kpi_revenue"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -112,4 +109,4 @@
         ]
     },
     "description": "Solusi kasir (POS) restoran dan kafe, dengan manajemen meja, cetak dapur, dan rekap shift kasir."
-}
\ No newline at end of file
+}
diff --git a/database/presets/fotografi.json b/database/presets/fotografi.json
index 07eeb96..ed8eaca 100644
--- a/database/presets/fotografi.json
+++ b/database/presets/fotografi.json
@@ -185,9 +185,6 @@
             {
                 "widget": "upcoming_schedule"
             },
-            {
-                "widget": "projects_progress"
-            },
             {
                 "widget": "kpi_cashflow"
             }
@@ -205,4 +202,4 @@
         ]
     },
     "description": "Sistem pemesanan sesi foto, penagihan deposit, dan pelacakan jadwal untuk studio foto maupun fotografer lepas."
-}
\ No newline at end of file
+}
diff --git a/database/presets/gym.json b/database/presets/gym.json
index a1c28ca..b8fbcaf 100644
--- a/database/presets/gym.json
+++ b/database/presets/gym.json
@@ -74,7 +74,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -90,4 +90,4 @@
         ]
     },
     "description": "Manajemen keanggotaan (membership), kelas terjadwal, dan penagihan bulanan otomatis untuk pusat kebugaran."
-}
\ No newline at end of file
+}
diff --git a/database/presets/katering.json b/database/presets/katering.json
index 285a316..8f578c7 100644
--- a/database/presets/katering.json
+++ b/database/presets/katering.json
@@ -87,10 +87,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "upcoming_schedule"
+                "widget": "pending_approvals"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "low_stock"
             }
         ]
     },
@@ -106,4 +106,4 @@
         ]
     },
     "description": "Sistem katering harian dan acara khusus, kelola pesanan, jadwal produksi, dan pengiriman dengan akurat."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kedai_kopi.json b/database/presets/kedai_kopi.json
index e0584d0..b7782e2 100644
--- a/database/presets/kedai_kopi.json
+++ b/database/presets/kedai_kopi.json
@@ -92,9 +92,6 @@
     },
     "dashboard": {
         "industry_zone": [
-            {
-                "widget": "kpi_revenue"
-            },
             {
                 "widget": "kpi_cashflow"
             },
@@ -116,4 +113,4 @@
         ]
     },
     "description": "Solusi kasir cepat untuk kedai kopi (coffee shop)."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kos_coworking.json b/database/presets/kos_coworking.json
index fdbd533..2d9cb19 100644
--- a/database/presets/kos_coworking.json
+++ b/database/presets/kos_coworking.json
@@ -74,10 +74,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "upcoming_schedule"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -91,4 +91,4 @@
         ]
     },
     "description": "Manajemen tagihan bulanan dan penyewaan ruang fleksibel untuk kos, apartemen, dan coworking space."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kursus.json b/database/presets/kursus.json
index eb0d602..1a10f92 100644
--- a/database/presets/kursus.json
+++ b/database/presets/kursus.json
@@ -77,7 +77,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -92,4 +92,4 @@
         ]
     },
     "description": "Manajemen kelas, tagihan SPP, dan daftar hadir untuk bimbingan belajar dan tempat kursus."
-}
\ No newline at end of file
+}
diff --git a/database/presets/pharmacy.json b/database/presets/pharmacy.json
index 347a517..22698e2 100644
--- a/database/presets/pharmacy.json
+++ b/database/presets/pharmacy.json
@@ -96,16 +96,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "prescription_queue"
-            },
-            {
-                "widget": "expiring_batches"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
-            },
-            {
-                "widget": "kpi_revenue"
             }
         ]
     },
@@ -121,4 +115,4 @@
         ]
     },
     "description": "Solusi apotek dengan manajemen stok obat, resep, dan kontrol tanggal kedaluwarsa."
-}
\ No newline at end of file
+}
diff --git a/database/presets/rental.json b/database/presets/rental.json
index 4a96d1c..ec7eb18 100644
--- a/database/presets/rental.json
+++ b/database/presets/rental.json
@@ -108,13 +108,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "bookings_due_today"
-            },
-            {
-                "widget": "overdue_returns"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -129,4 +126,4 @@
         ]
     },
     "description": "Sistem penyewaan barang dan kendaraan, hitung durasi, denda keterlambatan, dan pemeliharaan aset."
-}
\ No newline at end of file
+}
[0m
[0m$ [0mgit --no-pager show 5249e32 --format= --unified=3 -- database/presets/agency.json database/presets/bakery_preorder.json database/presets/barbershop.json database/presets/contractor.json database/presets/cuci_mobil.json database/presets/custom.json database/presets/eo.json | cat
diff --git a/database/presets/agency.json b/database/presets/agency.json
index f4f6d11..26407ea 100644
--- a/database/presets/agency.json
+++ b/database/presets/agency.json
@@ -119,13 +119,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "timesheet_summary"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -139,4 +136,4 @@
         ]
     },
     "description": "Solusi operasional untuk agensi kreatif dengan manajemen proyek, penagihan berbasis progres, dan timesheet terintegrasi."
-}
\ No newline at end of file
+}
diff --git a/database/presets/bakery_preorder.json b/database/presets/bakery_preorder.json
index f572a0d..0981cc2 100644
--- a/database/presets/bakery_preorder.json
+++ b/database/presets/bakery_preorder.json
@@ -86,14 +86,11 @@
     },
     "dashboard": {
         "industry_zone": [
-            {
-                "widget": "upcoming_schedule"
-            },
             {
                 "widget": "low_stock"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -109,4 +106,4 @@
         ]
     },
     "description": "Sistem manajemen pre-order kue dan roti, dengan pelacakan jadwal produksi dan serah terima."
-}
\ No newline at end of file
+}
diff --git a/database/presets/barbershop.json b/database/presets/barbershop.json
index 55bdd86..30871a6 100644
--- a/database/presets/barbershop.json
+++ b/database/presets/barbershop.json
@@ -83,7 +83,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_revenue"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
@@ -103,4 +103,4 @@
         ]
     },
     "description": "Sistem kasir dan antrean potong rambut terintegrasi untuk pangkas rambut, barbershop, dan salon pria."
-}
\ No newline at end of file
+}
diff --git a/database/presets/contractor.json b/database/presets/contractor.json
index 06950e5..acc5d97 100644
--- a/database/presets/contractor.json
+++ b/database/presets/contractor.json
@@ -121,16 +121,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "retention_held"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "pending_approvals"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -144,4 +138,4 @@
         ]
     },
     "description": "Sistem manajemen untuk kontraktor, pantau RAB, termin pembayaran, dan log material dalam satu platform."
-}
\ No newline at end of file
+}
diff --git a/database/presets/cuci_mobil.json b/database/presets/cuci_mobil.json
index d7e02c8..6be9a58 100644
--- a/database/presets/cuci_mobil.json
+++ b/database/presets/cuci_mobil.json
@@ -70,7 +70,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
+            },
+            {
+                "widget": "low_stock"
             }
         ]
     },
@@ -87,4 +90,4 @@
         ]
     },
     "description": "Sistem antrean cepat, tiket masuk, dan manajemen kas untuk cuci mobil dan motor."
-}
\ No newline at end of file
+}
diff --git a/database/presets/custom.json b/database/presets/custom.json
index 007a442..a600a0d 100644
--- a/database/presets/custom.json
+++ b/database/presets/custom.json
@@ -69,6 +69,9 @@
         "industry_zone": [
             {
                 "widget": "kpi_cashflow"
+            },
+            {
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -81,4 +84,4 @@
         ]
     },
     "description": "Bebas kustomisasi kapabilitas, terminologi, dan alur kerja sesuai dengan model bisnis spesifik Anda."
-}
\ No newline at end of file
+}
diff --git a/database/presets/eo.json b/database/presets/eo.json
index 00c8c69..df71ff2 100644
--- a/database/presets/eo.json
+++ b/database/presets/eo.json
@@ -98,16 +98,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "projects_progress"
-            },
-            {
-                "widget": "upcoming_schedule"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "pending_approvals"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -122,4 +116,4 @@
         ]
     },
     "description": "Manajemen acara dan event organizer. Kelola vendor, tiket, dan penagihan klien dari tahap awal hingga acara selesai."
-}
\ No newline at end of file
+}
[0m
[0m$ [0mgit --no-pager show 5249e32 --format= --unified=3 -- database/presets/fnb.json database/presets/fotografi.json database/presets/gym.json database/presets/katering.json database/presets/kedai_kopi.json database/presets/klinik.json database/presets/laundry.json database/presets/manufaktur.json database/presets/retail.json | cat
diff --git a/database/presets/fnb.json b/database/presets/fnb.json
index 5273ed4..9b064fd 100644
--- a/database/presets/fnb.json
+++ b/database/presets/fnb.json
@@ -89,13 +89,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "kpi_revenue"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
-            },
-            {
-                "widget": "kpi_cashflow"
             }
         ]
     },
@@ -112,4 +109,4 @@
         ]
     },
     "description": "Solusi kasir (POS) restoran dan kafe, dengan manajemen meja, cetak dapur, dan rekap shift kasir."
-}
\ No newline at end of file
+}
diff --git a/database/presets/fotografi.json b/database/presets/fotografi.json
index 07eeb96..ed8eaca 100644
--- a/database/presets/fotografi.json
+++ b/database/presets/fotografi.json
@@ -185,9 +185,6 @@
             {
                 "widget": "upcoming_schedule"
             },
-            {
-                "widget": "projects_progress"
-            },
             {
                 "widget": "kpi_cashflow"
             }
@@ -205,4 +202,4 @@
         ]
     },
     "description": "Sistem pemesanan sesi foto, penagihan deposit, dan pelacakan jadwal untuk studio foto maupun fotografer lepas."
-}
\ No newline at end of file
+}
diff --git a/database/presets/gym.json b/database/presets/gym.json
index a1c28ca..b8fbcaf 100644
--- a/database/presets/gym.json
+++ b/database/presets/gym.json
@@ -74,7 +74,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -90,4 +90,4 @@
         ]
     },
     "description": "Manajemen keanggotaan (membership), kelas terjadwal, dan penagihan bulanan otomatis untuk pusat kebugaran."
-}
\ No newline at end of file
+}
diff --git a/database/presets/katering.json b/database/presets/katering.json
index 285a316..8f578c7 100644
--- a/database/presets/katering.json
+++ b/database/presets/katering.json
@@ -87,10 +87,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "upcoming_schedule"
+                "widget": "pending_approvals"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "low_stock"
             }
         ]
     },
@@ -106,4 +106,4 @@
         ]
     },
     "description": "Sistem katering harian dan acara khusus, kelola pesanan, jadwal produksi, dan pengiriman dengan akurat."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kedai_kopi.json b/database/presets/kedai_kopi.json
index e0584d0..b7782e2 100644
--- a/database/presets/kedai_kopi.json
+++ b/database/presets/kedai_kopi.json
@@ -92,9 +92,6 @@
     },
     "dashboard": {
         "industry_zone": [
-            {
-                "widget": "kpi_revenue"
-            },
             {
                 "widget": "kpi_cashflow"
             },
@@ -116,4 +113,4 @@
         ]
     },
     "description": "Solusi kasir cepat untuk kedai kopi (coffee shop)."
-}
\ No newline at end of file
+}
[0m
[0m✱ [0mGlob "database/presets/*.json"[90m 40 matches[0m
[0m
[0m$ [0mgit --no-pager show 5249e32 --format= --unified=3 -- database/presets/klinik.json database/presets/laundry.json database/presets/manufaktur.json database/presets/retail.json database/presets/toko_bangunan.json | cat; git --no-pager show 5249e32 --stat --format= 2>&1 | grep -i preset
app/Services/Preset/PresetDefinitionValidator.php  | 27 +++++--
 database/presets/agency.json                       |  9 +--
 database/presets/bakery_preorder.json              |  7 +-
 database/presets/barbershop.json                   |  4 +-
 database/presets/contractor.json                   | 10 +--
 database/presets/cuci_mobil.json                   |  7 +-
 database/presets/custom.json                       |  5 +-
 database/presets/eo.json                           | 10 +--
 database/presets/fnb.json                          |  7 +-
 database/presets/fotografi.json                    |  5 +-
 database/presets/gym.json                          |  4 +-
 database/presets/katering.json                     |  6 +-
 database/presets/kedai_kopi.json                   |  5 +-
 database/presets/kos_coworking.json                |  6 +-
 database/presets/kursus.json                       |  4 +-
 database/presets/pharmacy.json                     | 10 +--
 database/presets/rental.json                       |  9 +--
 tests/Feature/PresetWidgetCapabilityMatrixTest.php | 93 ++++++++++++++++++++++
[0m
[0m$ [0mgit --no-pager diff 70d23e3^ 70d23e3 --stat -- database/presets/ | cat; git --no-pager show 5249e32 --format= --unified=3 -- database/presets/klinik_gigi.json database/presets/laundry.json database/presets/manufaktur.json database/presets/retail.json | cat
database/presets/agency.json          |  9 +++------
 database/presets/bakery_preorder.json |  7 ++-----
 database/presets/barbershop.json      |  4 ++--
 database/presets/contractor.json      | 10 ++--------
 database/presets/cuci_mobil.json      |  7 +++++--
 database/presets/custom.json          |  5 ++++-
 database/presets/eo.json              | 10 ++--------
 database/presets/fnb.json             |  7 ++-----
 database/presets/fotografi.json       |  5 +----
 database/presets/gym.json             |  4 ++--
 database/presets/katering.json        |  6 +++---
 database/presets/kedai_kopi.json      |  5 +----
 database/presets/kos_coworking.json   |  6 +++---
 database/presets/kursus.json          |  4 ++--
 database/presets/pharmacy.json        | 10 ++--------
 database/presets/rental.json          |  9 +++------
 16 files changed, 39 insertions(+), 69 deletions(-)
[0m
[0m$ [0mgit --no-pager show 5249e32 --format= --unified=3 -- database/presets/kos_coworking.json database/presets/kursus.json database/presets/pharmacy.json database/presets/rental.json | cat
diff --git a/database/presets/kos_coworking.json b/database/presets/kos_coworking.json
index fdbd533..2d9cb19 100644
--- a/database/presets/kos_coworking.json
+++ b/database/presets/kos_coworking.json
@@ -74,10 +74,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "upcoming_schedule"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -91,4 +91,4 @@
         ]
     },
     "description": "Manajemen tagihan bulanan dan penyewaan ruang fleksibel untuk kos, apartemen, dan coworking space."
-}
\ No newline at end of file
+}
diff --git a/database/presets/kursus.json b/database/presets/kursus.json
index eb0d602..1a10f92 100644
--- a/database/presets/kursus.json
+++ b/database/presets/kursus.json
@@ -77,7 +77,7 @@
                 "widget": "upcoming_schedule"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -92,4 +92,4 @@
         ]
     },
     "description": "Manajemen kelas, tagihan SPP, dan daftar hadir untuk bimbingan belajar dan tempat kursus."
-}
\ No newline at end of file
+}
diff --git a/database/presets/pharmacy.json b/database/presets/pharmacy.json
index 347a517..22698e2 100644
--- a/database/presets/pharmacy.json
+++ b/database/presets/pharmacy.json
@@ -96,16 +96,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "prescription_queue"
-            },
-            {
-                "widget": "expiring_batches"
+                "widget": "kpi_cashflow"
             },
             {
                 "widget": "low_stock"
-            },
-            {
-                "widget": "kpi_revenue"
             }
         ]
     },
@@ -121,4 +115,4 @@
         ]
     },
     "description": "Solusi apotek dengan manajemen stok obat, resep, dan kontrol tanggal kedaluwarsa."
-}
\ No newline at end of file
+}
diff --git a/database/presets/rental.json b/database/presets/rental.json
index 4a96d1c..ec7eb18 100644
--- a/database/presets/rental.json
+++ b/database/presets/rental.json
@@ -108,13 +108,10 @@
     "dashboard": {
         "industry_zone": [
             {
-                "widget": "bookings_due_today"
-            },
-            {
-                "widget": "overdue_returns"
+                "widget": "kpi_cashflow"
             },
             {
-                "widget": "kpi_cashflow"
+                "widget": "pending_approvals"
             }
         ]
     },
@@ -129,4 +126,4 @@
         ]
     },
     "description": "Sistem penyewaan barang dan kendaraan, hitung durasi, denda keterlambatan, dan pemeliharaan aset."
-}
\ No newline at end of file
+}
[0m
[0m$ [0mgrep -h '"widget"' database/presets/*.json | sort | uniq -c; for f in database/presets/*.json; do n=$(grep -c '"widget"' "$f"); echo "$n $f"; done | sort -n | head -5; php -r 'foreach (glob("database/presets/*.json") as $f) { json_decode(file_get_contents($f)); if (json_last_error()) echo "INVALID JSON: $f\n"; } echo "json check done\n";'
2                 "widget": "deals_pipeline"
     17                 "widget": "kpi_cashflow"
     10                 "widget": "low_stock"
     13                 "widget": "pending_approvals"
      8                 "widget": "upcoming_schedule"
      4             {"widget": "deals_pipeline"},
     18             {"widget": "kpi_cashflow"},
     13             {"widget": "low_stock"},
     18             {"widget": "pending_approvals"}
     10             {"widget": "upcoming_schedule"},
2 database/presets/agency.json
2 database/presets/bakery_preorder.json
2 database/presets/contractor.json
2 database/presets/cuci_mobil.json
2 database/presets/custom.json
json check done
[0m
[0m→ [0mRead app/Models/AdminImpersonationSession.php
[0m→ [0mRead app/Http/Middleware/EnsureCompanyAccess.php
[0m→ [0mRead app/Http/Middleware/SetCurrentCompany.php
[0m✱ [0mGrep "admin_impersonation"[90m in database/migrations · 2 matches[0m
[0m✱ [0mGrep "expires|revoked|active"[90m in app/Models · 28 matches[0m
[0m→ [0mRead database/migrations/2026_09_18_150000_create_admin_impersonation_sessions_table.php
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 --stat -- tests/ app/ resources/ scripts/ | cat
app/Contracts/CompanyContext.php                   |   6 +
 app/Livewire/Auth/Login.php                        |   1 +
 app/Livewire/Dashboard.php                         |  20 +-
 app/Providers/AppServiceProvider.php               |  12 ++
 app/Services/Dashboard/CashFlowCalculator.php      | 113 +++++++++++
 app/Services/Dashboard/DashboardComposer.php       |  25 ++-
 app/Services/Dashboard/WidgetCapabilityMap.php     |  43 ++++
 app/Services/Dashboard/WidgetRegistry.php          |  36 +---
 app/Services/Eloquent/EloquentCompanyContext.php   |  58 +++++-
 app/Services/Json/JsonCompanyContext.php           |  29 +++
 app/Services/Preset/PresetDefinitionValidator.php  |  27 ++-
 .../views/components/layouts/module.blade.php      |   2 +-
 resources/views/layouts/app.blade.php              |   2 +-
 resources/views/livewire/dashboard.blade.php       |   5 +
 resources/views/livewire/sidebar.blade.php         |   2 +-
 scripts/smoke-e2e.php                              | 218 +++++++++++++--------
 scripts/smoke_browser_c5.py                        | 148 ++++++++++++++
 tests/Feature/CompanyDisplayNameParityTest.php     |  88 +++++++++
 tests/Feature/DashboardCashFlowIntegrityTest.php   | 101 ++++++++++
 tests/Feature/DashboardEloquentTest.php            |  27 +++
 tests/Feature/DashboardTest.php                    | 135 +++++++++++++
 tests/Feature/DataErasureTest.php                  |  17 +-
 tests/Feature/DataExportTest.php                   |  10 +-
 tests/Feature/LivewireTenantMiddlewareTest.php     | 122 ++++++++++++
 tests/Feature/ModuleScreenWireIdStabilityTest.php  |   3 +-
 tests/Feature/PresetWidgetCapabilityMatrixTest.php |  93 +++++++++
 26 files changed, 1188 insertions(+), 155 deletions(-)
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 -- tests/Feature/DataErasureTest.php tests/Feature/DataExportTest.php | cat
diff --git a/tests/Feature/DataErasureTest.php b/tests/Feature/DataErasureTest.php
index fa09443..8c404ed 100644
--- a/tests/Feature/DataErasureTest.php
+++ b/tests/Feature/DataErasureTest.php
@@ -14,6 +14,7 @@
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Illuminate\Support\Facades\Config;
 use Livewire\Livewire;
+use LogicException;
 use Tests\TestCase;
 
 class DataErasureTest extends TestCase
@@ -178,14 +179,12 @@ public function test_non_owner_is_rejected_without_mutation(): void
         ]);
 
         $this->actingAs($staff);
+        // Fail-closed lebih awal (MQ-01C2): staff non-owner ditolak di lapisan
+        // context sebelum komponen tersentuh; data tetap tanpa mutasi.
+        $this->expectException(LogicException::class);
+        $this->expectExceptionMessage('Akses lintas company ditolak.');
         app(CompanyContext::class)->setCurrent((string) $company->id);
 
-        Livewire::test(DataErasure::class)
-            ->set('contactId', (string) $contact->id)
-            ->set('confirmationCode', 'YA')
-            ->call('erase')
-            ->assertStatus(403);
-
         $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
         $this->assertDatabaseMissing('access_logs', [
             'company_id' => $company->id,
@@ -219,8 +218,10 @@ public function test_erasure_tab_is_absent_from_staff_dom(): void
         $intruder->update(['current_company_id' => $company->id]);
 
         $this->actingAs($intruder);
+        // Fail-closed lebih awal (MQ-01C2): intruder non-owner ditolak di
+        // lapisan context sebelum route/komponen tersentuh.
+        $this->expectException(LogicException::class);
+        $this->expectExceptionMessage('Akses lintas company ditolak.');
         app(CompanyContext::class)->setCurrent((string) $company->id);
-
-        $this->get('/app/settings')->assertStatus(403);
     }
 }
diff --git a/tests/Feature/DataExportTest.php b/tests/Feature/DataExportTest.php
index 7ec83be..8d18227 100644
--- a/tests/Feature/DataExportTest.php
+++ b/tests/Feature/DataExportTest.php
@@ -19,6 +19,7 @@
 use Illuminate\Support\Facades\Queue;
 use Illuminate\Support\Facades\Storage;
 use Livewire\Livewire;
+use LogicException;
 use Tests\TestCase;
 
 class DataExportTest extends TestCase
@@ -119,12 +120,13 @@ public function test_non_owner_cannot_dispatch_export_from_the_component(): void
         ]);
 
         $this->actingAs($staff);
+        // Fail-closed lebih awal (MQ-01C2): staff non-owner tidak lagi bisa
+        // set context company milik orang lain - ditolak di lapisan context
+        // sebelum komponen tersentuh. Data tetap tanpa mutasi.
+        $this->expectException(LogicException::class);
+        $this->expectExceptionMessage('Akses lintas company ditolak.');
         app(CompanyContext::class)->setCurrent((string) $company->id);
 
-        Livewire::test(DataExport::class)
-            ->call('export')
-            ->assertStatus(403);
-
         Queue::assertNotPushed(BuildCompanyExport::class);
     }
 }
[0m
[0m→ [0mRead tests/Feature/LivewireTenantMiddlewareTest.php
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 -- resources/views/livewire/sidebar.blade.php resources/views/livewire/dashboard.blade.php app/Livewire/Auth/Login.php app/Contracts/CompanyContext.php | cat
diff --git a/app/Contracts/CompanyContext.php b/app/Contracts/CompanyContext.php
index c04e1d3..1932591 100644
--- a/app/Contracts/CompanyContext.php
+++ b/app/Contracts/CompanyContext.php
@@ -9,4 +9,10 @@ public function current(): string;
     public function preset(): string;
 
     public function setCurrent(string $company): void;
+
+    /**
+     * Nama tampilan company untuk UI (MQ-01C4): kontrak yang sama untuk
+     * JSON dan Eloquent - nama ter-cased, bukan identifier/ID mentah.
+     */
+    public function displayName(): string;
 }
diff --git a/app/Livewire/Auth/Login.php b/app/Livewire/Auth/Login.php
index 256456d..6b4d842 100644
--- a/app/Livewire/Auth/Login.php
+++ b/app/Livewire/Auth/Login.php
@@ -73,6 +73,7 @@ public function login(Request $request): void
         // onboarding, bukan dashboard yang bisa 403.
         if (! $companyId) {
             $this->redirect(route('onboarding'));
+
             return;
         }
 
diff --git a/resources/views/livewire/dashboard.blade.php b/resources/views/livewire/dashboard.blade.php
index b61a58c..541ba35 100644
--- a/resources/views/livewire/dashboard.blade.php
+++ b/resources/views/livewire/dashboard.blade.php
@@ -1,4 +1,9 @@
 <div class="space-y-8" aria-busy="false" wire:loading.attr="aria-busy">
+    @if ($themeError)
+        <div role="status" class="rounded-[var(--erp-radius-md)] border border-[var(--erp-warning)] bg-[var(--erp-warning-soft)] px-4 py-3 text-sm text-[var(--erp-text-primary)]">
+            Tampilan kembali ke tema default karena pengaturan tampilan tidak dapat dibaca.
+        </div>
+    @endif
     @if ($loadError !== null)
         <section role="alert" aria-labelledby="dashboard-error-title" class="rounded-[var(--erp-radius-lg)] border border-[var(--erp-danger)] bg-[var(--erp-bg-secondary)] p-6 shadow-[var(--erp-card-shadow)] sm:p-8">
             <div class="flex items-start gap-4">
diff --git a/resources/views/livewire/sidebar.blade.php b/resources/views/livewire/sidebar.blade.php
index 0950507..5bc50a6 100644
--- a/resources/views/livewire/sidebar.blade.php
+++ b/resources/views/livewire/sidebar.blade.php
@@ -10,7 +10,7 @@ class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-[var(--erp
     aria-labelledby="module-sidebar-title"
 >
     <div class="flex items-center justify-between border-b border-[var(--erp-border)] px-5 py-5">
-        <a href="{{ route('lobby') }}" wire:navigate class="min-w-0 flex-1 hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] rounded-[var(--erp-radius-sm)]">
+        <a href="{{ route('lobby') }}" wire:navigate class="min-h-11 min-w-0 flex-1 hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--erp-focus)] rounded-[var(--erp-radius-sm)]">
             <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--erp-sidebar-text)] opacity-70">Agentic BOS</p>
             <h2 id="module-sidebar-title" class="mt-1 truncate text-lg font-bold">{{ $this->title }}</h2>
         </a>
[0m
[0m✱ [0mGrep "replace\('-', ' '\)->title|str\(\$company"[90m in resources/views · 0 matches[0m
[0m✱ [0mGrep "displayName"[90m in . · 8 matches[0m
[0m✱ [0mGrep "\$company|companyName|company_name"[90m in resources/views · 2 matches[0m
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 -- app/ resources/ scripts/ | grep -iE 'bengkel|barbershop|katering|klinik|pharmacy|bakery|salon|laundry|fotografi|kedai|gym|warnet|toko|warung|kopi|angkringan|butik|kontraktor|contractor|manufaktur|manufactur' | head -40
-$user = User::create(['name'=>'Pemilik Warung E2E','email'=>$email,'password'=>bcrypt('PasswordKuat123!')]);
+$user = User::create(['name' => 'Pemilik Warung E2E', 'email' => $email, 'password' => bcrypt('PasswordKuat123!')]);
-    'name'=>'Warung Kopi E2E','slug'=>'warung-e2e-'.time(),'owner_user_id'=>$user->id,
+    'name' => 'Warung Kopi E2E', 'slug' => 'warung-e2e-'.time(), 'owner_user_id' => $user->id,
-App\Models\BusinessIdentity::create(['company_id'=>$company->id,'legal_name'=>'Warung Kopi E2E','tax_mode'=>'non_taxable','price_includes_tax'=>true,'is_default'=>true]);
+BusinessIdentity::create(['company_id' => $company->id, 'legal_name' => 'Warung Kopi E2E', 'tax_mode' => 'non_taxable', 'price_includes_tax' => true, 'is_default' => true]);
-    $order = $repo->for((string)$company->id,'cash_entries')->save(['direction'=>'in','amount'=>50000,'note'=>'Penjualan kopi','occurred_at'=>now()->toDateTimeString()]);
+    $order = $repo->for((string) $company->id, 'cash_entries')->save(['direction' => 'in', 'amount' => 50000, 'note' => 'Penjualan kopi', 'occurred_at' => now()->toDateTimeString()]);
-$company2 = Company::create(['name'=>'Toko Lain','slug'=>'toko-lain-'.time(),'owner_user_id'=>$user2->id,'business_preset'=>'retail','module_settings'=>[],'is_active'=>true]);
+$company2 = Company::create(['name' => 'Toko Lain', 'slug' => 'toko-lain-'.time(), 'owner_user_id' => $user2->id, 'business_preset' => 'retail', 'module_settings' => [], 'is_active' => true]);
+        if "bengkel smoke" in body.lower():
[0m
[0m→ [0mRead tests/Feature/CompanyDisplayNameParityTest.php
[0m✱ [0mGrep "->name|current\(\)"[90m in resources/views · 12 matches[0m
[0m→ [0mRead resources/views/layouts/app.blade.php
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 -- resources/views/layouts/app.blade.php resources/views/components/layouts/module.blade.php | cat
diff --git a/resources/views/components/layouts/module.blade.php b/resources/views/components/layouts/module.blade.php
index e3f107e..bab9d3b 100644
--- a/resources/views/components/layouts/module.blade.php
+++ b/resources/views/components/layouts/module.blade.php
@@ -19,7 +19,7 @@
     @endif
     <div wire:loading class="fixed left-0 right-0 top-0 z-[100] h-1 animate-pulse bg-[var(--erp-accent)]"></div>
 
-    <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-2 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
+    <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-3 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:min-h-11">
         Lewati ke konten utama
     </a>
 
diff --git a/resources/views/layouts/app.blade.php b/resources/views/layouts/app.blade.php
index c23a338..8d68f4f 100644
--- a/resources/views/layouts/app.blade.php
+++ b/resources/views/layouts/app.blade.php
@@ -9,7 +9,7 @@
     </head>
     <body class="bg-[var(--erp-bg-base)] text-[var(--erp-text-primary)] font-sans antialiased min-h-screen">
 
-        <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-2 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
+        <a href="#main-content" class="sr-only z-[70] rounded-[var(--erp-radius-sm)] bg-[var(--erp-accent)] px-4 py-3 text-[var(--erp-text-inverse)] focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:min-h-11">
             Lewati ke konten utama
         </a>
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 -- app/Services/Eloquent/EloquentCompanyContext.php | cat
diff --git a/app/Services/Eloquent/EloquentCompanyContext.php b/app/Services/Eloquent/EloquentCompanyContext.php
index 2c58a82..5647c2e 100644
--- a/app/Services/Eloquent/EloquentCompanyContext.php
+++ b/app/Services/Eloquent/EloquentCompanyContext.php
@@ -3,14 +3,13 @@
 namespace App\Services\Eloquent;
 
 use App\Contracts\CompanyContext;
+use App\Models\AdminImpersonationSession;
 use App\Models\Company;
 use Illuminate\Support\Facades\Auth;
 use LogicException;
 
 class EloquentCompanyContext implements CompanyContext
 {
-    private ?Company $cachedCompany = null;
-
     public function current(): string
     {
         $company = $this->getCompany();
@@ -25,6 +24,11 @@ public function preset(): string
         return $company->business_preset;
     }
 
+    public function displayName(): string
+    {
+        return (string) $this->getCompany()->name;
+    }
+
     public function setCurrent(string $companyId): void
     {
         $company = Company::find($companyId);
@@ -32,6 +36,8 @@ public function setCurrent(string $companyId): void
             throw new LogicException('Company tidak ditemukan.');
         }
 
+        $this->assertAuthorizedFor($company);
+
         session(['active_company' => $companyId]);
 
         $user = Auth::user();
@@ -39,21 +45,19 @@ public function setCurrent(string $companyId): void
             $user->current_company_id = (int) $companyId;
             $user->save();
         }
-
-        $this->cachedCompany = $company;
     }
 
     public function getCompany(): Company
     {
-        if ($this->cachedCompany) {
-            return $this->cachedCompany;
-        }
-
         $activeCompanyId = session('active_company');
         if ($activeCompanyId) {
             $company = Company::find($activeCompanyId);
             if ($company) {
-                $this->cachedCompany = $company;
+                // Session adalah input tak tepercaya: kepemilikan (atau sesi
+                // impersonasi admin yang sah) wajib diverifikasi ulang di
+                // sini, bukan hanya di middleware HTTP - request Livewire
+                // dan pemanggilan service langsung juga melewati jalur ini.
+                $this->assertAuthorizedFor($company);
 
                 return $company;
             }
@@ -63,8 +67,9 @@ public function getCompany(): Company
         if ($user && $user->current_company_id) {
             $company = Company::find($user->current_company_id);
             if ($company) {
+                $this->assertAuthorizedFor($company);
+
                 session(['active_company' => $company->id]);
-                $this->cachedCompany = $company;
 
                 return $company;
             }
@@ -72,4 +77,37 @@ public function getCompany(): Company
 
         throw new LogicException('Company aktif belum di-set pada EloquentCompanyContext.');
     }
+
+    /**
+     * Fail-closed: user harus owner company, atau admin platform dengan sesi
+     * impersonasi sah yang menargetkan company ini. Tanpa user terautentikasi
+     * (CLI/seed), tidak ada prinsipal untuk diverifikasi - izinkan set,
+     * karena pembacaan getCompany() tetap diverifikasi saat ada user.
+     */
+    private function assertAuthorizedFor(Company $company): void
+    {
+        $user = Auth::user();
+        if ($user === null) {
+            return;
+        }
+
+        if ((int) $company->owner_user_id === (int) $user->id) {
+            return;
+        }
+
+        $impersonationId = session('admin_impersonation_id');
+        if (is_string($impersonationId) && $impersonationId !== '') {
+            $valid = AdminImpersonationSession::query()
+                ->where('session_id', $impersonationId)
+                ->where('target_company_id', $company->id)
+                ->where('admin_user_id', $user->id)
+                ->exists();
+
+            if ($valid) {
+                return;
+            }
+        }
+
+        throw new LogicException('Akses lintas company ditolak.');
+    }
 }
[0m
[0m✱ [0mGrep "AdminImpersonationSession::(create|query)|impersonate"[90m in app · 3 matches[0m
[0m→ [0mRead app/Http/Controllers/Admin/AdminImpersonationController.php
[0m✱ [0mGrep "impersonate|platform_admin|is_admin"[90m in routes · 2 matches[0m
[0m
[0m$ [0mgrep -rn "impersonate\|admin.dashboard" routes/ | head
routes/web.php:89:    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
routes/web.php:90:    Route::post('/impersonate/stop', [AdminImpersonationController::class, 'stop'])->name('admin.impersonate.stop');
routes/web.php:91:    Route::post('/impersonate/{company}', [AdminImpersonationController::class, 'impersonate'])->name('admin.impersonate');
[0m
[0m$ [0msed -n 75,100p routes/web.php
abort(404, 'Export not found');
        })->name('settings.export.download');

        // PAY-1: Halaman pilih paket dan instruksi pembayaran manual
        Route::get('/app/billing/subscribe', SubscribePage::class)->name('billing.subscribe');
        Route::get('/app/billing/payment-instruction/{invoice}', PaymentInstructionPage::class)->name('billing.payment-instruction');

        Route::get('/app/{module}/{submodule?}', [ModuleController::class, 'show'])
            ->middleware(EnsureFeatureEnabled::class)
            ->name('app.module');
    });
});

Route::middleware(['auth', RequireSuperAdmin::class])->prefix('admin')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/impersonate/stop', [AdminImpersonationController::class, 'stop'])->name('admin.impersonate.stop');
    Route::post('/impersonate/{company}', [AdminImpersonationController::class, 'impersonate'])->name('admin.impersonate');
    // PAY-1: Admin invoice management
    Route::get('/invoices', AdminInvoiceManager::class)->name('admin.invoices');
});
[0m
[0m→ [0mRead tests/Feature/PresetWidgetCapabilityMatrixTest.php
[0m→ [0mRead tests/Feature/DashboardCashFlowIntegrityTest.php
[0m→ [0mRead tests/Feature/DataErasureTest.php[90m [offset=160][0m
[0m✱ [0mGrep "class CashEntry|'amount'"[90m in app/Models · 12 matches[0m
[0m
[0m$ [0mgrep -n "themeError\|role=\"status\"\|theme" tests/Feature/DashboardTest.php | head -20
414:    public function test_theme_read_failure_falls_back_to_default_theme_with_controlled_error_state(): void
[0m
[0m$ [0mgrep -n "def \|assert\|expect\|360\|390\|1280\|800\|Tab\|reload" scripts/smoke_browser_c5.py | head -30
4:Jalankan dengan server dev hidup di http://127.0.0.1:8003 (main worktree,
5:pola preseden: APP_URL/ASSET_URL=http://127.0.0.1:8003).
7:Buktikan: reload/loading state, fokus setelah navigasi, viewport 360x390,
16:BASE = "http://127.0.0.1:8003"
23:def ok(msg):
27:def note(msg):
32:def login(page):
45:def overflow_metrics(page):
61:def check_viewport(page, name):
73:def run():
76:        # --- Mobile 360x390 (kontrak plan) ---
77:        ctx = browser.new_context(viewport={"width": 360, "height": 390})
84:        ok("login nyata via browser (mobile 360x390)")
87:        check_viewport(page, "mobile-360x390")
99:        has_reload = page.get_by_role("button", name="Perbarui data").count()
100:        if has_reload:
105:            ok(f"reload: konten tetap ter-render sebelum={before} sesudah={after}")
107:                note("reload menampilkan pesan error padahal data valid")
111:        # Keyboard: Tab dari skip-link; fokus terlihat di elemen interaktif
113:        page.keyboard.press("Tab")
118:            note("keyboard: Tab tidak mengarahkan fokus ke elemen interaktif (skip-link?)")
128:        page.screenshot(path=r"D:\PROJECTS\agentic-bos\storage\app\_shots\c5_mobile_360.png")
131:        # --- Desktop 1280x800 ---
132:        ctx2 = browser.new_context(viewport={"width": 1280, "height": 800})
136:        check_viewport(page2, "desktop-1280x800")
[0m
[0m✱ [0mGrep "class .*CompanySettingsStore|function read"[90m in app/Services · 7 matches[0m
[0m→ [0mRead app/Services/Json/JsonCompanySettingsStore.php
[0m
[0m$ [0mfind app -name ThemeRegistry.php | head -2
app/Services/ThemeRegistry.php
[0m
[0m→ [0mRead app/Services/ThemeRegistry.php
[0m
[0m$ [0mphp -r 'foreach (glob("database/presets/*.json") as $f) { $d = json_decode(file_get_contents($f), true); $w = array_column($d["dashboard"]["industry_zone"], "widget"); if (count($w) !== count(array_unique($w))) echo "DUP: $f\n"; if (!$w) echo "EMPTY: $f\n"; $caps = $d["capabilities"]; } echo "dup/empty check done\n";' && grep -l '"approval_flow": true' database/presets/custom.json database/presets/gym.json database/presets/kursus.json database/presets/cuci_mobil.json database/presets/katering.json database/presets/kos_coworking.json 2>/dev/null; grep -o '"approval_flow": true' database/presets/custom.json
dup/empty check done
database/presets/custom.json
database/presets/gym.json
database/presets/kursus.json
database/presets/cuci_mobil.json
database/presets/katering.json
database/presets/kos_coworking.json
"approval_flow": true
[0m
[0m$ [0mgit --no-pager diff d0cc3fd..750fd31 -- tests/Feature/ModuleScreenWireIdStabilityTest.php app/Services/Dashboard/WidgetRegistry.php app/Services/Dashboard/DashboardComposer.php | cat
diff --git a/app/Services/Dashboard/DashboardComposer.php b/app/Services/Dashboard/DashboardComposer.php
index 03db570..ce5ab0f 100644
--- a/app/Services/Dashboard/DashboardComposer.php
+++ b/app/Services/Dashboard/DashboardComposer.php
@@ -18,6 +18,7 @@ public function __construct(
         private readonly FeatureResolver $features,
         private readonly TerminologyResolver $terms,
         private readonly WidgetRegistry $widgets,
+        private readonly CashFlowCalculator $cashFlowCalculator,
     ) {}
 
     /** @return array{company: string, kpis: list<array{label: string, value: string, meta: string, tone: string}>, assistant_report: array{summary: string, generated_at: string, period: string, highlights: list<string>, recommended_actions: list<string>}, widgets: list<array<string, mixed>>} */
@@ -33,13 +34,21 @@ public function compose(): array
         $widgets = [];
         foreach ($preset['dashboard']['industry_zone'] ?? [] as $selection) {
             $key = is_array($selection) ? ($selection['widget'] ?? null) : null;
-            if (is_string($key) && $this->widgets->available($key)) {
-                $widgets[] = $this->widgets->compose($key);
+            if (! is_string($key)) {
+                throw new InvalidArgumentException('Deklarasi widget dashboard tidak valid.');
             }
+            if (! $this->widgets->available($key)) {
+                // MQ-01C3: gagal jelas, bukan hilang diam-diam. Validator
+                // preset sudah menolak kombinasi ini saat seeding; sampai di
+                // sini berarti data runtime (module settings) menyimpang dari
+                // preset - tetap fail-closed, jangan render sebagian.
+                throw new InvalidArgumentException("Widget dashboard tidak tersedia untuk company aktif: {$key}");
+            }
+            $widgets[] = $this->widgets->compose($key);
         }
 
         return [
-            'company' => str($company)->replace('-', ' ')->title()->toString(),
+            'company' => $this->companyContext->displayName(),
             'kpis' => $this->universalKpis(),
             'assistant_report' => $this->assistantReport(),
             'widgets' => $widgets,
@@ -50,11 +59,7 @@ public function compose(): array
     private function universalKpis(): array
     {
         $cashEntries = $this->rows('cash_entries');
-        $balance = 0.0;
-        foreach ($cashEntries as $entry) {
-            $amount = (float) ($entry['amount'] ?? 0);
-            $balance += ($entry['direction'] ?? null) === 'in' ? $amount : -$amount;
-        }
+        $flow = $this->cashFlowCalculator->calculate($cashEntries);
 
         [$workEntity, $workTerm] = $this->workSource();
         $work = $this->rows($workEntity);
@@ -63,9 +68,9 @@ private function universalKpis(): array
         return [
             [
                 'label' => 'Arus kas bersih',
-                'value' => 'Rp '.number_format($balance, 0, ',', '.'),
+                'value' => $this->cashFlowCalculator->formatRupiah($flow['balance_cents']),
                 'meta' => count($cashEntries).' transaksi tercatat',
-                'tone' => $balance >= 0 ? 'success' : 'danger',
+                'tone' => $flow['balance_cents'] >= 0 ? 'success' : 'danger',
             ],
             [
                 'label' => $this->terms->resolve($workTerm).' aktif',
diff --git a/app/Services/Dashboard/WidgetRegistry.php b/app/Services/Dashboard/WidgetRegistry.php
index 6410131..57f3273 100644
--- a/app/Services/Dashboard/WidgetRegistry.php
+++ b/app/Services/Dashboard/WidgetRegistry.php
@@ -12,29 +12,21 @@
 
 class WidgetRegistry
 {
-    /** @var array<string, list<string>> */
-    private const REQUIREMENTS = [
-        'upcoming_schedule' => ['scheduling'],
-        'low_stock' => ['inventory'],
-        'kpi_cashflow' => ['finance.cashbook'],
-        'deals_pipeline' => ['deals'],
-        'pending_approvals' => ['approval_flow'],
-    ];
-
     public function __construct(
         private readonly EntityRepository $repository,
         private readonly CompanyContext $companyContext,
         private readonly FeatureResolver $features,
         private readonly TerminologyResolver $terms,
+        private readonly CashFlowCalculator $cashFlowCalculator,
     ) {}
 
     public function available(string $key): bool
     {
-        if (! isset(self::REQUIREMENTS[$key])) {
+        if (! WidgetCapabilityMap::known($key)) {
             return false;
         }
 
-        foreach (self::REQUIREMENTS[$key] as $capability) {
+        foreach (WidgetCapabilityMap::required($key) as $capability) {
             if (! $this->features->enabled($capability)) {
                 return false;
             }
@@ -121,24 +113,15 @@ private function lowStock(): array
     /** @return array{key: string, title: string, value: string, meta: string, items: list<array{primary: string, secondary: string}>, tone: string} */
     private function cashFlow(): array
     {
-        $incoming = 0.0;
-        $outgoing = 0.0;
-        foreach ($this->rows('cash_entries') as $entry) {
-            $amount = (float) ($entry['amount'] ?? 0);
-            if (($entry['direction'] ?? null) === 'in') {
-                $incoming += $amount;
-            } else {
-                $outgoing += $amount;
-            }
-        }
+        $flow = $this->cashFlowCalculator->calculate($this->rows('cash_entries'));
 
         return $this->card(
             'kpi_cashflow',
             'Arus kas',
-            $this->currency($incoming - $outgoing),
-            'Masuk '.$this->currency($incoming).' · keluar '.$this->currency($outgoing),
+            $this->cashFlowCalculator->formatRupiah($flow['balance_cents']),
+            'Masuk '.$this->cashFlowCalculator->formatRupiah($flow['incoming_cents']).' · keluar '.$this->cashFlowCalculator->formatRupiah($flow['outgoing_cents']),
             [],
-            $incoming >= $outgoing ? 'success' : 'danger',
+            $flow['balance_cents'] >= 0 ? 'success' : 'danger',
         );
     }
 
@@ -205,11 +188,6 @@ private function card(string $key, string $title, string $value, string $meta, a
         return compact('key', 'title', 'value', 'meta', 'items', 'tone');
     }
 
-    private function currency(float $amount): string
-    {
-        return 'Rp '.number_format($amount, 0, ',', '.');
-    }
-
     private function formatNumber(float $number): string
     {
         return number_format($number, 0, ',', '.');
diff --git a/tests/Feature/ModuleScreenWireIdStabilityTest.php b/tests/Feature/ModuleScreenWireIdStabilityTest.php
index 6ccad0a..61a9a99 100644
--- a/tests/Feature/ModuleScreenWireIdStabilityTest.php
+++ b/tests/Feature/ModuleScreenWireIdStabilityTest.php
@@ -3,6 +3,7 @@
 namespace Tests\Feature;
 
 use App\Contracts\CompanyContext;
+use App\Contracts\EntityRepository;
 use App\Livewire\Screens\ListScreen;
 use Illuminate\Filesystem\Filesystem;
 use Livewire\Livewire;
@@ -164,7 +165,7 @@ public function test_multiple_actions_on_list_screen_work_sequentially(): void
     private function seedRows(string $company, string $entity, array $rows): void
     {
         app(CompanyContext::class)->setCurrent($company);
-        $repository = app(\App\Contracts\EntityRepository::class)->for($company, $entity);
+        $repository = app(EntityRepository::class)->for($company, $entity);
 
         foreach ($rows as $row) {
             $repository->save($row);
[0m
[0m✱ [0mGrep "setCurrent\("[90m in app · 8 matches[0m
[0m→ [0mRead app/Livewire/BranchSwitcher.php
[0m
[0m$ [0msed -n 55,80p app/Livewire/Auth/Login.php
session()->regenerate();

        // Set konteks company aktif agar middleware /app tidak 403.
        $user = Auth::user();
        $companyId = $user->current_company_id;
        if (! $companyId) {
            $company = $user->companies()->first();
            if ($company) {
                $companyId = $company->id;
                $user->current_company_id = $companyId;
                $user->save();
            }
        }
        if ($companyId) {
            app(CompanyContext::class)->setCurrent((string) $companyId);
        }

        // User tanpa company (baru daftar, belum onboarding) diarahkan ke
        // onboarding, bukan dashboard yang bisa 403.
        if (! $companyId) {
            $this->redirect(route('onboarding'));

            return;
        }

        $this->redirectIntended(route('app.dashboard'));
[0m
# QA Review MQ-01 C1–C5 (d0cc3fd..750fd31) — READ-ONLY

## (a) Verdict Table

| # | Question | Verdict | Evidence |
|---|---|---|---|
| 1 | MONEY fail-closed KPI + widget | **PASS** | Both paths route through `CashFlowCalculator` (DashboardComposer.php:62, WidgetRegistry.php:116). Invalid direction/amount/negative/non-finite/overflow all throw `UnexpectedValueException` (CashFlowCalculator.php:31,83,99,108); caught → controlled `loadError` (app/Livewire/Dashboard.php:61-64). Double-tested per-path (DashboardCashFlowIntegrityTest.php:34,56). `formatRupiah` INT_MIN guard (CashFlowCalculator.php:52). No silent-default path remains (old `(float)($entry['amount'] ?? 0)` deleted, WidgetRegistry diff confirmed). |
| 2 | TENANT bypass | **PASS** (1 concern) | `assertAuthorizedFor` re-verified on every `getCompany()` resolution incl. session + `current_company_id` fallback (EloquentCompanyContext.php:60,70); `setCurrent` also asserts (line 39); cache removed. Livewire updates covered via persistent middleware (AppServiceProvider.php:50-53). Impersonation query binds `admin_user_id` + `target_company_id` + `session_id` (EloquentCompanyContext.php:100-104) — session forgery requires a DB row only superadmin can create (routes/web.php:88 `RequireSuperAdmin`). Proven by LivewireTenantMiddlewareTest.php:39,57,78 (forged session 403/throw, post-revoke reload fails closed). All `setCurrent` callers validated. Concern: no expiry, see F2. |
| 3 | CONTRACT single source | **PASS** (1 concern) | `WidgetRegistry::available/compose` and `PresetDefinitionValidator::validateDashboard` both gate on `WidgetCapabilityMap::known/required` (WidgetRegistry.php:25,29; PresetDefinitionValidator.php:319,322). All 40 preset JSONs declare only the 5 map widgets (grep verified: deals_pipeline, kpi_cashflow, low_stock, pending_approvals, upcoming_schedule). Composer fail-closed on unavailable widget (DashboardComposer.php:40-46). Concern: stale `WIDGETS` const, see F1. |
| 4 | PRESET DATA valid + sensible | **PASS** | 16/16 modified JSONs parse (php json_decode check clean), no duplicate widgets per file, none empty. All replacements map to active capabilities (matrix test enforces; verified `approval_flow:true` in custom/gym/kursus/cuci_mobil/katering/kos_coworking). Removed widgets (kpi_revenue, projects_progress, prescription_queue, etc.) were unrenderable placeholders pre-C3 — removal correct, not lost intent; cash-flow visibility retained via universal KPI. |
| 5 | PARITY displayName | **CONCERN** | Contract added (app/Contracts/CompanyContext.php:17); JSON reads business_identity.json name w/ slug title-case fallback (JsonCompanyContext.php:59-68); Eloquent reads `Company->name` (EloquentCompanyContext.php:29); composer uses it (DashboardComposer.php:51); parity test asserts both (CompanyDisplayNameParityTest.php:31-87). Gaps: F3, F4. |
| 6 | REGRESSION tests adapted vs weakened | **PASS** | Mutation-safety assertions retained: DataErasureTest.php:188-192 (`assertDatabaseHas` contacts + `assertDatabaseMissing` access_logs), DataExportTest `Queue::assertNotPushed` kept. Change is behavior-correct: 403-via-component now unreachable because context throws first. Minor nit F5. |
| 7 | D-31 industry names in code | **PASS** | Diff scan of app/ + resources/: zero industry names introduced. Occurrences only in test fixtures/scripts: smoke-e2e.php ('Warung Kopi E2E' fixture, pre-existing reformat), smoke_browser_c5.py ('bengkel smoke' body check), test preset keys `bengkel`/`klinik` (legit preset keys). |

## (b) Findings

**F1 — LOW — MQ-01C3**: `PresetDefinitionValidator::WIDGETS` (app/Services/Preset/PresetDefinitionValidator.php:46-52) retains 14 widget keys (`kpi_revenue`, `projects_progress`, `prescription_queue`, `timesheet_summary`, `retention_held`, `bookings_due_today`, `overdue_returns`, `open_bills`, `expiring_batches`, `resources_status`, `kpi_receivables_due`, `ai_report_card`, `vendor_settlement`, …) that are always rejected at line 319 and unreachable at runtime. Dead list invites drift: a future widget added to `WidgetCapabilityMap` but not the const is validator-rejected while runtime-valid, silently violating single-source.
Repro: add `'new_widget'` to map → seeder validation fails though `WidgetRegistry` can render it.
Amendment (C3): replace line 312 check `in_array($widget, self::WIDGETS)` with `WidgetCapabilityMap::widgets()` (or delete stale const entries).

**F2 — MEDIUM — MQ-01C2**: Impersonation sessions have no expiry or revocation lifecycle. `admin_impersonation_sessions` table has no `expires_at`/`revoked` (database/migrations/2026_09_18_150000_create_admin_impersonation_sessions_table.php:11-19); validation checks existence only (EloquentCompanyContext.php:100-104). Row persists indefinitely if `stop()` (AdminImpersonationController.php:34-46) is never invoked (admin closes tab instead of clicking "Akhiri Sesi"). Not a forgery hole — grants no privilege beyond what superadmin already created — but a stale-permanent-access gap.
Amendment (C2): add `expires_at` column + `->where('expires_at', '>', now())` to both checks (EloquentCompanyContext.php, EnsureCompanyAccess.php:49), and delete-on-logout hook.

**F3 — LOW — MQ-01C4**: Parity gap: Eloquent `displayName()` returns `''` when `Company->name` is empty string (EloquentCompanyContext.php:29); JSON driver falls back to slug title-case (JsonCompanyContext.php:67). Blank identity in UI possible.
Amendment (C4): `$name = trim((string) $this->getCompany()->name); return $name !== '' ? $name : /* fallback */;`

**F4 — LOW — MQ-01C4**: Impersonation banner bypasses the contract: resources/views/layouts/app.blade.php:26 uses `$context->getCompany()->name` instead of `displayName()` (contract method now exists; the `method_exists` guard reason is obsolete post-C4). Eloquent-only path, so currently harmless.
Amendment (C4): call `app(CompanyContext::class)->displayName()` inside existing try/catch.

**F5 — LOW — MQ-01C2 (tests)**: `DataErasureTest::test_erasure_tab_is_absent_from_staff_dom` (tests/Feature/DataErasureTest.php:209-226) no longer touches DOM or `/app/settings` — only asserts context throws. Test name lies. HTTP-level 403 is covered separately by LivewireTenantMiddlewareTest.php:39, so no coverage hole.
Amendment (C2): rename to `test_intruder_cannot_set_foreign_company_context`.

## (c) Overall verdict

**LAYAK** — no HIGH findings; money and tenant fail-closed claims hold under inspection and are test-proven; presets schema-valid and sensible. F1–F5 are non-blocking hygiene items.

## (d) Ownership recap

| Finding | Slice | Amendment |
|---|---|---|
| F1 | C3 | Derive validator widget list from `WidgetCapabilityMap` |
| F2 | C2 | `expires_at` on impersonation sessions + check in both validators |
| F3 | C4 | Empty-name fallback in Eloquent `displayName()` |
| F4 | C4 | Use `displayName()` in app.blade.php banner |
| F5 | C2 | Rename misleading DataErasure test |
