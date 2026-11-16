# FTR-SAM-01 — Student Admission & Registration

**Module:** Students & Academy  
**Feature Group:** Student Admission & Registration  
**Specifications:** 4  
**Status:** Draft

---

# FTR-SAM-01-001 — Student Admission

| Field | Value |
|---|---|
| Source | CMUD ERP Admin Checklist — AD01; Original FTR019 |
| Module | Students & Academy |
| Priority | To confirm |
| Status | Draft |
| Delivery | API first; UI later |

## 1. Goal

Register a student with personal/contact details, qualifications, admission date, course and batch; assign a unique student ID.

## 2. Requirements & rules

- Capture the approved required student fields and admission date.
- Select an existing institute, branch, course and batch; validate their relationships.
- Create **one student identity** (`students`) and a linked course enrollment (`enrollments`) when the approved admission workflow requires both.
- A student may enroll in multiple courses without duplicate registration.
- Generate a unique `student_code` using FTR-SAM-01-003.
- Check possible duplicates using FTR-SAM-01-002.
- Require authenticated, authorized staff.
- Save student and enrollment atomically using a database transaction.
- Return student and enrollment identifiers on success.

**Not included:** Documents, profile editing, student search, enrollment changes, tuition, or payment processing.

## 3. Dependencies & API

- **Models:** `Student`, `Enrollment`, `Institute`, `Branch`, `Course`, `Batch`.
- **Modules:** Students & Academy (owner), Institute & Branches (lookup), Security (permission), Finance (downstream).
- **Dependencies:** FTR-SAM-01-002, FTR-SAM-01-003.
- **API (proposed):** `POST /api/students/admissions` — authenticated; permission to confirm.
- **Responses (proposed):** `201` success, `401` unauthenticated, `403` forbidden, `422` validation failure. Duplicate/conflict response to confirm.

## 4. Pest test scenarios

| Type | Required checks |
|---|---|
| Unit | Student-code generation and isolated admission business rules |
| Feature / API | Valid admission; required-field validation; invalid branch/course/batch relationships; authentication; authorization; duplicate policy |
| Integration (PostgreSQL) | Student and enrollment persistence; transaction rollback; unique student codes; foreign-key constraints; concurrent admissions |
| Manual (Postman/Bruno) | Login with session/CSRF → submit fictional student → inspect response and PostgreSQL records → test invalid and unauthorized requests |

**Suggested files:**
- `tests/Unit/Academy/StudentCodeGeneratorTest.php`
- `tests/Feature/Academy/StudentAdmissionTest.php`
- `tests/Integration/AcademyInstitute/StudentAdmissionFlowTest.php`

Run targeted Pest tests on a **separate PostgreSQL test database**, followed by regression tests. Report actual results only.

## 5. Acceptance / API Done

- [ ] Approved admission rules implemented.
- [ ] Authentication and authorization enforced.
- [ ] Student and enrollment records created correctly.
- [ ] Unique student code generated.
- [ ] Failed operations leave no partial records.
- [ ] Relevant Pest and regression tests pass.
- [ ] Manual API verification and developer review pass.

**API Done ≠ UI Done.** UI delivery is tracked separately.

## 6. Decisions to confirm before implementation

1. Which student fields are mandatory?
2. Must registration and enrollment occur together?
3. Which course/batch relationships are required?
4. What permissions authorize admissions?
5. Is this required for the first release?

**Tracking:** GitHub Issue: — · PR: — · Tests: Not run · Manual verification: Pending

---

# FTR-SAM-01-002 — Existing Records and Duplicate Checks

| Field | Value |
|---|---|
| Source | CMUD ERP Admin Checklist — AD15; Original FTR033 |
| Module | Students & Academy |
| Priority | To confirm |
| Status | Draft |
| Delivery | API first; UI later |

## 1. Goal

Identify existing or potentially duplicate students before registration, preventing unnecessary duplicate student identities while allowing legitimate admissions.

## 2. Requirements & rules

- Search existing students using approved identifying fields.
- Support potential matches using phone, name, student code, and other approved identifiers.
- Distinguish exact matches from possible matches.
- Return matching student information only to authorized staff.
- Allow an existing student to be selected for additional course enrollment.
- Apply the approved policy for warnings, blocking, and authorized overrides.
- Do not automatically reject students solely because they share a phone number.
- Enforce duplicate detection during admission submission, not only in a preliminary lookup.
- Keep database-enforced unique constraints separate from approximate duplicate matching.

**Not included:** Merging existing duplicate records, student profile editing, or enrollment changes.

## 3. Dependencies & API

- **Models:** `Student`, `Enrollment`.
- **Modules:** Students & Academy (owner), Security (authorization).
- **Dependencies:** Student identity rules from FTR-SAM-01-003.
- **API (proposed):** `POST /api/students/duplicate-check` — authenticated and authorized.
- **Responses (proposed):** `200` match-check completed, `401` unauthenticated, `403` forbidden, `422` invalid input.
- **Integration:** Admission endpoint must apply the approved duplicate policy before committing registration.

## 4. Pest test scenarios

| Type | Required checks |
|---|---|
| Unit | Exact matching, normalized identifiers, and approximate matching rules |
| Feature / API | Existing student detected; possible matches returned; nonmatches allowed; shared phone handled correctly; unauthorized access blocked |
| Integration (PostgreSQL) | Searches use existing student records; duplicate restrictions remain effective during concurrent registration; existing student reuse does not create another identity |
| Manual (Postman/Bruno) | Create fictional student → submit matching details → inspect warning/matches → test legitimate shared phone → verify access restrictions |

**Suggested files:**
- `tests/Unit/Academy/StudentDuplicateDetectorTest.php`
- `tests/Feature/Academy/StudentDuplicateCheckTest.php`
- `tests/Integration/Academy/StudentDuplicateAdmissionTest.php`

Use a separate PostgreSQL test database. Verify actual matching results and database behavior.

## 5. Acceptance / API Done

- [ ] Approved matching rules implemented.
- [ ] Existing students and possible duplicates detected.
- [ ] Legitimate shared contact information handled correctly.
- [ ] Duplicate checks enforced during admission.
- [ ] Student information protected by authorization.
- [ ] Relevant Pest and regression tests pass.
- [ ] Manual API verification passes.

**API Done ≠ UI Done.**

## 6. Decisions to confirm before implementation

1. Which student fields participate in duplicate detection?
2. Which identifiers constitute a definite duplicate?
3. Are duplicate matches warnings or blocking errors?
4. Who may override a warning?
5. What student information may appear in duplicate-check responses?

**Tracking:** GitHub Issue: — · PR: — · Tests: Not run · Manual verification: Pending

---

# FTR-SAM-01-003 — Student Identity and Unique ID Management

| Field | Value |
|---|---|
| Source | Original FTR065 — Proposed Additional Requirement |
| Module | Students & Academy |
| Priority | To confirm |
| Status | Draft |
| Delivery | API first; UI later |

## 1. Goal

Generate and preserve a unique student registration code for each student identity across the institute and all associated course enrollments.

## 2. Requirements & rules

- Generate a unique `student_code` during initial registration.
- Associate the code with exactly one student record.
- Preserve the existing code when the student joins additional courses.
- Enforce uniqueness through PostgreSQL constraints.
- Use a concurrency-safe code-generation mechanism.
- Prevent unauthorized modification of the generated student code.
- Support retrieval and searching using the student code.
- Keep the generated student code separate from the database primary key (`id`).
- Any approved correction must preserve traceability.
- Do not reuse previously assigned codes.

**Not included:** Enrollment-specific identifiers, certificate numbers, or general student profile editing.

## 3. Dependencies & API

- **Models:** `Student`, `Enrollment`.
- **Database:** `students.student_code` with an appropriate unique constraint.
- **Modules:** Students & Academy (owner), Security (permission).
- **Dependencies:** FTR-SAM-01-001.
- **API:** Code generation is primarily an internal service used during admission.
- **Proposed lookup API:** `GET /api/students/{student}` — authenticated and authorized, returning the student's code.
- **Responses:** Determined by the calling endpoint; uniqueness conflicts must be handled without creating duplicate records.

## 4. Pest test scenarios

| Type | Required checks |
|---|---|
| Unit | Code format, sequence/identifier generation, and relevant generation rules |
| Feature / API | Registration returns a code; lookup exposes correct code; unauthorized code changes rejected |
| Integration (PostgreSQL) | Unique database constraint; concurrent code generation; persistence across enrollments; rollback behavior |
| Manual (Postman/Bruno) | Register multiple fictional students → compare codes → enroll an existing student again → confirm original code is unchanged |

**Suggested files:**
- `tests/Unit/Academy/StudentCodeGeneratorTest.php`
- `tests/Feature/Academy/StudentIdentityTest.php`
- `tests/Integration/Academy/StudentCodeUniquenessTest.php`

Concurrency-sensitive tests must use PostgreSQL and independently verify the database uniqueness guarantee.

## 5. Acceptance / API Done

- [ ] Student code format approved.
- [ ] Every student receives a unique code.
- [ ] PostgreSQL uniqueness constraint enforced.
- [ ] Concurrent requests cannot commit duplicate codes.
- [ ] Codes remain unchanged across multiple enrollments.
- [ ] Unauthorized modification prevented.
- [ ] Required Pest and regression tests pass.
- [ ] Manual API verification passes.

**API Done ≠ UI Done.**

## 6. Decisions to confirm before implementation

1. What is the student-code format?
2. Is numbering institute-wide or branch-specific?
3. Can codes ever be corrected after registration?
4. Should numbering reset annually or remain continuous?
5. How should generation conflicts be retried and reported?

**Tracking:** GitHub Issue: — · PR: — · Tests: Not run · Manual verification: Pending

---

# FTR-SAM-01-004 — Student Status Management

| Field | Value |
|---|---|
| Source | Original FTR066 — Proposed Additional Requirement |
| Module | Students & Academy |
| Priority | To confirm |
| Status | Draft |
| Delivery | API first; UI later |

## 1. Goal

Manage a student's administrative status independently of the academic status of their individual course enrollments.

## 2. Requirements & rules

- Assign an approved default student status during registration.
- Retrieve and display a student's current administrative status.
- Allow authorized staff to change student status.
- Support filtering students by administrative status.
- Validate permitted status values and transitions.
- Record important status changes with responsible user and timestamp.
- Preserve student, enrollment, examination, and payment history after a status change.
- Apply approved restrictions to administrative actions based on current student status.
- Do not automatically change student status when a course is completed.
- Keep student-level status independent of each enrollment's status.

**Proposed statuses:** `active`, `inactive`, `blocked`. Final status definitions require approval.

**Not included:** Enrollment status changes, academic stage progression, student deletion, or financial-account status management.

## 3. Dependencies & API

- **Models:** `Student`, `Enrollment`.
- **Database:** Student status field and an audit/history mechanism, subject to existing schema review.
- **Modules:** Students & Academy (owner), Security (authorization); Finance and other modules consume status only where approved.
- **Dependencies:** FTR-SAM-01-001.
- **API (proposed):** `PATCH /api/students/{student}/status` — authenticated, authorized.
- **Responses (proposed):** `200` updated, `401` unauthenticated, `403` forbidden, `404` student missing, `422` invalid status or transition.

## 4. Pest test scenarios

| Type | Required checks |
|---|---|
| Unit | Default status, allowed status transitions, and status-based restriction rules |
| Feature / API | Authorized updates succeed; invalid status rejected; unauthorized changes blocked; missing student handled |
| Integration (PostgreSQL) | Status persists; audit history records important changes; enrollment and academic data remain unchanged |
| Manual (Postman/Bruno) | Register fictional student → inspect default status → perform authorized update → inspect database/history → retry as unauthorized staff |

**Suggested files:**
- `tests/Unit/Academy/StudentStatusRulesTest.php`
- `tests/Feature/Academy/StudentStatusTest.php`
- `tests/Integration/Academy/StudentStatusHistoryTest.php`

Run tests on a separate PostgreSQL test database, including regression tests for dependent workflows.

## 5. Acceptance / API Done

- [ ] Administrative statuses and transition rules approved.
- [ ] Default status assigned during registration.
- [ ] Authorized status updates work.
- [ ] Unauthorized and invalid updates rejected.
- [ ] Status history preserved where required.
- [ ] Enrollment and academic records remain unchanged.
- [ ] Approved status-based restrictions enforced.
- [ ] Relevant Pest and regression tests pass.
- [ ] Manual API verification passes.

**API Done ≠ UI Done.**

## 6. Decisions to confirm before implementation

1. Which student statuses are required?
2. What is the default status?
3. Which transitions are permitted?
4. Which operations are restricted for inactive or blocked students?
5. Which staff permissions authorize status updates?
6. Does the current schema already contain suitable status and history fields?

**Tracking:** GitHub Issue: — · PR: — · Tests: Not run · Manual verification: Pending

---

# Group Completion Checklist

- [ ] FTR-SAM-01-001 — Student Admission
- [ ] FTR-SAM-01-002 — Existing Records and Duplicate Checks
- [ ] FTR-SAM-01-003 — Student Identity and Unique ID Management
- [ ] FTR-SAM-01-004 — Student Status Management

**Group Status:** Draft — Not Started

**Testing Policy:** Use a separate PostgreSQL test database. Run targeted Pest tests, relevant regression tests, and the full test suite before release. Record actual results only.