## FTR-SAM-06 — Student Academic Progress


Description: Track student academic progress independently within each course enrollment, manage stage and semester progression, enforce examination eligibility based on semester fee clearance, determine course completion, and manage approved academic exceptions.
Feature List
FTR-SAM-06-001 — Student Stage Progression
Original: FTR072
Track student progression between course stages or semesters according to the course's academic structure and approved progression rules.


FTR-SAM-06-002 — Course Completion Tracking
Original: FTR074
Determine whether a student has fulfilled all mandatory academic requirements for a particular course enrollment.

FTR-SAM-06-003 — Student Stage Status Management
Original: FTR093
Manage individual stage statuses, including Pending, Ongoing, Incomplete, Failed, and Completed.

FTR-SAM-06-004 — Academic Exception Management
Original: FTR094
Handle incomplete stages, delayed completion, interrupted studies, and authorized academic exceptions.

FTR-SAM-06-005 — Semester Examination Eligibility Management
New
Determine examination eligibility based on semester fee clearance, academic requirements, institutional status, and approved exceptions.

FTR-SAM-06-006 — Academic Completion and Certificate Eligibility
New
Determine certificate eligibility after successful completion of mandatory academic stages and examinations for an individual enrollment.

Functional Requirements
FR-001 — Student Stage Progression

The system shall allow users to track and manage student progression between course stages or semesters according to the defined academic structure and approved progression rules.

FR-002 — Course Completion Tracking

The system shall determine and maintain course completion separately for each student enrollment based on mandatory academic requirements.

FR-003 — Student Stage Status Management

The system shall manage individual student stage statuses, including:
- Pending
- Ongoing
- Incomplete
- Failed
- Completed
- On Hold
FR-004 — Academic Exception Management

The system shall allow users to record and manage incomplete stages, delayed completion, interrupted studies, and other approved academic exceptions.

FR-005 — Academic Progress History

The system shall maintain the history of academic progression, stage status changes, completion records, and academic exceptions.

FR-006 — Academic Progression Validation

The system shall validate student eligibility for progression to the next stage according to configured academic requirements and approved exceptions.

FR-007 — Semester Examination Eligibility

The system shall determine whether a student is eligible to participate in a semester examination based on the applicable semester fee clearance and academic requirements.

FR-008 — Examination Financial Restriction

The system shall prevent examination participation when the applicable semester fee is unpaid or partially paid, unless an approved financial exception exists.

FR-009 — Institutional Status Validation

The system shall verify the student's institutional status before allowing academic progression or examination participation. Deactivated or suspended students shall be restricted according to the applicable institutional policy.

FR-010 — Academic Completion Validation

The system shall confirm that all mandatory stages and academic requirements have been fulfilled before marking an enrollment as Completed.

FR-011 — Certificate Eligibility Determination

The system shall determine certificate eligibility when the student has successfully completed all required academic stages and examinations for the relevant enrollment.

FR-012 — Enrollment-wise Academic Independence

The system shall maintain academic progress and completion independently for every course enrollment belonging to a student.

Business Rules
BR-001: Student academic progress must be maintained separately for each course enrollment.

BR-002: Students must follow the defined stage sequence of their enrolled course.

BR-003: A student can progress to the next stage only when applicable academic requirements are fulfilled or an approved exception permits progression.

BR-004: Each student stage must maintain an individual academic status.

BR-005: A course enrollment can only be marked as Completed when all mandatory academic requirements are fulfilled.

BR-006: Failed or incomplete stages must not automatically be considered completed.

BR-007: Academic exceptions must be recorded with relevant details, reasons, and approval information.

BR-008: Academic exceptions must not automatically change stage or enrollment completion status unless applicable academic rules permit it.

BR-009: Changes to student academic progress must preserve previous academic records.

BR-010: Course completion must remain independent for each student enrollment.

BR-011: Each semester must have an applicable semester fee requirement according to CMUD's financial policy.

BR-012: A student must fully clear the applicable semester fee before participating in that semester's examination.

BR-013: Unpaid or partially paid semester fees make the student ineligible for the applicable examination.

BR-014: Approved discounts and fee waivers must be considered when calculating the payable semester fee.

BR-015: Approved financial exceptions may permit examination participation despite outstanding semester fees.

BR-016: Examination eligibility must be checked independently for each semester and enrollment.

BR-017: A deactivated or suspended student cannot participate in restricted academic activities according to the applicable institutional status policy.

BR-018: Passing an examination does not automatically mean the entire course enrollment is completed.

BR-019: Certificate eligibility requires successful completion of all mandatory academic stages and examinations.

BR-020: Certificate eligibility must be maintained independently for each course enrollment.

BR-021: Academic completion and financial clearance must remain separate conditions. Any additional financial clearance required for certificate issuance must follow CMUD's financial policy.

Acceptance Criteria
AC-001: Users can view academic progress for individual course enrollments.

AC-002: Users can record student progression through course stages or semesters.

AC-003: The system validates stage progression according to configured academic rules.

AC-004: Users can manage individual student stage statuses.

AC-005: The system supports Pending, Ongoing, Incomplete, Failed, Completed, and On Hold stage statuses.

AC-006: Users can record incomplete stages and delayed academic completion.

AC-007: Users can record interrupted studies and approved academic exceptions.

AC-008: The system maintains academic exception records.

AC-009: The system determines course completion based on mandatory academic requirements.

AC-010: The system prevents course completion when mandatory academic requirements remain unfulfilled.

AC-011: Previous academic progression and status changes remain accessible.

AC-012: Academic progress in one enrollment does not automatically affect another enrollment.

AC-013: The system checks applicable semester fee clearance before permitting examination participation.

AC-014: Students with unpaid or partially paid semester fees cannot participate in the applicable examination.

AC-015: Students with approved financial exceptions can be permitted to participate according to the exception decision.

AC-016: The system checks institutional status before permitting restricted academic activities.

AC-017: The system determines certificate eligibility after all mandatory academic requirements and examinations are successfully completed.

AC-018: Passing an individual examination does not automatically complete the course enrollment.

AC-019: Certificate eligibility for one enrollment does not affect another enrollment.

Testing Requirements
TC-001: Verify that student academic progress can be recorded for an individual enrollment.

TC-002: Verify that students can progress through stages in the correct sequence.

TC-003: Verify that progression is rejected when mandatory academic requirements are not fulfilled.

TC-004: Verify that eligible students can progress to the next stage.

TC-005: Verify that individual student stage statuses can be created and updated.

TC-006: Verify that all supported stage statuses are saved correctly.

TC-007: Verify that incomplete and failed stages are not treated as completed.

TC-008: Verify that academic exceptions can be recorded with relevant reasons and details.

TC-009: Verify that delayed completion and interrupted studies can be recorded.

TC-010: Verify that academic exceptions follow applicable progression rules.

TC-011: Verify that course completion is allowed when all mandatory academic requirements are fulfilled.

TC-012: Verify that course completion is rejected when mandatory academic requirements remain incomplete.

TC-013: Verify that academic progression history is preserved after status changes.

TC-014: Verify that academic progress remains independent across multiple course enrollments.

TC-015: Verify that examination eligibility requires full clearance of the applicable semester fee.

TC-016: Verify that unpaid semester fees block examination participation.

TC-017: Verify that partially paid semester fees block examination participation.

TC-018: Verify that approved discounts and fee waivers are reflected in examination eligibility calculations.

TC-019: Verify that an approved financial exception permits examination participation when applicable.

TC-020: Verify that deactivated or suspended students are restricted from applicable academic activities.

TC-021: Verify that passing an examination alone does not automatically complete an enrollment.

TC-022: Verify that certificate eligibility is granted only after all mandatory academic requirements and examinations are fulfilled.

TC-023: Verify that certificate eligibility remains independent across multiple course enrollments.

Policy alignment: This feature now reflects CMUD's enrollment-centric status policy, semester fee examination restriction, institutional status control, and academic certificate eligibility rules.

Note: Authorization and permission enforcement will be handled separately by the CMUD Security & User Access module. This feature focuses exclusively on academic progression, examination eligibility, course completion, and academic exceptions.