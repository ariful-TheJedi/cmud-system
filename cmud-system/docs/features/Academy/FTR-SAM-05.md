
## FTR-SAM-05 — Batch & Enrollment Management

**FTR-SAM-05-001 — Enrollment Changes**  
Original: FTR027  
Allow authorized changes to enrollment details, course assignments, and batches while maintaining relevant history.

**FTR-SAM-05-002 — Batch Assignment and Transfer**  
Original: FTR071  
Assign students to batches and support authorized transfers between eligible batches.

**FTR-SAM-05-003 — Enrollment Status Management**  
Original: FTR073  
Maintain separate enrollment statuses for each course, including active, completed, discontinued, or cancelled.

**FTR-SAM-05-004 — Course Enrollment Registration**  
Original: FTR090  
Enroll registered students in one or more courses while validating batch and duplicate enrollment rules.

**FTR-SAM-05-005 — Batch Lifecycle Management**  
Original: FTR091  
Manage batch dates and statuses such as upcoming, ongoing, completed, and cancelled.

**FTR-SAM-05-006 — Batch Capacity and Enrollment Validation**  
Original: FTR092  
Apply optional batch capacities and other approved admission restrictions.


Description: Manage student course enrollment, batch assignments, batch transfers, enrollment changes, enrollment statuses, and batch lifecycle while maintaining accurate enrollment history.
Functional Requirements
FR-001 — Enrollment Changes

The system shall allow authorized users to update existing enrollment details, including course assignments and batch assignments, while maintaining a history of previous changes.

FR-002 — Batch Assignment and Transfer

The system shall allow users to assign enrolled students to eligible batches and transfer students between eligible batches while maintaining their previous batch assignment history.

FR-003 — Enrollment Status Management

The system shall maintain separate enrollment statuses for each student course enrollment, including Active, Completed, Discontinued, and Cancelled.

FR-004 — Course Enrollment Registration

The system shall allow registered students to enroll in one or multiple courses while validating course availability, batch eligibility, and duplicate enrollment restrictions.

FR-005 — Batch Lifecycle Management

The system shall allow users to manage batch starting dates, ending dates, and lifecycle statuses, including Upcoming, Ongoing, Completed, and Cancelled.

FR-006 — Batch Capacity Management

The system shall allow users to define optional maximum student capacities for batches and prevent new enrollments when the configured capacity has been reached.

FR-007 — Enrollment Restriction Management

The system shall allow users to configure and enforce approved admission restrictions when registering students in courses or assigning them to batches.
Business Rules
BR-001: Only registered students can be enrolled in courses.

BR-002: A student may enroll in multiple courses.

BR-003: Duplicate active enrollment in the same course must be prevented unless explicitly permitted by the system's business rules.

BR-004: Students can only be assigned to batches belonging to their enrolled courses.

BR-005: Batch transfers must only be allowed between eligible batches.

BR-006: Previous enrollment and batch assignment details must be preserved when changes are made.

BR-007: Each course enrollment must maintain its own independent enrollment status.

BR-008: Completed, Discontinued, and Cancelled enrollments must not be treated as active enrollments.

BR-009: Batch capacity restrictions must only be enforced when a maximum capacity is configured.

BR-010: Cancelled or completed batches must not accept new student enrollments.

BR-011: Changes to enrollment details must not automatically delete previous enrollment history.

BR-012: Batch lifecycle status must reflect its actual academic availability and operational state.
Acceptance Criteria
AC-001: Users can enroll registered students in one or multiple courses.

AC-002: The system prevents duplicate active enrollment in the same course.

AC-003: Users can assign students to eligible batches.

AC-004: Users can transfer students between eligible batches.

AC-005: Previous batch assignments remain accessible after transfers.

AC-006: Users can update enrollment details while preserving change history.

AC-007: Each course enrollment maintains an independent status.

AC-008: Users can update enrollment statuses to Active, Completed, Discontinued, or Cancelled.

AC-009: Users can configure batch starting dates and ending dates.

AC-010: Users can manage batch statuses, including Upcoming, Ongoing, Completed, and Cancelled.

AC-011: Users can configure optional batch capacity limits.

AC-012: The system prevents enrollment when a batch reaches its configured capacity.

AC-013: The system permits enrollment in batches without capacity restrictions when no maximum capacity is configured.

AC-014: The system prevents enrollment in cancelled or completed batches.

AC-015: The system validates approved admission restrictions before completing enrollment.
Testing Requirements
TC-001: Verify successful enrollment of a registered student in a course.

TC-002: Verify that a student can enroll in multiple courses.

TC-003: Verify that duplicate active enrollment in the same course is rejected.

TC-004: Verify successful assignment of a student to an eligible batch.

TC-005: Verify that assignment to a batch belonging to another course is rejected.

TC-006: Verify successful transfer of a student between eligible batches.

TC-007: Verify that previous batch assignment history is preserved after a transfer.

TC-008: Verify that enrollment details can be updated without losing previous information.

TC-009: Verify that each course enrollment maintains an independent status.

TC-010: Verify that all supported enrollment statuses can be assigned correctly.

TC-011: Verify that batch starting and ending dates are saved and validated correctly.

TC-012: Verify that batch lifecycle statuses can be updated correctly.

TC-013: Verify that enrollment is rejected when the configured batch capacity is reached.

TC-014: Verify that enrollment is permitted when no batch capacity is configured.

TC-015: Verify that completed or cancelled batches reject new enrollments.

TC-016: Verify that configured admission restrictions are enforced during enrollment.

TC-017: Verify that cancelled, completed, or discontinued enrollments are not counted as active enrollments.

Note: Authorization and permission enforcement will be handled separately by the CMUD Security & User Access module. This feature focuses on enrollment business rules and history management.