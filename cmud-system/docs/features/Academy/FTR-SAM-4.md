

## FTR-SAM-04 — Courses & Academic Structure

**FTR-SAM-04-001 — Course and Batch Management**  
Original: FTR026  
Create, update, activate, deactivate, and manage courses and batches.

**FTR-SAM-04-002 — Course Stages and Semester Management**  
Original: FTR070  
Configure courses with one or multiple stages or semesters, including stage order and duration.

**FTR-SAM-04-003 — Course Duration and Completion Period Management**  
Original: FTR087  
Define standard course duration and permitted completion period separately.

**FTR-SAM-04-004 — Course Status and Availability Management**  
Original: FTR088  
Manage course availability for enrollment and define draft, active, inactive, or discontinued states.


## Description: 
Manage courses, batches, academic stages, semesters, course durations, and enrollment availability.

## requirements
FR-001 — Course Management

The system shall allow users to create, view, update, activate, and deactivate courses.

FR-002 — Batch Management

The system shall allow users to create, view, update, and manage batches under their respective courses.

FR-003 — Course Stage Management

The system shall allow users to configure one or multiple stages or semesters for each course.

FR-004 — Stage Sequence Management

The system shall allow users to define and update the sequence or order of stages within a course.

FR-005 — Stage Duration Management

The system shall allow users to define the duration of individual stages or semesters.

FR-006 — Standard Course Duration Management

The system shall allow users to define the standard expected duration required to complete a course.

FR-007 — Permitted Completion Period Management

The system shall allow users to define the maximum permitted period for completing a course separately from its standard duration.

FR-008 — Course Status Management

The system shall allow users to manage course statuses, including Draft, Active, Inactive, and Discontinued.

FR-009 — Course Enrollment Availability Management

The system shall allow users to control course availability for new student enrollment independently of other course information.

## Business Rules
BR-001: Every batch must belong to an existing course.

BR-002: A course may contain one or multiple academic stages or semesters.

BR-003: Each stage must have a defined sequence within its course.

BR-004: Standard course duration and permitted completion period must be maintained separately.

BR-005: Only courses available for enrollment can be selected for new student admissions.

BR-006: Deactivating a course must not automatically delete its existing batches, student enrollments, or academic records.

BR-007: Discontinued courses must not be available for new student enrollment.

BR-008: Changes to course structures must not invalidate existing student academic records.
Acceptance Criteria
AC-001: Users can successfully create, view, and update courses.

AC-002: Users can successfully create and manage batches under courses.

AC-003: Users can configure multiple stages or semesters within a course.

AC-004: Users can define and modify the sequence of course stages.

AC-005: Users can define individual stage durations.

AC-006: Users can define standard course duration and permitted completion period independently.

AC-007: Users can update course statuses.

AC-008: Users can control course enrollment availability.

AC-009: The system prevents new enrollment in unavailable or discontinued courses.

AC-010: Existing student academic records remain intact when course configurations change.

## Testing Requirements
TC-001: Verify successful course creation with valid information.

TC-002: Verify successful batch creation under an existing course.

TC-003: Verify that batch creation is rejected when no valid course is selected.

TC-004: Verify that multiple stages can be added to a course.

TC-005: Verify that stage sequence can be created and updated correctly.

TC-006: Verify that individual stage durations are saved correctly.

TC-007: Verify that standard course duration and permitted completion period are stored independently.

TC-008: Verify that all supported course statuses can be assigned correctly.

TC-009: Verify that students cannot enroll in courses unavailable for enrollment.

TC-010: Verify that deactivating a course does not delete existing batches or student academic records.

TC-011: Verify that discontinued courses cannot be selected for new admissions.

TC-012: Verify that updating course structures does not invalidate existing student academic records.





















## Feature Development Lifecycle

1. Confirm feature requirements and acceptance criteria.
2. Identify associated database tables and models.
3. Define validation rules, business logic, and permissions.
4. Design database queries and relationships.
5. Implement Laravel backend functionality.
6. Develop React/Inertia user interface.
7. Create Pest unit tests for business logic.
8. Create Pest feature tests for routing, validation, permissions, and database behavior.
9. Verify complete workflows using development seed data.
10. Record results and update the feature status.

## Feature Completion Criteria

A feature can be marked complete only when:

- Business requirements are implemented.
- Required database relationships work correctly.
- Backend behavior is verified.
- Frontend interface is functional.
- Authorization and validation are tested.
- Relevant Pest tests pass.
- End-to-end user workflows are verified.

---








