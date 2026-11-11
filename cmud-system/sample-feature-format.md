# ACA-001 — Student Admission

| Field | Value |
|---|---|
| Source | CMUD ERP Admin Checklist — AD01 |
| Module | Academy (Students & Academy) |
| Priority | To confirm |
| Status | Draft |
| Delivery | API first; UI later |

## 1. Goal
Register a student with personal/contact details, qualifications, admission date, course and batch; assign a unique student ID.

## 2. Requirements & rules
- Capture the approved required student fields and admission date.
- Select an existing institute, branch, course and batch; validate their relationships.
- Create **one student identity** (`students`) and a linked course enrollment (`enrollments`). A student may enroll in multiple courses.
- Generate a unique `student_code`; check for possible duplicates using an **approved** policy.
- Require authenticated, authorized staff; save student + enrollment without partial writes.
- Return student and enrollment identifiers on success.

**Not included:** documents (AD02), profile editing (AD03), search (AD04), enrollment changes (AD09), tuition/payment (Finance).

## 3. Dependencies & API
- **Models:** `Student`, `Enrollment`, `Institute`, `Branch`, `Course`, `Batch`.
- **Modules:** Academy (owner), Institute (lookup), Security (permission); Finance is downstream only.
- **API (proposed):** `POST /api/students/admissions` — authenticated; permission name to confirm.
- **Responses (proposed):** `201` success, `401` unauthenticated, `403` forbidden, `422` invalid input. Duplicate/conflict behavior to confirm.

## 4. Pest test scenarios

| Type | Required checks |
|---|---|
| Unit | Student-code generation and any isolated business rules |
| Feature / API | Valid admission succeeds; missing/invalid fields rejected; invalid branch/institute or course/batch pairing rejected; unauthenticated and unauthorized requests blocked; approved duplicate policy enforced |
| Integration (PostgreSQL) | Student + enrollment persist correctly; failed enrollment rolls back student creation; unique codes and FK constraints hold, including concurrent requests |
| Manual (Postman/Bruno) | Login with real session/CSRF → register fictional student → inspect response → check PostgreSQL student/enrollment → retry invalid and unauthorized cases |

**Suggested files:**
- `tests/Unit/Academy/StudentCodeGeneratorTest.php`
- `tests/Feature/Academy/StudentAdmissionTest.php`
- `tests/Integration/AcademyInstitute/StudentAdmissionFlowTest.php`

Run targeted Pest tests on a **separate PostgreSQL test database**, then relevant regression tests and full CI. Report actual results; never claim unrun tests passed.

## 5. Acceptance / API Done
- [ ] Approved rules implemented and permission enforced
- [ ] Admission works; unique student code and valid relationships verified
- [ ] Failures do not leave partial records
- [ ] Relevant Pest tests and regression suite pass
- [ ] Manual API verification and developer review pass

**API Done ≠ UI Done.** UI delivery is tracked separately.

## 6. Decisions to confirm before implementation
1. Which student fields are mandatory?
2. Student-code format and duplicate-detection policy?
3. Must new student registration and course enrollment occur together?
4. What makes a batch eligible, and what exact permission is required?
5. Is AD01 a first-release requirement?

**Tracking:** GitHub Issue: — · PR: — · Tests: Not run · Manual verification: Pending
