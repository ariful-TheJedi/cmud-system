# FTR-SAM-12 --- Student Profile & Enrollment Overview

## Feature Group Information

  Item                   Value
  ---------------------- --------------------
  Module                 Students & Academy
  Feature Group ID       FTR-SAM-12
  Priority               High
  Development approach   API-first
  Frontend               React/Inertia
  Database               PostgreSQL
  Testing                Pest
  Status                 Planned

## Goal

Provide a consolidated Student Profile with four clearly separated
sections:

1.  Academic --- enrollment status, semesters/stages, examinations and
    results.
2.  Certificates --- one or multiple certificates per course enrollment,
    each with its own name/type and status.
3.  Financial Summary --- total course fee, approved waiver, payable
    fee, total paid, outstanding due and payment status.
4.  Student Payment History --- all payment transactions across all
    course enrollments.

The first three sections are organized by course enrollment. Student
Payment History is consolidated across the student's enrollments.

------------------------------------------------------------------------

# FTR-SAM-12-001 --- Enrollment Academic Overview

## 1. Goal

Display the academic information of each student enrollment, including
enrollment status, semesters/stages, examinations and results.

## 2. Functional Requirements

-   FR-001: Display all course enrollments belonging to a student.
-   FR-002: Display enrollment status independently for each course.
-   FR-003: Display all semesters/stages belonging to an enrollment.
-   FR-004: Support courses containing one or multiple semesters.
-   FR-005: Display the academic status of each semester/stage.
-   FR-006: Display examinations associated with each semester/stage.
-   FR-007: Support one or multiple examinations within a
    semester/stage.
-   FR-008: Display examination attempts, results and examination
    history.
-   FR-009: Display overall academic progress for each enrollment.

## 3. Business Rules

-   BR-001: A student can have multiple course enrollments.
-   BR-002: Each enrollment has its own academic lifecycle.
-   BR-003: Each semester/stage belongs to the relevant enrollment's
    academic structure.
-   BR-004: Examination records must be associated with the relevant
    semester/stage.
-   BR-005: Examination results must not automatically change enrollment
    status.
-   BR-006: Completing one enrollment must not affect another
    enrollment.

## 4. Status Categories

**Enrollment status**

``` php
[
    'enrolled',
    'ongoing',
    'on_hold',
    'completed',
    'discontinued',
    'cancelled'
]
```

**Semester/stage academic status**

``` php
[
    'pending',
    'ongoing',
    'on_hold',
    'completed',
    'failed',
    'incomplete'
]
```

**Examination attempt status**

``` php
[
    'scheduled',
    'appeared',
    'absent',
    'cancelled'
]
```

**Examination result status**

``` php
[
    'pending',
    'passed',
    'failed',
    'incomplete'
]
```

## 5. Existing Tables to Inspect

-   `students`
-   `enrollments`
-   `courses`
-   `batches`
-   `course_stages`
-   `student_stage_records`
-   `exam_records`

## 6. Expected Model Relationships

-   Student `hasMany` Enrollments.
-   Enrollment `belongsTo` Student.
-   Enrollment `belongsTo` Course.
-   Enrollment `belongsTo` Batch.
-   Enrollment `hasMany` StudentStageRecords.
-   StudentStageRecord `belongsTo` CourseStage.
-   StudentStageRecord `hasMany` ExamRecords.
-   ExamRecord `belongsTo` StudentStageRecord.

Verify actual foreign keys and existing model definitions before
changing relationships.

## 7. Suggested API

  ---------------------------------------------------------------------------------------------
  Method                  Endpoint                                      Purpose
  ----------------------- --------------------------------------------- -----------------------
  GET                     `/api/v1/students/{student}/academic`         Student academic
                                                                        overview

  GET                     `/api/v1/enrollments/{enrollment}/academic`   Enrollment academic
                                                                        details
  ---------------------------------------------------------------------------------------------

## 8. Acceptance Criteria

-   AC-001: All student enrollments can be retrieved.
-   AC-002: One-semester and multi-semester courses are supported.
-   AC-003: Each semester/stage displays its own academic status.
-   AC-004: Each semester/stage displays its associated examinations.
-   AC-005: Multiple examinations and attempts are supported where
    configured.
-   AC-006: Academic progress is independent for each enrollment.
-   AC-007: Examination results do not incorrectly overwrite enrollment
    status.

## 9. Test Scenarios

-   TC-001: Student with multiple enrollments.
-   TC-002: Enrollment with one semester.
-   TC-003: Enrollment with multiple semesters.
-   TC-004: Semester with multiple examinations.
-   TC-005: Multiple examination attempts.
-   TC-006: Independent enrollment statuses.
-   TC-007: Correct academic progress calculation.
-   TC-008: Examination history preservation.

## 10. Completion Checklist

-   [ ] Existing schema and relationships inspected.
-   [ ] Required database changes identified and approved.
-   [ ] API endpoints and validation implemented.
-   [ ] Response structure verified.
-   [ ] Pest tests pass.
-   [ ] Manual API verification completed.

------------------------------------------------------------------------

# FTR-SAM-12-002 --- Enrollment Certificate Management

## 1. Goal

Manage and display multiple certificates belonging to an individual
course enrollment.

## 2. Functional Requirements

-   FR-001: Display all certificates associated with an enrollment.
-   FR-002: Support one or multiple certificates per enrollment.
-   FR-003: Display certificate name/type.
-   FR-004: Display certificate number and relevant dates.
-   FR-005: Track each certificate's status independently.
-   FR-006: Support certificate preparation, issuance and delivery
    tracking.
-   FR-007: Display certificate history.
-   FR-008: Support certificate reissue requests.
-   FR-009: Display certificates across all enrollments in the student
    profile.

## 3. Business Rules

-   BR-001: Each certificate belongs to a specific enrollment.
-   BR-002: One enrollment may have multiple certificates.
-   BR-003: Each certificate has an independent status.
-   BR-004: Issuing one certificate must not automatically issue other
    certificates.
-   BR-005: Certificate eligibility follows the applicable academic
    completion requirements.
-   BR-006: Reissuing a certificate must preserve the original
    certificate record.
-   BR-007: Enrollment creates a certificate record with `not_eligible`
    status when the course has an active course-specific template.
-   BR-008: Enrollment remains allowed without a certificate record when
    no active course-specific template exists.

## 4. Certificate Statuses

``` php
[
    'not_eligible',
    'eligibility_pending',
    'pending',
    'processing',
    'ready',
    'issued',
    'delivered'
]
```

## 5. Existing Tables to Inspect

-   `certificates`
-   `certificate_templates`
-   `certificate_reissue_requests`
-   `enrollments`
-   `students`

## 6. Expected Model Relationships

-   Enrollment `hasMany` Certificates.
-   Certificate `belongsTo` Enrollment.
-   Certificate `belongsTo` CertificateTemplate, if the existing schema
    supports this.
-   CertificateReissueRequest `belongsTo` original Certificate.
-   CertificateReissueRequest `belongsTo` replacement Certificate, where
    applicable.

Verify the actual schema and existing model definitions before changing
relationships.

## 7. Suggested API

  -------------------------------------------------------------------------------------------------
  Method                  Endpoint                                          Purpose
  ----------------------- ------------------------------------------------- -----------------------
  GET                     `/api/v1/enrollments/{enrollment}/certificates`   Enrollment certificates

  GET                     `/api/v1/students/{student}/certificates`         All student
                                                                            certificates

  GET                     `/api/v1/certificates/{certificate}`              Certificate details
  -------------------------------------------------------------------------------------------------

## 8. Acceptance Criteria

-   AC-001: Multiple certificates per enrollment are supported.
-   AC-002: Certificate names/types are displayed correctly.
-   AC-003: Each certificate has an independent status.
-   AC-004: Certificate eligibility is validated against academic
    requirements.
-   AC-005: Issuance and delivery are tracked independently.
-   AC-006: Original certificate history is preserved after reissue.

## 9. Test Scenarios

-   TC-001: Enrollment with no certificates.
-   TC-002: Enrollment with one certificate.
-   TC-003: Enrollment with multiple certificates.
-   TC-004: Different certificate types.
-   TC-005: Independent certificate statuses.
-   TC-006: Certificate eligibility validation.
-   TC-007: Certificate issuance and delivery.
-   TC-008: Certificate reissue history.
-   TC-009: Enrollment creates a not-eligible certificate when an active
    course template exists.
-   TC-010: Enrollment remains successful without a certificate when no
    active course template exists.

## 10. Completion Checklist

-   [ ] Existing certificate schema and relationships inspected.
-   [ ] Certificate type/name structure verified.
-   [ ] Required database changes identified and approved.
-   [ ] API endpoints and validation implemented.
-   [ ] Pest tests pass.
-   [ ] Manual API verification completed.

------------------------------------------------------------------------

# FTR-SAM-12-003 --- Enrollment Financial Summary

## 1. Goal

Display the complete financial summary of each course enrollment,
including total course fee, approved waiver, payable amount, payments
and outstanding balance.

## 2. Functional Requirements

-   FR-001: Display the original total course fee.
-   FR-002: Display the approved waiver/discount amount.
-   FR-003: Calculate the final payable fee.
-   FR-004: Display total valid payments allocated to the enrollment's
    bills.
-   FR-005: Calculate the outstanding balance.
-   FR-006: Display the enrollment's financial status.
-   FR-007: Support multiple payments against course fees.
-   FR-008: Display financial summaries independently for multiple
    enrollments.
-   FR-009: Display relevant bill and payment details.

## 3. Business Rules

-   BR-001: Financial summary belongs to an individual course
    enrollment.
-   BR-002: Only approved waivers/discounts reduce the payable fee.
-   BR-003: Payable fee = Total course fee − Approved waiver.
-   BR-004: Outstanding due = Payable fee − Valid payments allocated to
    the relevant bills, subject to the existing billing and allocation
    rules.
-   BR-005: Students can make multiple payments until the payable amount
    is fulfilled.
-   BR-006: Financial status is determined from outstanding balance and
    applicable due dates.
-   BR-007: Financial status must not automatically determine academic
    enrollment status.
-   BR-008: Payments must not be counted more than once.
-   BR-009: A payment made toward one enrollment must not be incorrectly
    counted toward another enrollment.

## 4. Financial Statuses

``` php
[
    'unpaid',
    'partial',
    'paid',
    'overdue'
]
```

Overdue should be determined using the applicable due date and
outstanding balance. Confirm whether it is stored or derived in the
existing Finance design.

## 5. Existing Tables to Inspect

-   `enrollments`
-   `courses`
-   `course_price_versions`
-   `student_tuitions`
-   `student_tuition_payments`
-   `student_payment_allocations`, if implemented
-   Existing waiver/discount records, if available.

## 6. Expected Model Relationships

-   Enrollment `hasMany` StudentTuitions, if this matches the existing
    schema.
-   StudentTuition has payment/allocation relationships according to the
    actual schema.
-   Enrollment financial summary is calculated from its authoritative
    billing and payment records.

Do not assume that `student_tuition_payments` is directly related to an
enrollment. Inspect the actual foreign keys and allocation structure
first.

## 7. Suggested API

  ------------------------------------------------------------------------------------------------------
  Method                  Endpoint                                               Purpose
  ----------------------- ------------------------------------------------------ -----------------------
  GET                     `/api/v1/enrollments/{enrollment}/financial-summary`   Enrollment financial
                                                                                 summary

  GET                     `/api/v1/students/{student}/financial-summary`         Financial summaries
                                                                                 across enrollments
  ------------------------------------------------------------------------------------------------------

## 8. Acceptance Criteria

-   AC-001: Original fee and approved waiver are displayed correctly.
-   AC-002: Payable amount is calculated correctly.
-   AC-003: Multiple payments are accounted for correctly.
-   AC-004: Outstanding balance is calculated correctly.
-   AC-005: Financial status is displayed correctly.
-   AC-006: Payments are not double-counted.
-   AC-007: Financial summaries remain independent across enrollments.

## 9. Test Scenarios

-   TC-001: Enrollment without payment.
-   TC-002: Enrollment with partial payment.
-   TC-003: Enrollment with full payment.
-   TC-004: Enrollment with approved waiver.
-   TC-005: Multiple payments against one enrollment.
-   TC-006: Outstanding balance calculation.
-   TC-007: Overdue calculation.
-   TC-008: Multiple enrollments with independent financial summaries.
-   TC-009: Cancelled, reversed or otherwise invalid payment handling.

## 10. Completion Checklist

-   [ ] Existing billing, waiver and payment schema inspected.
-   [ ] Payment allocation rules verified.
-   [ ] Required database changes identified and approved.
-   [ ] API endpoints and calculations implemented.
-   [ ] Pest tests pass.
-   [ ] Manual API verification completed.

------------------------------------------------------------------------

# FTR-SAM-12-004 --- Student Consolidated Payment History

## 1. Goal

Display all payment transactions made by a student across all course
enrollments in one consolidated payment history.

## 2. Functional Requirements

-   FR-001: Display all valid payment transactions belonging to a
    student.
-   FR-002: Include payments from all course enrollments.
-   FR-003: Display payment date, amount, payment method and
    transaction/reference number.
-   FR-004: Identify the course enrollment associated with each payment,
    where applicable.
-   FR-005: Display payment receipt information.
-   FR-006: Support filtering by course, date and payment method.
-   FR-007: Display payment allocation details where one payment is
    allocated to multiple bills.
-   FR-008: Display cancelled, reversed or refunded transactions
    distinctly where supported by the existing Finance policy.

## 3. Business Rules

-   BR-001: Payment history is student-wide, not enrollment-specific.
-   BR-002: Every payment must be traceable to its actual financial
    transaction.
-   BR-003: A payment must not appear multiple times because it has
    multiple allocations.
-   BR-004: Payment history must preserve transaction records.
-   BR-005: Payment totals must use valid transactions and must not
    simply count payment records.
-   BR-006: Payments must be associated with the correct student, even
    when the student has multiple enrollments.

## 4. Existing Tables to Inspect

-   `students`
-   `enrollments`
-   `student_tuitions`
-   `student_tuition_payments`
-   `student_other_payments`
-   `payment_methods`
-   `financial_accounts`
-   `student_payment_allocations`, if implemented.

Determine whether `student_other_payments` contains transactions that
belong in the consolidated student payment history. Do not include
unrelated transaction types.

## 5. Expected Model Relationships

-   Student `hasMany` Enrollments.
-   Enrollment has billing relationships according to the existing
    schema.
-   Billing/payment records must be traceable to their student and
    enrollment.
-   Student-wide payment history should consolidate the authoritative
    payment transactions without duplicating rows through joins to
    multiple allocations.

Verify actual foreign keys and model definitions before changing
relationships.

## 6. Suggested API

  -----------------------------------------------------------------------------------------------
  Method                  Endpoint                                        Purpose
  ----------------------- ----------------------------------------------- -----------------------
  GET                     `/api/v1/students/{student}/payments`           Consolidated payment
                                                                          history

  GET                     `/api/v1/students/{student}/payments/summary`   Student payment summary

  GET                     `/api/v1/student-payments/{payment}`            Payment details and
                                                                          receipt
  -----------------------------------------------------------------------------------------------

## 7. Acceptance Criteria

-   AC-001: All relevant student payments are displayed together.
-   AC-002: Course enrollment is identified for each payment where
    applicable.
-   AC-003: Payment history is not duplicated.
-   AC-004: Payment details and receipts are accessible.
-   AC-005: Filtering works correctly.
-   AC-006: Invalid/reversed transactions are represented according to
    Finance policy.

## 8. Test Scenarios

-   TC-001: Student with no payments.
-   TC-002: Student with one enrollment and multiple payments.
-   TC-003: Student with multiple enrollments and payments.
-   TC-004: Payments across different courses.
-   TC-005: Payment allocated to multiple bills.
-   TC-006: Payment method and receipt display.
-   TC-007: Date and course filtering.
-   TC-008: Prevent duplicate payment display.
-   TC-009: Exclude invalid/reversed transactions from valid payment
    totals.

## 9. Completion Checklist

-   [ ] Existing student payment sources and relationships inspected.
-   [ ] Scope of `student_other_payments` verified.
-   [ ] Payment allocation and transaction status rules verified.
-   [ ] Required database changes identified and approved.
-   [ ] API endpoints and filtering implemented.
-   [ ] Pest tests pass.
-   [ ] Manual API verification completed.

------------------------------------------------------------------------

# Overall Development Sequence

1.  FTR-SAM-12-001 --- Enrollment Academic Overview.
2.  FTR-SAM-12-002 --- Enrollment Certificate Management.
3.  FTR-SAM-12-003 --- Enrollment Financial Summary.
4.  FTR-SAM-12-004 --- Student Consolidated Payment History.

# Shared Implementation Standards

1.  Inspect existing migrations, columns, foreign keys, indexes, models
    and relationships before making database changes.
2.  Reuse existing tables wherever possible. Do not create duplicate
    academic, certificate or financial structures.
3.  Keep academic, certificate and financial lifecycles independent.
4.  Keep academic progress and examination records tied to the relevant
    enrollment and semester/stage.
5.  Keep certificates tied to the relevant enrollment, with independent
    certificate types and statuses.
6.  Calculate enrollment financial summaries from authoritative billing,
    waiver and payment allocation records.
7.  Consolidate student payment history without duplicating
    transactions.
8.  Implement backend API, validation and Pest tests before the
    React/Inertia UI.
9.  Do not run production migrations or reset databases without explicit
    approval.
10. Security and permission enforcement remain a separate cross-cutting
    layer to be implemented later. Do not add authorization/permission
    features to these business features now; keep the routes compatible
    with future middleware and policies.

## Important Business Structure

-   One student can have multiple course enrollments.
-   One enrollment can have one or multiple semesters/stages.
-   One semester/stage can have one or multiple examinations.
-   One enrollment can have one or multiple certificates.
-   One enrollment has its own course fee, waiver, payable amount,
    payments and financial status.
-   A student can make multiple payments to fulfill the payable amount.
-   Student Payment History consolidates payment transactions across all
    the student's enrollments.
-   Academic, certificate and financial statuses must not overwrite one
    another.
