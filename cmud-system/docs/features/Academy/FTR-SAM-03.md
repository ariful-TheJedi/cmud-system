# FTR-SAM-03 --- Student Search & Records

**Module:** Students & Academy\
**Feature Group:** Student Search & Records\
**Specifications:** 3\
**Status:** Draft

------------------------------------------------------------------------

# FTR-SAM-03-001 --- Student Search and Filters

  Field      Value
  ---------- ---------------------
  Source     Original FTR022
  Module     Students & Academy
  Priority   To confirm
  Status     Draft
  Delivery   API first; UI later

## 1. Goal

Provide a student search facility that allows users to find registered
students using common identifying information and basic academic and
admission-related filters.

## 2. Requirements & rules

-   Search students using student registration code, name, and phone
    number.
-   Filter students by course and batch.
-   Filter students by admission date, month, and year.
-   Support partial-name and partial-phone searches where appropriate.
-   Allow multiple search criteria to be combined.
-   Return student information with relevant enrollment details where
    applicable.
-   Support pagination for large search results.
-   Provide consistent search behavior across supported criteria.
-   Return an appropriate empty result when no matching student is
    found.
-   Use existing student and enrollment records without creating
    duplicate search-specific data.
-   Avoid loading unnecessary related records when retrieving search
    results.

**Not included:** Advanced multi-condition reporting, student profile
editing, duplicate detection, academic timeline, or report generation
and export.

## 3. Dependencies & API

-   **Models:** `Student`, `Enrollment`, `Course`, `Batch`.
-   **Modules:** Students & Academy (owner); Institute & Branches
    (related organizational references where required).
-   **Dependencies:** FTR-SAM-01-001 (Student Admission), FTR-SAM-01-003
    (Student Identity and Unique ID Management), and the approved
    student profile feature.
-   **API (proposed):** `GET /api/students` --- accepts search and
    filter query parameters.
-   **Query parameters (proposed):** `search`, `student_code`, `name`,
    `phone`, `course_id`, `batch_id`, `admission_date`,
    `admission_month`, `admission_year`, `page`, `per_page`.
-   **Responses (proposed):** `200` successful search, `422` invalid
    query parameters.

## 4. Pest test scenarios

  -----------------------------------------------------------------------
  Type                                Required checks
  ----------------------------------- -----------------------------------
  Unit                                Search criteria handling, query
                                      construction, filter combinations,
                                      and partial matching rules

  Feature / API                       Search by student code, name, and
                                      phone; filter by course, batch,
                                      admission date, month, and year;
                                      combined filters; pagination;
                                      invalid parameters

  Integration (PostgreSQL)            Search results match actual student
                                      and enrollment relationships;
                                      multiple enrollments do not create
                                      duplicate student rows; date
                                      filtering and sorting work
                                      correctly

  Manual (Postman/Bruno)              Create fictional student records →
                                      search using different criteria →
                                      combine filters → verify returned
                                      records and pagination → test
                                      no-result and invalid-query
                                      scenarios
  -----------------------------------------------------------------------

**Suggested files:** - `tests/Unit/Academy/StudentSearchQueryTest.php` -
`tests/Feature/Academy/StudentSearchTest.php` -
`tests/Integration/Academy/StudentSearchIntegrationTest.php`

Run targeted Pest tests on a **separate PostgreSQL test database**,
followed by relevant regression tests. Report actual results only.

## 5. Acceptance / API Done

-   [ ] Search by student registration code, name, and phone works.
-   [ ] Course and batch filters work correctly.
-   [ ] Admission date, month, and year filters work correctly.
-   [ ] Multiple search criteria can be combined.
-   [ ] Pagination works correctly.
-   [ ] Multiple enrollments do not produce unintended duplicate student
    results.
-   [ ] Empty results and invalid parameters are handled correctly.
-   [ ] Relevant Pest tests and regression tests pass.
-   [ ] Manual API verification and developer review pass.

**API Done ≠ UI Done.** UI delivery is tracked separately.

## 6. Decisions to confirm before implementation

1.  Which student information should appear in search results?
2.  Should phone search support partial matching or exact matching?
3.  Should searching by name support partial words and multiple words?
4.  Should students with multiple enrollments appear once or separately
    for each enrollment?
5.  Which default sorting order should be used?
6.  What should be the default and maximum pagination limits?

**Tracking:** GitHub Issue: --- · PR: --- · Tests: Not run · Manual
verification: Pending

------------------------------------------------------------------------

# FTR-SAM-03-002 --- Advanced Student Search and Filtering

  Field      Value
  ---------- ---------------------
  Source     Original FTR037
  Module     Students & Academy
  Priority   To confirm
  Status     Draft
  Delivery   API first; UI later

## 1. Goal

Extend the basic student search functionality with advanced filtering
and combined search conditions to support more detailed student record
retrieval.

## 2. Requirements & rules

-   Extend the existing student search functionality from
    FTR-SAM-03-001.
-   Support combining multiple search conditions in a single request.
-   Support filtering by student status and enrollment status
    separately.
-   Support filtering by institute and branch where relevant.
-   Support advanced academic filters such as course, batch, and
    academic stage.
-   Support admission date ranges.
-   Support students with no current enrollment where applicable.
-   Allow sorting search results using approved fields.
-   Ensure filtering across multiple enrollments does not
    unintentionally duplicate student records.
-   Use the same underlying student search service as FTR-SAM-03-001.
-   Keep query construction reusable and maintainable.
-   Validate unsupported filter fields and invalid filter values.
-   Optimize queries to handle larger student datasets without
    unnecessary database loading.

**Not included:** Student profile editing, student data correction,
academic timeline, dashboard analytics, or report export.

## 3. Dependencies & API

-   **Models:** `Student`, `Enrollment`, `Course`, `Batch`,
    `CourseStage`, `StudentStageRecord`, `Institute`, `Branch`.
-   **Modules:** Students & Academy (owner), Institute & Branches
    (organizational references).
-   **Dependencies:** FTR-SAM-03-001 (Student Search and Filters),
    FTR-SAM-01-004 (Student Status Management), and the approved
    academic-stage progression feature.
-   **API (proposed):** `GET /api/students` --- extended query
    parameters using the existing search endpoint.
-   **Query parameters (proposed):** `student_status`,
    `enrollment_status`, `institute_id`, `branch_id`, `stage_id`,
    `date_from`, `date_to`, `sort_by`, `sort_direction`, in addition to
    the basic search parameters.
-   **Responses (proposed):** `200` successful search, `422` invalid
    filters or sorting parameters.

**Implementation note:** Advanced search should extend the existing
search service and endpoint rather than introduce a second, duplicate
search implementation.

## 4. Pest test scenarios

  -----------------------------------------------------------------------
  Type                                Required checks
  ----------------------------------- -----------------------------------
  Unit                                Advanced filter combinations,
                                      filter validation, sorting rules,
                                      and reusable query construction

  Feature / API                       Combined filters; student and
                                      enrollment status filters;
                                      institute and branch filters;
                                      academic stage filtering; date
                                      ranges; sorting; invalid filters

  Integration (PostgreSQL)            Correct filtering across multiple
                                      relationships; no unintended
                                      duplicate students; students with
                                      multiple enrollments; students
                                      without enrollments; query
                                      performance with representative
                                      seeded data

  Manual (Postman/Bruno)              Create fictional students across
                                      different courses, branches,
                                      batches, and statuses → apply
                                      combined filters → verify results →
                                      test sorting, date ranges, and
                                      empty results
  -----------------------------------------------------------------------

**Suggested files:** -
`tests/Unit/Academy/AdvancedStudentSearchTest.php` -
`tests/Feature/Academy/AdvancedStudentSearchTest.php` -
`tests/Integration/Academy/AdvancedStudentSearchIntegrationTest.php`

Run targeted Pest tests on a **separate PostgreSQL test database**,
followed by relevant regression tests. Report actual results only.

## 5. Acceptance / API Done

-   [ ] Advanced filters work independently and in combination.
-   [ ] Student and enrollment statuses are filtered independently.
-   [ ] Institute, branch, course, batch, and academic stage filters
    work correctly.
-   [ ] Date-range filtering works correctly.
-   [ ] Sorting supports only approved fields and directions.
-   [ ] Multiple enrollments do not create unintended duplicate student
    results.
-   [ ] Students without enrollments are handled according to approved
    rules.
-   [ ] Existing basic search functionality remains functional.
-   [ ] Relevant Pest tests and regression tests pass.
-   [ ] Manual API verification and developer review pass.

**API Done ≠ UI Done.** UI delivery is tracked separately.

## 6. Decisions to confirm before implementation

1.  Which advanced filters are required for the first release?
2.  Should institute and branch filtering be mandatory or optional?
3.  Should student status and enrollment status both be available?
4.  Which academic stages should be searchable?
5.  Which sorting fields and default sorting order are required?
6.  What dataset size should be used for search-performance
    verification?
7.  Should advanced search support saved filter combinations in a future
    release?

**Tracking:** GitHub Issue: --- · PR: --- · Tests: Not run · Manual
verification: Pending

------------------------------------------------------------------------

# FTR-SAM-03-003 --- Student Academic Timeline

  Field      Value
  ---------- ---------------------
  Source     Original FTR086
  Module     Students & Academy
  Priority   To confirm
  Status     Draft
  Delivery   API first; UI later

## 1. Goal

Provide a chronological view of a student's important academic and
administrative events throughout their relationship with CMUD.

## 2. Requirements & rules

-   Retrieve the timeline for a selected student.
-   Display the student's original admission date and registration
    event.
-   Display course enrollment events.
-   Display batch assignment and transfer events where recorded.
-   Display academic stage progression events.
-   Display examination completion and result events.
-   Display certificate approval, issuance, and delivery events.
-   Present events in chronological order.
-   Include the event date, event type, relevant reference, and a short
    description.
-   Support filtering by event category and date range.
-   Support pagination for students with extensive academic histories.
-   Maintain a consistent timeline format across different event types.
-   Retrieve event information from existing module records rather than
    duplicating underlying business data.
-   Handle missing or incomplete historical information without breaking
    the timeline.
-   Use the actual business event date where available, rather than
    automatically relying on database creation timestamps.

**Not included:** Student profile editing, communication history,
complaint history, financial transaction timeline, or a general-purpose
audit log.

## 3. Dependencies & API

-   **Models:** `Student`, `Enrollment`, `StudentStageRecord`,
    `ExamRecord`, `Certificate`.
-   **Modules:** Students & Academy (owner); other business modules may
    provide related events only where explicitly approved.
-   **Dependencies:** Student Admission, Enrollment Changes, Student
    Stage Progression, Manual Exam Completion, Exam Results, and
    Certificate Approval/Issuance features. Confirm the final feature
    IDs against the approved Students & Academy feature register.
-   **API (proposed):** `GET /api/students/{student}/academic-timeline`.
-   **Query parameters (proposed):** `event_type`, `date_from`,
    `date_to`, `page`, `per_page`, `sort_direction`.
-   **Responses (proposed):** `200` successful timeline retrieval, `404`
    student not found, `422` invalid filters or query parameters.

**Implementation note:** Use existing academic tables as event sources.
Do not introduce a separate timeline table unless existing records
cannot reliably preserve required historical events. Any additional
event-history storage must be justified during schema review.

## 4. Pest test scenarios

  -----------------------------------------------------------------------
  Type                                Required checks
  ----------------------------------- -----------------------------------
  Unit                                Timeline event formatting, event
                                      aggregation, chronological
                                      ordering, event categorization, and
                                      date handling

  Feature / API                       Retrieve timeline for valid
                                      student; nonexistent student;
                                      event-type filtering; date-range
                                      filtering; pagination; ascending
                                      and descending order

  Integration (PostgreSQL)            Correct events aggregated from
                                      student, enrollment, stage,
                                      examination, and certificate
                                      records; relationships correctly
                                      associated with the student;
                                      missing optional records handled
                                      correctly; no duplicate timeline
                                      events

  Manual (Postman/Bruno)              Create fictional student → add
                                      enrollment, stage, exam, and
                                      certificate records → retrieve
                                      timeline → verify event order,
                                      dates, categories, and filtering
  -----------------------------------------------------------------------

**Suggested files:** -
`tests/Unit/Academy/StudentAcademicTimelineTest.php` -
`tests/Feature/Academy/StudentAcademicTimelineTest.php` -
`tests/Integration/Academy/StudentAcademicTimelineIntegrationTest.php`

Run targeted Pest tests on a **separate PostgreSQL test database**,
followed by relevant regression tests. Report actual results only.

## 5. Acceptance / API Done

-   [ ] Student academic timeline can be retrieved successfully.
-   [ ] Admission and enrollment events appear correctly.
-   [ ] Academic progression events appear correctly where recorded.
-   [ ] Examination completion and result events appear correctly.
-   [ ] Certificate events appear correctly.
-   [ ] Events are ordered chronologically.
-   [ ] Event categories and date-range filters work correctly.
-   [ ] Pagination works for extensive histories.
-   [ ] Missing historical records do not break timeline retrieval.
-   [ ] Duplicate events are prevented.
-   [ ] Relevant Pest tests and regression tests pass.
-   [ ] Manual API verification and developer review pass.

**API Done ≠ UI Done.** UI delivery is tracked separately.

## 6. Decisions to confirm before implementation

1.  Which event types must appear in the first release?
2.  Should the timeline include only academic events or also
    administrative record changes?
3.  Should batch transfers and enrollment changes appear as separate
    events?
4.  Which date should be used when an event has both an effective date
    and a database creation timestamp?
5.  Should the timeline support filtering by course?
6.  Do the existing database tables preserve enough historical
    information to reconstruct previous events?
7.  Should historical events be editable, or should corrections be
    represented as new events?

**Tracking:** GitHub Issue: --- · PR: --- · Tests: Not run · Manual
verification: Pending

------------------------------------------------------------------------

# Group Completion Checklist

-   [ ] FTR-SAM-03-001 --- Student Search and Filters
-   [ ] FTR-SAM-03-002 --- Advanced Student Search and Filtering
-   [ ] FTR-SAM-03-003 --- Student Academic Timeline

**Group Status:** Draft --- Not Started

**Testing Policy:** Use a separate PostgreSQL test database. Run
targeted Pest tests, relevant regression tests, and the full test suite
before release. Record actual results only.

**Security Integration:** Security & User Access is a separate
cross-cutting layer and will be implemented later. Do not develop
module-specific authorization or permission management as part of these
features. Design routes to support future security middleware
integration.
