# CMUD ERP â€” Seed Scenarios

> **Status:** Development dataset executed and database-verified on 2026-11-12; application workflows are largely unimplemented  
> **Companion:** [`SEED_DATA_PLAN.md`](SEED_DATA_PLAN.md)  
> **Method:** Deterministic explicit records in Laravel Seeders; **no factories**  
> **Reminder:** These are candidate data shapes, not proof of business workflows. The live PostgreSQL schema and migrations were reviewed; unsupported behavior is listed in section 3.1. Do not claim API/UI verification where no routes or screens exist.

## 1. Purpose and separation of responsibilities

This file defines **business cases** for a realistic, reusable development dataset. The data plan documents table coverage, record counts and dependency order; this document describes **what an identifiable seeded example must demonstrate** and its **expected outcome**. Successful insertion is not proof that a route or UI works.

For a new feature, add or update scenarios here rather than adding detailed scenario definitions to `AGENTS.md` or duplicating them in the data plan.

## 2. Conventions

- **Scenario ID:** `SD-INS-###`, `SD-ACA-###`, `SD-FIN-###`, or `SD-SEC-###` (never recycle an ID).
- **Status:** `Proposed` â†’ `Schema-confirmed` â†’ `Seeded` â†’ `DB-verified` â†’ `API-verified` â†’ `UI-verified` (only after each check actually runs). `Blocked` is allowed with a reason.
- **Scenario lookup:** stable **existing** business key (e.g., student code, course code, transaction reference, or an unambiguous key tuple). Do not assume an `id` or new `scenario_id` column.
- **Dates:** use explicit, internally consistent historical dates; honor effective-date and lifecycle constraints.
- **Amounts:** BDT currency, decimal-safe representation appropriate for column types; don't use floating-point arithmetic for asserted monetary totals.
- **Safety:** fictional values only; no real student data, real payment transaction IDs or production credentials.
- **Negative input scenarios:** keep invalid FK, duplicate unique key, unauthorized action and invalid amount attempts in isolated **Pest tests**, not the permanent seeded development dataset.

## 3. Scenario registry

`DB-verified` means the seeded rows and stated database-level checks were verified; it does not mean application workflow behavior passed. `Seeded` marks partial records that do not represent the full scenario, and `Blocked` records scenarios intentionally omitted because a required implementation is absent. API/UI and application behavior remain separate verification layers.

| ID | Area | Business case | Core expected result | Status |
|---|---|---|---|---|
| SD-INS-001 | Institute | Multi-branch organizational structure | Rooms and departments resolve to valid owners | DB-verified |
| SD-INS-002 | Institute | Stuff/teacher branch assignments | Assignment links and scope are consistent | DB-verified |
| SD-INS-003 | Institute | Asset assigned to organization/location | Asset category and owner/location resolve | DB-verified |
| SD-INS-004 | Inventory | Stock received | Expected available quantity from valid movement history | DB-verified |
| SD-INS-005 | Inventory | Stock issued | Expected decrement without negative or invalid quantity | DB-verified |
| SD-INS-006 | Inventory | Stock price history | Correct applicable price for selected date | DB-verified |
| SD-ACA-001 | Academy | Registered student not enrolled | Student exists; enrollment count zero | DB-verified |
| SD-ACA-002 | Academy | One active valid enrollment | Student/course/batch links consistent | DB-verified |
| SD-ACA-003 | Academy | Same student on two courses | Two distinct valid enrollments | DB-verified |
| SD-ACA-004 | Academy | Multi-stage course progression | Stage records align to actual course stages | DB-verified |
| SD-ACA-005 | Academy | Passed and failed examination records | Independent, correctly linked exam outcomes | DB-verified |
| SD-ACA-006 | Academy | Completed / certificate pending vs issued | Certificate state respects eligibility and schema | DB-verified |
| SD-ACA-007 | Academy | Communication group membership | Valid group/student link and no duplicate membership | DB-verified |
| SD-ACA-008 | Academy | Teacher, subject and course allocation | Pivot relationships are valid | DB-verified |
| SD-ACA-009 | Academy | Course price version change | Correct fee version selected by effective date | DB-verified |
| SD-ACA-010 | Academy | Open course batches for admission form | One active future cohort per demo course and branch | Seed code added; execution pending |
| SD-ACA-012 | Academy | Current enrollment academic overview | Existing student enrollment has three stages, no exam attempts, and an ineligible certificate | Seed code added; execution pending |
| SD-ACA-013 | Academy | Enrollment-generated course certificate | A second course enrollment for the current student receives a not-eligible certificate from its active course template | Seed code added; execution pending |
| SD-FIN-001 | Finance | Fully paid tuition | Adjusted tuition minus confirmed payments = 0 | DB-verified |
| SD-FIN-002 | Finance | Partially paid tuition | Expected positive outstanding due | DB-verified |
| SD-FIN-003 | Finance | Waiver and installment payments | Correct adjusted tuition and payment sum | DB-verified |
| SD-FIN-004 | Finance | Unpaid tuition | Due equals adjusted tuition | DB-verified |
| SD-FIN-005 | Finance | Student additional expense / payment | Correct amount outstanding on supported expense records | DB-verified |
| SD-FIN-006 | Finance | Institute expense and associated payment | Consistent bill/expense payment status | DB-verified |
| SD-FIN-007 | Finance | Transfer between financial accounts | Transfer links and ledger totals are coherent | DB-verified |
| SD-FIN-008 | Finance | Institute other income bill and payment | Income payment correctly belongs to bill | DB-verified |
| SD-SEC-001 | Access | Authorized development user | Allowed action succeeds through actual permission checks | Seeded |
| SD-SEC-002 | Access | Restricted development user | Unauthorized action denied by actual route policy | Seeded |
| SD-SEC-003 | Auditing | Auditable state change | Genuine audit behavior recorded when implemented | Blocked: no audit writer |

### 3.1 Verified support and blockers

Inspection covered the current migrations, Eloquent models, module route files, service/controller locations, existing seeders and the configured PostgreSQL schema. The four module API route files are stubs; no module controllers, services or request validators implement the listed workflows. Student page components exist, but no corresponding web routes/controllers were found. `DatabaseSeeder` currently calls setup/demo seeders that are stubs, apart from reference payment-method setup (which creates nothing if no institute exists). Before this execution, all application tables except `sessions` were empty; two pre-existing runtime session rows were preserved.

| Scenario(s) | Schema support | Implementation support / required correction |
|---|---|---|
| SD-INS-001 | Institute, branches, departments, rooms FKs support valid parent records. Departments reference the institute, not a branch. | Branch-filtered department behavior is unsupported. Rooms do reference branches. |
| SD-INS-002 | Stuff assignments require stuff+branch+department; teachers may link to stuff; `branch_teacher` has a unique branch/teacher pair. | Assignment effective dates do not exist in `stuff_assignments`; overlap checks and application behavior are unsupported. |
| SD-INS-003 | Asset requires category+branch and optionally room. | No DB rule ensures room belongs to the asset's branch; asset category has institute ownership but assets have no institute FK. Cross-scope validation is unsupported. |
| SD-INS-004, SD-INS-005 | Stock, movement and price records can be related through FKs. | Movement types are strings; `quantity_on_hand` is stored independently; no movement calculation or nonnegative-stock validation was found. Expected closing balances 150/120 are unsupported. |
| SD-INS-006 | Stock price versions have a unique `(stock_id, version_number)` pair; category and approver are optional FKs. | No effective-date overlap rule or date-based price selector was found. |
| SD-ACA-001 | A student can exist without an enrollment; the enrollment FK points to student. | Schema-supported record shape; student page components are not connected to a module route/controller workflow. |
| SD-ACA-002, SD-ACA-003 | Multiple enrollment rows per student are allowed; only `enrollment_code` is unique. `batch_id` is nullable. | Duplicate-course policy and student/course/batch scope validation are not implemented. Two courses are representable but not a verified business policy. Each enrollment can have at most one tuition because `student_tuitions.enrollment_id` is unique. |
| SD-ACA-004 | Stage records reference enrollment, course stage and batch. | No constraint ensures the stage belongs to the enrollment's course, validates sequencing, or enforces progression. |
| SD-ACA-005 | Exam rows reference stage records; marks are nullable. | `result_status` is an unconstrained string; no marks bounds, pass/fail calculation or validation exists. |
| SD-ACA-006 | Certificate rows reference enrollment and template; template course is optional. Certificate number is nullable/unique. | No issue eligibility, completion, financial-clearance, status lifecycle or delivery workflow was found. |
| SD-ACA-007 | Membership rows reference students and groups. | No unique student/group pair or eligibility rule exists; duplicate memberships are database-allowed. |
| SD-ACA-008 | `course_subject` has a composite PK; `branch_teacher` has unique branch/teacher; `course_teacher` has no unique pair. | Course/teacher allocation policy and duplicate prevention for `course_teacher` are not implemented. |
| SD-ACA-009 | Course price versions have partial unique indexes for `(course_id, version_number)` at default scope and `(course_id, branch_id, version_number)` at branch scope. | No effective-date overlap check or price selection logic was found; snapshot behavior in tuition is not enforced. |
| SD-FIN-001 through SD-FIN-004 | Tuition amount, waiver, payment and status fields support storing proposed rows. One tuition per enrollment is enforced. | No payment aggregation, waiver approval validation, overpayment prevention, status lifecycle or outstanding-balance calculation was found. Monetary expected dues are proposals, not verified outputs. |
| SD-FIN-005 | Student expenses and payment rows have FK paths; `StudentExpensePayment` now explicitly maps to `student_other_payments`, and its relation paths are covered by a unit test. | **Pending:** expense due calculation and payment workflow are not implemented. |
| SD-FIN-006 | Institute expense and payment FK paths exist. | No approval/payment reconciliation, partial settlement, or outstanding calculation was found. |
| SD-FIN-007 | Transfers reference two financial accounts and recorder; approval/confirmation users are optional. | No distinct-account constraint, balance ledger/posting or transfer lifecycle enforcement was found. The zero-sum result is unsupported. |
| SD-FIN-008 | Bill items are JSONB; payments reference the bill, method and account. | No JSON line validation, sum reconciliation, overpayment prevention or collected-income calculation was found. |
| SD-SEC-001, SD-SEC-002 | Users optionally reference a permission group; permissions belong to groups. There is no user-permission pivot. | **Unsupported:** security module routes are empty, permission seeder is a stub, and no route authorization workflow was found. Scenarios must wait for implemented protected routes and permission checks. |
| SD-SEC-003 | `audit_logs` supports optional actor and polymorphic subject fields. | No automatic audit writer or audited action was found. Do not seed fabricated history as proof of auditing. |

Only `courses.course_mode` is declared with enum values in a migration (`online`, `offline`, `dual`); PostgreSQL stores it as `varchar` with a `CHECK`. Other status/type columns are strings; use values only after the owning feature defines them. No database check constraints enforce positive amounts, permitted transitions, date ordering, stock rules, or academic outcomes. Existing factories are present in the repository, but the database instructions prohibit using or creating them for this work.

## 4. Detailed anchored scenarios

The following examples define proposed inputs and outcomes, not verified business results. Their relational data shapes are schema-supported, but expected calculations and workflows remain pending as recorded in sections 3.1 and 6.

### SD-INS-001 â€” Multi-branch organizational structure

- **Purpose:** Exercise institute, branch, department and room filters.
- **Tables to inspect:** `institutes`, `branches`, `departments`, `rooms`.
- **Setup:** One development institute and three distinct branches; at least one linked room per relevant branch, assuming these FKs exist.
- **Expected:** Branch-filtered queries return only the corresponding rooms/departments **where branch scope actually exists**; every FK points to a real parent.
- **Verify:** FK joins, API filtering if available, UI branch selection if implemented.

### SD-INS-002 â€” Stuff and teacher assignments

- **Purpose:** Demonstrate stuff assignments and teaching assignment scope.
- **Tables to inspect:** `stuff`, `stuff_assignments`, `teachers`, `branch_teacher` and relevant parents.
- **Setup:** At least one stuff member with a valid assignment; one teacher linked to a valid branch where the schema supports it.
- **Expected:** No orphan foreign-key references. `stuff_assignments` has no effective-date columns, so overlap behavior is out of scope for schema-backed sample data.
- **Verify:** DB joins and available stuff/teacher directory endpoints.

### SD-INS-003 â€” Assigned asset

- **Purpose:** Show assets with valid categories and organization/location ownership.
- **Tables:** `asset_categories`, `assets`, relevant institute/branch/room tables **only where actual FKs exist**.
- **Setup:** One fictional asset in a valid category.
- **Expected:** Asset listing displays valid category and owner/location labels.

### SD-INS-004 â€” Stock received

- **Purpose:** Verify quantity increases from incoming movement.
- **Tables:** `stocks`, `stock_categories`, `stock_movements`.
- **Input example:** Opening available quantity = 100 units; receipt = 50 units.
- **Expected:** Closing available quantity = **150 units**, if the implemented stock model uses opening balance plus inbound movements. Otherwise derive the corresponding model-specific expected value.
- **Verify:** Reconcile stock balance against valid movement records; rerun seeder and ensure receipt is not duplicated.

### SD-INS-005 â€” Stock issued

- **Purpose:** Verify quantity decrease after valid issue.
- **Input example:** Available = 150 units; issue = 30 units.
- **Expected:** Closing available = **120 units**, subject to schema-specific movement rules. No negative stock unless explicitly permitted.
- **Verify:** Movement aggregation and inventory API/UI, if available.

### SD-INS-006 â€” Stock price history

- **Purpose:** Test date-effective stock pricing.
- **Tables:** `stock_price_versions`, `stocks`.
- **Input:** Two valid, nonconflicting price versions with distinct effective periods when supported.
- **Expected:** Pricing lookup for each date uses the correct version; no invalid overlap.

### SD-ACA-001 â€” New registered student without enrollment

- **Purpose:** Test student directory/profile independent of enrollment.
- **Tables:** `students`, relevant `institutes`, `branches`, `medical_colleges` (if referenced).
- **Lookup key example:** `CMUD-TEST-001` **only if allowed by actual code constraints**.
- **Setup:** Student created on valid admission date; no enrollments.
- **Expected:** Exactly one matching student; zero enrollments; valid owner/branch references.
- **Verify:** SQL joins, student GET API, UI profile (if built).

### SD-ACA-011 â€” Inactive student registry filter

- **Purpose:** Provide a stable inactive student for checking the Academy registry status filter.
- **Tables:** `students`, `institutes`, `branches`, and `medical_colleges`.
- **Lookup key:** `DEV-ACA-INACTIVE-001`.
- **Setup:** One fictional inactive student linked to the seeded development institute and Dhaka branch; no enrollment is required.
- **Expected:** Exactly one student with status `inactive` and valid institute, branch, and college references.
- **Verify:** Run the development Academy seeder twice and confirm the stable student code remains unique and unchanged.
- **Status:** Implemented in `CoreAcademySeeder`; not executed against the development database for this change.

### SD-ACA-002 â€” Student with one enrollment

- **Purpose:** Verify student â†’ course â†’ batch enrollment information.
- **Tables:** `students`, `courses`, `batches`, `enrollments`.
- **Setup:** One student with exactly one permitted enrollment in a compatible batch; the core Academy sample uses the `enrolled` status.
- **Expected:** Enrollment exists exactly once and points to the intended course/batch with status `enrolled`; no duplicate course enrollment if forbidden by application rules.

### SD-ACA-003 â€” Student enrolled in two courses

- **Purpose:** Verify CMUD's one-student/multiple-course enrollment policy.
- **Setup:** One student with **two distinct valid course enrollments**.
- **Expected:** One student row; two appropriate enrollment rows; course-specific academic/financial data remain separate where schema requires.
- **Verify:** Join counts, per-course UI and API filters.

### SD-ACA-004 â€” Multi-stage program progression

- **Purpose:** Validate multi-stage courses such as a three-stage DMU program **only if configured in the actual schema**.
- **Tables:** `courses`, `course_stages`, `enrollments`, `student_stage_records`.
- **Setup:** Student has valid progression through earlier stages and current placement in a later stage.
- **Expected:** No stage record references a stage outside its course; sequencing matches implemented progression rules.

### SD-ACA-005 â€” Examination outcomes

- **Purpose:** Show passed and failed outcomes in reports.
- **Tables:** `exam_records` and verified academic parents.
- **Setup:** At least one passed and one failed exam associated with valid eligible records.
- **Expected:** Queries and API filters distinguish outcomes; no invented marks/status enum values.

### SD-ACA-006 â€” Certificate pending vs issued

- **Purpose:** Validate certificate eligibility and lifecycle.
- **Tables:** `certificate_templates`, `certificates`, valid academic and financial parents as applicable.
- **Setup:** One completed student with pending certificate; another with issued/delivered certificate **only if the application supports those states**.
- **Expected:** Certificate records do not contradict actual eligibility, completion or financial-clearance policy; unique serial constraints satisfied.
- **Verify:** DB references and certificate API/UI if implemented.

### SD-ACA-007 â€” Communication group membership

- **Purpose:** Test group assignment and membership filters.
- **Tables:** `communication_groups`, `student_group_memberships`, `students`, and actual related batch/course parent.
- **Expected:** Valid membership FK chain and no duplicate pair where uniqueness is required.

### SD-ACA-008 â€” Teacher, course and subject relations

- **Purpose:** Verify teacher and subject allocations.
- **Tables:** `teachers`, `course_teacher`, `subjects`, `course_subject`, `courses`.
- **Expected:** Course-specific relations query correctly; no duplicate pivot link where prohibited.

### SD-ACA-009 â€” Effective course price version

- **Purpose:** Ensure historic enrollment pricing is consistent with course price version rules.
- **Tables:** `course_price_versions`, `courses`, and `enrollments`/`student_tuitions` if linked.
- **Setup:** Two valid price versions separated by effective dates.
- **Expected:** Historical transactions use the applicable price/version or a preserved tuition snapshot, according to implemented policy.

### SD-ACA-010 â€” Open course batches for admission form

- **Purpose:** Provide selectable demo cohorts when checking student admission.
- **Tables:** `courses`, `batches`, `branches`.
- **Setup:** Eight active batches, one for each seeded demo course; admission is open from 2026-11-13 through 2027-02-28, with starts between March and August 2027.
- **Expected:** Every batch points to its intended active course and branch, and the admission form can show options for multiple courses.
- **Verify:** Seeded records and admission form options. This seeder update has not yet been executed or UI-verified.

### SD-ACA-012 â€” Current enrollment academic overview

- **Feature / route:** Enrollment academic overview and certificate eligibility.
- **Purpose:** Populate academic progress and certificate status on the current student's existing enrollment.
- **Tables:** `students`, `courses`, `course_stages`, `batches`, `enrollments`, `student_stage_records`, `exam_records`, `certificate_templates`, `certificates`.
- **Dependencies:** Existing student `202611001`, enrollment `ENR-E3915C70-8950-418A-B55D-F898FF8E19EC`, its DMU course/batch, and the institute's certificate template.
- **Seed records:** Three stage records with statuses completed, ongoing, and pending; no exam-attempt rows; one certificate record with status `not_eligible` and no certificate number.
- **Expected DB state:** All stage and certificate records link to the current enrollment; no exam rows are inserted.
- **Expected API result:** One of three stages is complete, no exam attempts are listed, and certificate eligibility is `not_eligible` because stages and enrollment remain incomplete.
- **Expected UI behavior:** Open enrollment `ENR-E3915C70-8950-418A-B55D-F898FF8E19EC` for student `202611001`. Academic shows three stages and “No examinations recorded”; Certificates shows the certificate and its `not_eligible` status without eligibility requirement details.
- **Verification:** Pest integration test `tests/Feature/Database/EnrollmentOverviewSeederTest.php`; manual UI verification remains pending.
- **Rerun check:** The three stage records and certificate business key are updated without creating duplicates.
- **Status:** Seed code added; execution pending.

### SD-ACA-013 â€” Enrollment-generated course certificate

- **Purpose:** Verify that enrolling the current student in a second course automatically creates its certificate record.
- **Tables:** `students`, `courses`, `batches`, `enrollments`, `course_stages`, `student_stage_records`, `certificate_templates`, `certificates`.
- **Dependencies:** Student `202611001`, the seeded BLS course, and an active course-specific certificate template.
- **Seed records:** A future BLS cohort and active course-specific template, then a BLS enrollment created through `StudentEnrollmentService`.
- **Expected DB state:** The new enrollment belongs to student `202611001`; its initial stage is created and its certificate uses the BLS template with status `not_eligible` and no certificate number.
- **Expected UI behavior:** The student's enrollment overview shows a second BLS course card with its single-stage examination view and the automatically created certificate status.
- **Rerun check:** The same student/course/batch enrollment and its certificate are reused; no duplicate enrollment or certificate is created.
- **Verification:** Pest integration test `tests/Feature/Database/EnrollmentOverviewSeederTest.php` passes; seeder execution on the user's PostgreSQL database and manual UI verification remain pending.
- **Status:** Seed code added; execution pending.

### SD-FIN-001 â€” Fully paid tuition

- **Purpose:** Verify zero outstanding tuition.
- **Tables:** `student_tuitions`, `student_tuition_payments`, valid enrollment/student parents, `payment_methods` and `financial_accounts` as required.
- **Inputs:** Tuition BDT 40,000; waiver BDT 0; confirmed payments BDT 15,000 + BDT 25,000.
- **Expected:** Adjusted tuition **40,000**; total confirmed paid **40,000**; due **0**.
- **Verify:** Payment aggregation, finance API/UI, repeated-seed idempotence.

### SD-FIN-002 â€” Partially paid tuition

- **Purpose:** Verify correct positive due and due-list visibility.
- **Lookup key example:** `CMUD-TEST-002`, provided student-code validation permits it.
- **Inputs:** Tuition BDT 50,000; waiver BDT 5,000; confirmed payments BDT 12,000 + BDT 8,000.
- **Expected:** Adjusted tuition **45,000**; confirmed payments **20,000**; due **25,000**.
- **Verify:** SQL payment sum, application computation, student profile and due-list filter if implemented.

### SD-FIN-003 â€” Waiver plus multiple installments

- **Purpose:** Verify waiver and installment history without assuming fixed installment amounts.
- **Inputs:** Tuition BDT 60,000; waiver BDT 10,000; confirmed payments BDT 10,000 + BDT 15,000 + BDT 5,000.
- **Expected:** Adjusted tuition **50,000**; paid **30,000**; due **20,000**.
- **Verify:** Correct payment count and financial aggregation.

### SD-FIN-004 â€” Completely unpaid tuition

- **Inputs:** Tuition BDT 35,000; waiver BDT 5,000; payments none.
- **Expected:** Adjusted tuition **30,000**; due **30,000**.
- **Verify:** Due filter and payment-history empty state.

### SD-FIN-005 â€” Student additional charge/payment

- **Purpose:** Test student expense types/prices, assessed charges and associated non-tuition payments.
- **Tables:** `student_expense_types`, `student_expense_price_versions`, `student_expenses`, **`student_other_payments`**.
- **Inputs:** Additional charge BDT 2,000; payment BDT 500 **only if these values are valid under the schema**.
- **Expected:** Remaining charge due **1,500**, if this is how the implemented model computes it; otherwise document the actual rule.
- **Important:** Never refer to the renamed table `student_expense_payments`.

### SD-FIN-006 â€” Institute expense with payment

- **Purpose:** Verify category/type, expense record, price/version where applicable, and expense payment.
- **Tables:** `institute_expense_categories`, `institute_expense_types`, `institute_expense_price_versions`, `institute_expenses`, `institute_expense_payments`.
- **Expected:** Payment sum and outstanding amount (where applicable) reconcile; valid institute/type/category/price/account references.

### SD-FIN-007 â€” Transfer between financial accounts

- **Purpose:** Verify funds transfer without manufacturing or losing money.
- **Tables:** `financial_accounts`, `account_transfers` and any actual ledger tables.
- **Input:** Transfer BDT 5,000 between two distinct eligible fictional accounts.
- **Expected:** Net change across both accounts is **0** before fees; source âˆ’5,000 and destination +5,000, **only when the actual balance model implements those effects**. No double-post on rerun.

### SD-FIN-008 â€” Other institute income bill/payment

- **Purpose:** Verify institutional non-tuition income lifecycle.
- **Tables:** `institute_income_bill`, `institute_other_income_bill_payment` (names exactly as listed).
- **Input example:** BDT 3,000 bill and BDT 3,000 received, if schema supports.
- **Expected:** Payment attached to the correct bill and correct outstanding status; no orphan or duplicated payment.

### SD-SEC-001 â€” Permitted action

- **Purpose:** Verify existing custom permission rules in a real route.
- **Tables:** `users`, `permission_groups`, `permissions` and any **actual** permission-assignment tables discovered in migrations.
- **Setup:** Development-only user with permission needed for a selected existing route.
- **Expected:** Authenticated, authorized request succeeds. Do **not** assume permissions are automatically model- or table-based.
- **Verify:** Pest API test with independent test data; manual dev account if safe.

### SD-SEC-002 â€” Denied action

- **Purpose:** Verify restricted access.
- **Setup:** Separate restricted development-only user; same protected route.
- **Expected:** Unauthorized request receives the application's correct denial response (often HTTP 403, after authentication), while the allowed case succeeds.
- **Verify:** Pest test; do not seed an invalid permission relationship.

### SD-SEC-003 â€” Audit history

- **Purpose:** Validate application-generated audit events where implemented.
- **Tables:** `audit_logs` and actual actor/target FKs.
- **Setup:** If feasible, perform a supported auditable action through the application during tests rather than forging manually authored audit records.
- **Expected:** Appropriate actor, target, action and timestamp if auditing is implemented; otherwise keep application behavior **Pending** until an audit writer exists.

## 5. Repeatable scenario template for future features

Copy this section when adding a scenario:

```markdown
### SD-{AREA}-{NNN} â€” {Short name}

- **Feature / route:** {Verified implementation path, or Not implemented}
- **Purpose:** {Why this case matters}
- **Tables:** {Actual existing table names}
- **Dependencies:** {Stable parent lookup keys}
- **Seed records:** {Exact fictional values and stable lookup keys}
- **Expected DB state:** {Counts, FK associations, totals, stage/status}
- **Expected API result:** {Only when route exists}
- **Expected UI behavior:** {Only when UI exists}
- **Verification:** {SQL assertion + Pest test + manual UI steps}
- **Rerun check:** {What must remain unchanged after second seed}
- **Status:** Proposed
- **Blockers / assumptions:** {Any schema uncertainty}
```

## 6. Scenario verification matrix

All 27 scenarios have a schema-supported record shape. Schema support means tables and FK paths can represent the records; it does not verify an application workflow. Existing development seeders were executed and source rows were verified as recorded below. Application behavior stays **Pending** until implemented and independently verified. API routes are absent in the four module route files. Student page components exist, but no corresponding web routes/controllers were found; UI status is therefore pending/unverified, not passed.

| Scenario ID | Schema shape | Seeder code | Seed executed | DB outcome | Application behavior | API | UI | Pending behavior / note |
|---|---|---|---|---|---|---|---|---|
| SD-INS-001 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Department branch filtering is unsupported; departments belong to institute. |
| SD-INS-002 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Assignment lifecycle/overlap rules are not implemented. |
| SD-INS-003 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Same-branch asset/room consistency is not enforced. |
| SD-INS-004 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Stock quantity reconciles to seeded movement rows; application receipt calculation is not implemented. |
| SD-INS-005 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Stock quantity reconciles to seeded movement rows; issue validation and negative-stock behavior are not implemented. |
| SD-INS-006 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Price rows are present and FK-valid; date-effective selection and overlap validation are not implemented. |
| SD-ACA-001 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Student row exists without enrollment; registration workflow is unverified. |
| SD-ACA-002 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Enrollment/batch/course/branch scope matches in seeded rows; application workflow remains unimplemented. |
| SD-ACA-003 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Multiple course enrollments are representable; policy is unconfirmed. |
| SD-ACA-004 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Stage records align with course and batch; progression rules are not enforced. |
| SD-ACA-005 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Exam rows are linked; marks bounds and pass/fail rules are not implemented. |
| SD-ACA-006 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Certificate rows are linked; eligibility and lifecycle are not implemented. |
| SD-ACA-007 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Membership rows are present; eligibility and database duplicate prevention are not implemented. |
| SD-ACA-008 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Course/subject and teacher links are present; allocation workflow is absent. `course_subject` has 37 rows after a first rerun exposed an additive-volume bug; further reruns are stable after correction. |
| SD-ACA-009 | Supported | Implemented | Yes | Verified | Pending | Pending | Pending | Price rows and tuition course links are valid; effective-date selection and snapshot behavior are unimplemented. |
| SD-ACA-010 | Supported | Implemented | Pending | Pending | Pending | Pending | Eight open future demo cohorts are defined in AcademyVolumeSeeder; not executed or UI-verified. |
| SD-ACA-012 | Supported | Implemented | Pending | Pending | Pending | Pending | Current student's three-stage, no-exam enrollment sample is in EnrollmentOverviewSeeder; Pest passes, but seeder execution and manual UI verification remain pending. |
| SD-ACA-013 | Supported | Implemented | Pending | Pending | Pending | Pending | EnrollmentOverviewSeeder creates a second BLS enrollment through StudentEnrollmentService to verify automatic certificate creation; Pest passes, but seeder execution and manual UI verification remain pending. |
| SD-FIN-001 | Supported | Implemented | Yes | Source amounts verified | Pending | Pending | Pending | Seeded full-paid source rows calculate to zero due; application aggregation remains unimplemented. |
| SD-FIN-002 | Supported | Implemented | Yes | Source amounts verified | Pending | Pending | Pending | Seeded partial-paid source rows calculate to positive due; application due-list is unimplemented. |
| SD-FIN-003 | Supported | Implemented | Yes | Source amounts verified | Pending | Pending | Pending | Waiver and installment source amounts reconcile; approval/payment workflow remains unimplemented. |
| SD-FIN-004 | Supported | Implemented | Yes | Source amounts verified | Pending | Pending | Pending | Unpaid tuition source amount equals the expected due; application balance status is unimplemented. |
| SD-FIN-005 | Supported | Implemented | Yes | Source amounts/FKs verified | Pending | Pending | Pending | Payment model table mapping is correct; expense balance/payment workflow remains unimplemented. |
| SD-FIN-006 | Supported | Implemented | Yes | Source amounts/FKs verified | Pending | Pending | Pending | Expense and payment rows are linked; approval/settlement workflow is unimplemented. |
| SD-FIN-007 | Supported | Implemented | Yes | FK rows verified | Pending | Pending | Pending | Transfer source/destination records exist; posting and account balance ledger are unimplemented. |
| SD-FIN-008 | Supported | Implemented | Yes | Bill source totals verified | Pending | Pending | Pending | JSON line totals match bill totals and payment links exist; collection workflow is unimplemented. |
| SD-SEC-001 | Partial | Partial | Yes | Partial: users/groups only | Pending | Pending | Pending | Permission definitions and protected route behavior are absent. |
| SD-SEC-002 | Partial | Partial | Yes | Partial: users/groups only | Pending | Pending | Pending | Denial behavior has no protected module route. |
| SD-SEC-003 | Supported shape | Intentionally omitted | N/A | Verified omitted | Pending | Pending | Pending | No automatic audit writer or audited module action was found; no fabricated audit events are seeded. |

`CoreDevelopmentSeeder` is an explicit development-only entry point and is not wired into `DatabaseSeeder`. It ran successfully four times in this review (one initial run and three reruns). The first rerun added 17 extra `course_subject` pairs; `AcademyVolumeSeeder` was corrected to add only while below the documented target of 20. Because the observed database already held 37 pairs, the correction preserved them and added no more. No rows were deleted. The final two reruns after correction made no further count changes. No Pest tests were created or run. Database source-row verification does not change `Application behavior`, `API`, or `UI` from Pending.

### 6.1 Observed database execution and source-row verification

The target was verified immediately before seeding: `APP_ENV=local`, PostgreSQL 18.4, connection `pgsql`, host `127.0.0.1:5432`, database `cmud_system`, schema `public`; the public schema contained 52 application tables plus Laravel's `migrations` table. All application tables except `sessions` were empty. The two existing session rows were left untouched. The master seeder follows FK order: Access, Institute core, Institute volume, Academy foundation (core then volume), Finance core, Finance volume.

Observed row counts after the final rerun:

| Foundation | Table counts | Seed-owned rows |
|---|---|---:|
| Institute & Operations | institutes 1; branches 3; departments 4; rooms 6; stuff 12; stuff_assignments 12; asset_categories 4; assets 12; stocks 10; stock_movements 30; stock_price_versions 20; stock_categories 4 | 118 |
| Academy | students 200; medical_colleges 20; courses 8; course_stages 10; batches 15; enrollments 250; student_stage_records 120; exam_records 100; certificate_templates 2; certificates 30; communication_groups 10; student_group_memberships 120; teachers 6; course_teacher 8; course_price_versions 9; subjects 17; course_subject 37; branch_teacher 6 | 968 |
| Finance | student_tuitions 200; student_tuition_payments 300; student_expense_types 4; student_expense_price_versions 10; student_expenses 50; student_other_payments 40; institute_expense_types 5; institute_expense_categories 9; institute_expense_price_versions 8; institute_expenses 20; institute_expense_payments 20; payment_methods 3; financial_accounts 3; account_transfers 5; institute_income_bill 10; institute_other_income_bill_payment 10 | 697 |
| Access & Auditing | users 2; permission_groups 2; permissions 0; audit_logs 0. `sessions` 2 and `password_reset_tokens` 0 are runtime tables, not seeded. | 4 |

Read-only checks found 121 validated foreign-key constraints and no unvalidated constraints; enrollment/batch scope, stage/course/batch scope, tuition/course-price scope and amount, tuition/expense payment overages, expense price/source amount matches, institute expense and income-bill overages, bill JSON line totals, and duplicate payment numbers all had zero mismatches. All 10 stock quantities equal opening balance + receipts - issues (120 for the core stock and 110 for each of nine volume stocks). The four anchored tuition source records yielded 0.00, 25,000.00, 20,000.00, and 30,000.00 due respectively. These are direct source-row calculations, not verified application outputs. The final two reruns after the additive-pivot correction left all observed counts unchanged. The first failed attempt due to a missing expense-type `name` was rolled back; no database rows were left by that attempt.

## 7. Pest testing boundaries

- Development seeders create **stable demonstration data**, not a shared mutable fixture that every automated test depends on.
- Run Pest in a separate test database. Tests may insert minimal direct records or use **test-only seeders**, without model factories.
- Required test groups: FK integrity and uniqueness; calculations (tuition, payments, account transfer, stock); expected API responses; validation and authorization failures; rerun idempotence.
- For invalid requests (duplicate admission, invalid FK, overpayment, inadequate stock, unauthorized access), **attempt the invalid operation through the route in a test** and assert rejection/rollback. Do not keep invalid rows in the permanent sample database.
- Report routes/UI that do not exist as **not tested**, not passed.

## 8. Maintenance and change log

- Add a scenario when a new feature needs a meaningful success/boundary case.
- Update exact setup and outcomes when migrations or real business rules change; preserve historical scenario IDs.
- Keep `SEED_DATA_PLAN.md` synchronized with affected table ownership/dependencies.
- Never mark scenarios verified solely because `php artisan db:seed` succeeded.

| Date | Change | Status |
|---|---|---|
| 2026-11-12 | Reconciled all 26 scenarios; implemented development-only core data for schema-representable cases and marked unsupported workflows pending | Initially code-only; execution results are recorded above |
| 2026-11-12 | Executed the full dataset, checked database relationships and source totals, fixed the volume pivot's repeated additive insertion, and confirmed a stable subsequent rerun | Database rows verified; application workflows remain pending; no Pest tests run |
