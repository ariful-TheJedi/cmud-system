# CMUD ERP — Seed Data Plan

> **Status:** Schema inventory and local development dataset verified on 2026-11-12; application workflows remain pending  
> **Scope:** Development data only  
> **Method:** Direct Laravel Seeders and explicit records; **no factories**  
> **Database:** PostgreSQL  
> **Companion:** [`SEED_SCENARIOS.md`](SEED_SCENARIOS.md)  
> **Authority:** Current Laravel migrations, constraints, models and implemented business rules override this planning document.

## 1. Objective

Build a reusable, deterministic, realistic-but-fictional dataset for developing and demonstrating CMUD ERP features, checking REST API behavior, and manually verifying the UI. This plan defines **what** to seed, expected volume, data ownership, and dependency checks. The companion scenarios document defines **which concrete workflows and expected outcomes** must be represented.

**Do not create factories.** Store the predefined dataset directly in maintainable Laravel seeder classes or private arrays/data files used exclusively by those seeders. Do not fill tables merely to achieve a target count; every record must satisfy schema and business invariants.

## 2. Schema inventory and reconciliation

The verified inventory contains **52 application tables**: 12 Institute & Operations, 18 Academy, 16 Finance, and 6 Access & Auditing. The database document's earlier module subtotals were incorrect; the corrected counts are reflected here. `branch_teacher` belongs to Academy. The live PostgreSQL `public` schema contains all 52 names plus Laravel's `migrations` metadata table; all 11 project migrations are recorded as applied. The live schema uses `student_other_payments`, renamed from `student_expense_payments`.

Before proceeding, Codex must record:

- The migration/live table reconciliation is complete: 52 application tables match; `student_expense_payments` was renamed; `migrations` is framework-owned.
- The Laravel environment is `local`, connection `pgsql`, database `cmud_system`, PostgreSQL 18.4, schema `public`. Read-only row counts reported zero rows in the application tables at review time. Do not infer data safety for a different target from this local result.
- Migrations define primary/unique keys and FKs; most status/type fields are unconstrained strings. `courses.course_mode` is the only enum-like field, stored as `varchar` with an allowed-value check. No database checks encode the proposed amount, progression, stock, approval, or effective-date rules.
- FK order and implemented-vs-absent rules are recorded in section 5 and `SEED_SCENARIOS.md` section 3.1. No module service/controller or validation workflow was found in the module route stubs.
- The local SQLite file is not schema authority.

**Gate:** The approved development dataset was executed against the verified local PostgreSQL `cmud_system` database. Source-row consistency and repeatability checks are recorded in `SEED_SCENARIOS.md`; unsupported application workflows remain pending.

## 3. Dataset design

### 3.1 Three datasets within development seeding

| Dataset | Goal | Identity strategy |
|---|---|---|
| Reference | Stable institutes, branches, academic offerings, methods, categories and permission definitions | Reuse existing natural unique keys; create only missing approved development records |
| Operational | Coherent stuff, student, enrollment, tuition, exam, inventory and accounting histories | Stable test-only business identifiers where supported; parent FK IDs resolved from persisted parents |
| Anchored scenarios | Small, easily findable records that exercise defined business cases | Stable scenario IDs in documentation and stable record lookup keys |

Use fake non-contactable personal data and neutral/placeholder addresses; do not seed real student records, valid passwords in documentation, real financial account credentials, or real mobile payment transaction IDs. Use Bangladeshi formats only when validation supports safe synthetic values.

### 3.2 Initial volume targets (proposals, not guarantees)

- 1 fictional development institute / institute record consistent with the schema; 3 branches if supported.
- ~12 stuff, ~6 teachers, ~8 courses, ~23 batches (including 8 open future cohorts for admission-form checks), ~20 medical college reference records.
- ~200 students; ~250 enrollments where the business rules allow multiple enrollments.
- Associated financial/academic/stock records driven by the scenario distributions and actual relationships.
- Zero seeded sessions or password-reset tokens by default.

**Dataset tiers:** `core` = deterministic records for the 26 documented scenario shapes where useful schema rows can be represented. Access scenarios seed users and groups only; permissions and protected behavior are unsupported. The audit scenario is intentionally omitted because no genuine audit writer exists. `demo` = core plus ~200 students; `volume` = optional later generation **using explicit seeder logic, not factories**, only when requested. Core rows are a reviewable proposal until the explicit seeder is run and verified.

### 3.3 Ownership and insertion principles

- Each table has **one logical owning seeder** to avoid duplicate ownership; cross-module relationships are coordinated by a master development seeder.
- Seed parent/reference records before dependent operational records according to a FK-derived **topological order**. The module sequence below is indicative, **not a guaranteed insertion order**.
- Map FK references through stable existing keys rather than assumed IDs (`1`, `2`, etc.). Use IDs only after looking up parents.
- Prefer idempotent `firstOrCreate` / `updateOrCreate` / carefully scoped `upsert` for suitable stable entities; for immutable financial, stock and audit events use a stable transaction/reference key or explicit existence check backed by a unique constraint. If idempotence cannot be guaranteed, **stop rather than duplicate history**.
- Preserve manually created development data. No bulk deletes, truncate, `migrate:fresh`, `db:wipe`, unchecked overrides, or production writes.
- Data that is derived in the application (balances, outstanding dues, totals, stock on hand) must be computed consistently from its source records, not independently randomized.
- Respect the **no-refund** policy currently documented for CMUD; do not introduce refund rows or workflows absent from the schema.
- Keep course offerings, academic stages, prices, and certificate eligibility aligned with implemented rules; never assume every course has multiple stages.

## 4. Table-by-table seed specification

**Legend:** `Ref` = reference data, `Op` = operational data, `Evt` = historical/transaction data, `Runtime` = normally unseeded. Proposed quantities are editable targets, not prescribed record counts. Verified FK parents are specified in section 5; status, calculation, cross-row and eligibility behavior is not established unless explicitly stated.

### 4.1 Institute & Operations — 12 tables

| Table | Kind | Initial target | Dependency / implementation note | Scenarios |
|---|---|---:|---|---|
| `institutes` | Ref | 1 | Root organization; `code` is not unique in the schema | INS-001 |
| `branches` | Ref | 3 | Required FK to institute; branch `code` is not unique | INS-001 |
| `departments` | Ref | 4–6 | Required FK to institute; no branch FK exists | INS-001 |
| `rooms` | Ref | 6–12 | Required FK to branch; no department FK exists | INS-001 |
| `stuff` | Op | 12 | Required institute FK; optional unique `user_id` uses `SET NULL` on user deletion | INS-002 |
| `stuff_assignments` | Op | 12–18 | Required stuff, branch and department FKs; no dates or pair uniqueness | INS-002 |
| `asset_categories` | Ref | 4–6 | Required institute FK | INS-003 |
| `assets` | Op | 12–25 | Required category and branch FKs; optional room FK; no same-branch check | INS-003 |
| `stocks` | Op | 10–20 | Required branch FK; `quantity_on_hand` stored separately from movements | INS-004/005 |
| `stock_movements` | Evt | 25–50 | Required stock FK, optional price-version FK; movement type is an unconstrained string | INS-004/005 |
| `stock_price_versions` | Evt | 10–25 | Required stock FK; unique stock/version; optional category and approver; no date-overlap rule | INS-006 |
| `stock_categories` | Ref | 4–8 | No parent FK; unique category name; referenced optionally by stock price versions | INS-004 |

### 4.2 Academy — 18 explicitly named tables

| Table | Kind | Initial target | Dependency / implementation note | Scenarios |
|---|---|---:|---|---|
| `students` | Op | 200 | Required institute and admission-branch FKs; optional college; unique `student_code` | ACA-001/002/003 |
| `medical_colleges` | Ref | 20 | Root reference table; name has no unique constraint | ACA-001 |
| `courses` | Ref | 8 | Required institute FK; course code is not unique | ACA-002/004 |
| `course_stages` | Ref | 8–14 | Required course FK; no unique stage code/sequence constraint | ACA-004 |
| `batches` | Op | 23 | Required course and branch FKs; includes eight open future admission cohorts, no stage FK or date-order constraint | ACA-002/004/010 |
| `enrollments` | Op | 250 | Required student/course/branch, optional batch; only enrollment code is unique | ACA-002/003 |
| `student_stage_records` | Op | 100–200 | Required enrollment, stage and batch FKs; no same-course or progression constraint | ACA-004 |
| `exam_records` | Op | 80–150 | Required stage-record FK; marks nullable and result status is a string | ACA-005 |
| `certificate_templates` | Ref | 2–5 | Required institute FK; optional course FK | ACA-006 |
| `certificates` | Op | 30–70 | Required enrollment and template FKs; nullable unique certificate number; no eligibility constraint | ACA-006 |
| `communication_groups` | Op | 10–20 | Required institute; optional branch/course/batch FKs | ACA-007 |
| `student_group_memberships` | Op/pivot | 120–220 | Required student/group FKs; pair is not unique | ACA-007 |
| `teachers` | Op | 6 | Required institute; optional stuff FK; unique teacher code | ACA-008 |
| `course_price_versions` | Evt | 8–16 | Course FK; optional branch/approver; scoped version-number uniqueness, no effective-date overlap check | ACA-009 |
| `subjects` | Ref | 15–25 | Module with required course FK after catalog assignment; unique subject code | ACA-008 |
| `subject_teacher` | Op/pivot | 20–40 | Required teacher/module FKs; unique pair | ACA-008 |
| `branch_teacher` | Op/pivot | 6–12 | Required branch/teacher FKs; unique pair (`branch_id`, `teacher_id`) | ACA-008 |

### 4.3 Finance — 16 explicitly named tables

| Table | Kind | Initial target | Dependency / implementation note | Scenarios |
|---|---|---:|---|---|
| `student_tuitions` | Op | 200–250 | Unique enrollment FK (one tuition per enrollment); course-price FK; waiver fields have no calculation/approval constraint | FIN-001–004 |
| `student_tuition_payments` | Evt | 300–500 | Required tuition, method, account and recorder FKs; amount/status have no payment or overpayment constraint | FIN-001–004 |
| `student_expense_types` | Ref | 4–8 | Required institute FK; code/name not unique | FIN-005 |
| `student_expense_price_versions` | Evt | 4–12 | Required type FK; optional branch/course/stage/approver; no date-overlap constraint | FIN-005 |
| `student_expenses` | Op | 50–100 | Required student/branch/type/price/recorder; optional enrollment/stage/waiver approver | FIN-005 |
| `student_other_payments` | Evt | 40–100 | Current PostgreSQL name; required expense/method/account/recorder and optional verifier FKs; model explicitly maps to this table | FIN-005 |
| `institute_expense_types` | Ref | 4–6 | Selectable expense type; required institute FK | FIN-006 |
| `institute_expense_categories` | Ref | 8–12 | Billable category/item; required expense type FK | FIN-006 |
| `institute_expense_price_versions` | Evt | 8–12 | Required category/item FK; optional branch/approver; no date-overlap constraint | FIN-006 |
| `institute_expenses` | Op | 20–40 | Required expense type/category; optional recorder after the billing migration; optional branch, department, price version, stuff, teacher, asset and approver | FIN-006 |
| `institute_expense_payments` | Evt | 20–40 | Required expense/method/account/recorder; optional verifier; no settlement constraint | FIN-006 |
| `payment_methods` | Ref | 3–5 | Required institute FK; unique code; capability flags are stored booleans only | FIN-001 |
| `financial_accounts` | Ref/Op | 3–6 | Required institute FK, optional branch; unique code; `opening_balance_as_of` is required | FIN-007 |
| `account_transfers` | Evt | 5–12 | Required source/destination accounts and recorder; optional approver/confirmer; no distinct-account or ledger rule | FIN-007 |
| `institute_income_bill` | Op | 10–20 | Singular table name; required recorder and optional approver; JSONB bill lines have no DB validation | FIN-008 |
| `institute_other_income_bill_payment` | Evt | 10–20 | Required bill/method/account/recorder; optional receiver/verifier; no paid-state or overpayment constraint | FIN-008 |

### 4.4 Access & Auditing — 6 tables

| Table | Kind | Initial target | Dependency / implementation note | Scenarios |
|---|---|---:|---|---|
| `users` | Op | 4–6 | Unique email; optional permission group; `is_super_admin`/`is_active` flags; no user-permission pivot | SEC-001/002 |
| `permission_groups` | Ref | As defined | Unique group name; parent of users and permissions | SEC-001/002 |
| `permissions` | Ref | As defined | Required group FK; unique (`name`, `guard_name`); current permission seeder is a stub | SEC-001/002 |
| `sessions` | Runtime | **0** | Runtime-managed; do not seed by default | — |
| `password_reset_tokens` | Runtime | **0** | Runtime-managed; do not seed by default | — |
| `audit_logs` | Evt | 0 or 5–15 | Optional actor FK and polymorphic subject fields; no writer found, so keep empty unless genuine app events are implemented | SEC-003 |

## 5. Verified dependency and insertion order

This order is derived from migration foreign keys. Nullable edges are optional but, when populated, require the same parent-first order. There are no FK cycles. `stuff.user_id` is nullable/unique and has `ON DELETE SET NULL`; it does not create a cycle because `users` does not reference `stuff`.

| Phase | Tables | Required parents / notes |
|---|---|---|
| 1. Roots | `permission_groups`, `institutes`, `medical_colleges`, `stock_categories` | No application-table parents. |
| 2. Shared principals and references | `users`, `permissions`, `branches`, `departments`, `courses`, `asset_categories`, `student_expense_types`, `institute_expense_types`, `payment_methods` | `users` optionally references `permission_groups`; `permissions` requires `permission_groups`; the other listed organization references require `institutes`. |
| 3. Stuff, location and catalog children | `rooms`, `stuff`, `course_stages`, `batches`, `teachers`, `institute_expense_categories`, `financial_accounts`, `stocks`, `certificate_templates` | Parents respectively: branch; institute and optional user; course; course+branch; institute+optional stuff; expense type; institute+optional branch; branch; institute+optional course. |
| 4. Price and assignment records | `stuff_assignments`, `assets`, `branch_teacher`, `subject_teacher`, `course_price_versions`, `stock_price_versions`, `student_expense_price_versions`, `institute_expense_price_versions` | Require stuff/branch/department; category/location; branch+teacher; module+teacher; course+optional branch/user; stock+optional category/user; expense type+optional branch/course/stage/user; institute expense category/item+optional branch/user. |
| 5. Students and cohorts | `students`, `communication_groups`, `enrollments` | Students require institute+admission branch and optional college. Groups require institute and optional branch/course/batch. Enrollments require student+course+branch and optional batch. |
| 6. Academic progression | `student_stage_records`, `exam_records`, `certificates`, `student_group_memberships` | Stage records require enrollment+course stage+batch; exams require stage record; certificates require enrollment+template; memberships require student+group. |
| 7. Student finance | `student_tuitions`, `student_expenses` | Tuition requires enrollment+course price version and optional waiver approver. Expenses require student+branch+expense type+price version+recorder and optional enrollment, stage record and waiver approver. |
| 8. Payments and accounting history | `student_tuition_payments`, `student_other_payments`, `institute_expenses`, `account_transfers`, `institute_income_bill` | Student payments require tuition/expense, method, account, recorder and optional verifier. Institute expenses require expense type+category/item and optional recorder, branch, department, price version, stuff, teacher, asset, approver. Transfers require two accounts+recorder and optional approver/confirmer. Income bills require recorder and optional approver. |
| 9. Remaining payment/inventory/audit events | `stock_movements`, `institute_expense_payments`, `institute_other_income_bill_payment`, `audit_logs` | Stock movements require stock and optionally a stock price version; expense payments require expense+method+account+recorder; bill payments require bill+method+account+recorder and optional receiver/verifier; audit rows optionally reference a user. |
| Runtime-managed | `sessions`, `password_reset_tokens` | Leave empty; runtime records reference users. |

The schema has no FK tying an asset's `room_id` to the same `branch_id`, no composite check ensuring a batch belongs to an enrollment's course/branch, and no uniqueness rule for several apparent pivots. Seeders must resolve those cross-row consistency decisions explicitly and must not present them as database-enforced.

## 6. Seeder file organization

```text
# Inside Laravel project root
database/
├── AGENTS.md
├── SEED_DATA_PLAN.md
├── SEED_SCENARIOS.md
└── seeders/
    ├── DatabaseSeeder.php
    └── Development/
        ├── CoreDevelopmentSeeder.php
        ├── UsesCoreSeedData.php
        ├── CoreAccessSeeder.php
        ├── CoreInstituteSeeder.php
        ├── InstituteVolumeSeeder.php
        ├── CoreAcademySeeder.php
        ├── AcademyFoundationSeeder.php
        ├── AcademyVolumeSeeder.php
        ├── EnrollmentOverviewSeeder.php
        ├── CoreFinanceSeeder.php
        └── FinanceVolumeSeeder.php
```

`AcademyFoundationSeeder` requires the Institute anchors (`CMUD-DEV-CORE`, its three development branches, and `DEV-STUFF-002`) and the Access approver (`core.operator@example.invalid`). It fails with the missing lookup keys instead of creating those parent records. It runs `CoreAcademySeeder` followed by `AcademyVolumeSeeder`; the parent and volume seeders reject environments other than `local` and `testing`. The Academy seeder is not wired into `DatabaseSeeder`.

The full development sequence is Access, Institute core, Institute volume, Academy foundation (core anchors then Academy volume), Finance core, and Finance volume. The Institute and Finance volume seeders extend only namespaced volume rows and preserve core scenario identifiers. The explicit full-core command is `php artisan db:seed --class="Database\\Seeders\\Development\\CoreDevelopmentSeeder"`; it is guarded to `local` and `testing` and is not wired into `DatabaseSeeder`. Observed execution and verification are recorded in `SEED_SCENARIOS.md`.

For the academic and certificate overview on the current student, run `php artisan db:seed --class="Database\\Seeders\\Development\\EnrollmentOverviewSeeder"`. This development-only seeder adds stage records and an ineligible certificate to existing student `202611001` and enrollment `ENR-E3915C70-8950-418A-B55D-F898FF8E19EC`; it does not create another student or enrollment and is not wired into `DatabaseSeeder`.

## 7. Scenario linkage and fixed sample records

`SEED_SCENARIOS.md` is authoritative for scenarios. For each scenario, seeders must provide:

1. Stable scenario ID (documentation), a real lookup key in each affected table, and the parent chain.
2. Explicit input amounts/dates/status values supported by the schema.
3. Expected database state and computed output.
4. A reproducible manual/API verification path when that feature exists.
5. Re-run behavior (same records, no duplicate immutable transactions).

Identifiers such as `CMUD-TEST-001` are **examples only**: inspect length, pattern, and unique constraints before choosing actual student codes. Never add a new DB column solely to store scenario IDs.

## 8. Execution gates and verification

### Gate A — Plan readiness

- [x] Actual PostgreSQL schema and all 52 documented names reconciled (2026-11-12).
- [x] Migration FK hierarchy and declared unique constraints reviewed; no domain checks exist for proposed workflow invariants.
- [x] `StudentExpensePayment` maps to live `student_other_payments`; affected Eloquent relations have a focused unit test.
- [ ] Confirm application rules for balances, statuses, price effective dates, academic progression, approvals and permissions; no implemented module workflows were found.
- [x] Unsupported scenarios and assumptions are called out in `SEED_SCENARIOS.md`.
- [x] Core development seeder and seed integrity/repeatability Pest test are implemented; execution is deferred until review.

### Gate B — Safe execution

- [x] Confirm Laravel environment and database **name/host** indicate the authorized local development database (`cmud_system`, `127.0.0.1:5432`).
- [x] Inspect existing data; application tables were empty except two preserved runtime session rows.
- [ ] Prepare backup/restore approach if the target has valuable non-test data.
- [x] Execute the scoped development master seeder only against the approved local target.
- [ ] Never run `migrate:fresh`, `db:wipe`, truncation or production seeding.

### Gate C — Data verification

- [x] Report observed counts per table; insert/update/skip deltas are not reported by these upsert seeders.
- [x] Verify database foreign-key constraints, uniqueness and seeded cross-row scopes.
- [x] Verify scenario source rows and record-level expected amounts; application calculation workflows remain unverified.
- [x] Verify financial source totals and inventory movement consistency.
- [ ] Run Pest tests against a **separate test database**, using independent fixtures/direct test seeders; no reliance on mutable development state.
- [x] Rerun development seeders and verify no further count changes or duplicate payment keys after correcting Academy volume pivot insertion.
- [x] Report checks run and unimplemented UI/application behavior.

## 9. Maintenance protocol

When adding features, update **only** the affected rows, scenario entries, seeders and associated Pest checks. Preserve anchored IDs; migrate them intentionally if a schema change forces a rename. Record changes below.

| Date | Change | Affected tables/scenarios | Verification |
|---|---|---|---|
| 2026-11-12 | Verified schema and FK schedule; implemented direct core development seeders and Pest integrity/repeatability coverage | All scenario-backed tables | PHP syntax/static checks only; seeders and DB-writing tests not executed pending review |
| 2026-11-12 | Added Institute and Finance volume seeders and connected the full development insertion order | Institute & Operations; Finance; all documented scenarios remain anchored | PHP syntax/class loading and static count/dependency review only; no tests or database writes |
| 2026-11-12 | Executed and verified the complete development dataset; corrected the Academy volume pivot guard after the first rerun added 17 extra valid pairs | All four foundations; SD-INS/ACA/FIN/SEC | Local PostgreSQL source-row checks and final rerun; details in `SEED_SCENARIOS.md`; no Pest tests |

## 10. Deliverable/report format for Codex

After each task, report: schema discrepancies; created/edited files; counts inserted/updated/skipped per table; scenario IDs present; idempotence results; SQL/Pest outcomes; blockers; and whether any database write occurred. Do not claim verification without executing the relevant check.

## 11. Verified schema facts and unresolved blockers

- **Live schema:** PostgreSQL 18.4, connection `pgsql`, database `cmud_system`, schema `public`, Laravel environment `local`; all 11 project migrations report `Ran`. All 52 application table names match the live schema. The framework `migrations` table is additional. The SQLite file was not used for verification.
- **Current data:** Read-only table counts reported no rows in the application tables at review time. Recheck before any future seeding run.
- **Renamed payment model:** `App\Models\Finance\StudentExpensePayment` explicitly maps to the live `student_other_payments` table. `StudentExpense::payments()`, `PaymentMethod`, `FinancialAccount`, and both `User` payment relations use that model; a focused unit test checks each relation's table and FK. Payment business calculations remain pending.
- **Implemented behavior:** The four module API route files are stubs, with no module controllers/services/request validators found. Existing setup/demo seeders are mostly stubs. `PaymentMethodSeeder` is implemented but needs an institute row; current setup does not create one. The local application tables are empty.
- **Constraints:** The database enforces declared primary/unique keys and foreign keys, not proposed application invariants. In particular, status/type values (except `courses.course_mode`) are strings; no amount positivity/overpayment, status transition, stock balance, effective-date overlap, academic progression, certificate eligibility or transfer ledger checks are present in migrations.
- **Uniqueness relevant to scenarios:** `student_tuitions.enrollment_id` is unique (one tuition per enrollment); `branch_teacher` has a unique branch/teacher pair; `subject_teacher` has a unique subject/teacher pair. Course price versions have scope-aware partial unique indexes on version number, but no date-overlap constraint.
- **Factories:** Four model factories already exist in the repository. The database agent instructions prohibit using or adding factories; this plan does not authorize changing or invoking them.
- **Instruction filename:** The repository instructions are now at `database/AGENTS.md`; references use the plural filename.
- **Readiness:** Schema inventory and FK sequencing are verified. Core seeder code is implemented but not executed; its DB integrity and rerun test must run against a separate test database after review. Seed volume targets remain proposals; business outcomes in the scenarios are not validated until their owning workflows are implemented. No factories, migrations or database records were changed.
