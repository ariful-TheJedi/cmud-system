# CMUD API Route Reference

> **Tuition update (2026-11-25):** Tuition amounts, waiver approvals, issue/due dates and tuition status are stored on `enrollments`. Payments use `student_tuition_payments.enrollment_id` directly. Read tuition at `GET /api/v1/enrollments/{enrollment}/tuition`; record a payment at `POST /api/v1/enrollments/{enrollment}/tuition/payments`; load active payment methods and branch-compatible financial accounts at `GET /api/v1/enrollments/{enrollment}/payment-options`. Student tuition and other-bill payments use the selected enrollment's branch scope; non-super-admin users cannot read or record payments for another branch.

This document describes the API routes currently registered in the application. It is based on `routes/api.php`, the module route files, controllers, requests, and resources. Planned features and routes that are not registered are not included.

## API conventions

- **Base path:** `/api/v1`
- **Format:** JSON (`Accept: application/json`)
- **Route file:** `routes/api.php` loads module route files under the `v1` prefix.
- **Authentication:** Routes marked `auth` use Laravel's `auth` middleware. Routes without it currently have no authentication middleware at the route level.
- **Validation:** Invalid request data generally returns `422 Unprocessable Content`. Missing records resolved with `firstOrFail()` return `404 Not Found`.
- **Pagination:** Laravel paginator responses include pagination links and metadata.

The application configures JSON exception rendering for `/api/*`. No API-wide token or session scheme is defined in these route files; callers must use the authentication mechanism configured by the application when a route requires authentication.

## Shared / API root

### `GET /api/v1`

Returns a small API welcome payload. No authentication required.

```json
{
  "message": "Welcome to the CMUD System API.",
  "version": "1.0"
}
```

## Students & Academy

The Academy routes are registered from `app/Modules/Academy/routes/api.php`.

| Method | URL | Route name | Handler | Middleware |
| --- | --- | --- | --- | --- |
| `GET` | `/api/v1/students` | `students.index` | `StudentController@index` | `api` |
| `GET` | `/api/v1/academy-catalog/courses` | `academy.catalog.courses.index` | `AcademyCatalogController@courses` | `api` |
| `POST` | `/api/v1/academy-catalog/courses` | `academy.catalog.courses.store` | `AcademyCatalogController@storeCourse` | `api` |
| `GET` | `/api/v1/academy-catalog/courses/{course}` | `academy.catalog.courses.show` | `AcademyCatalogController@showCourse` | `api` |
| `PUT` | `/api/v1/academy-catalog/courses/{course}` | `academy.catalog.courses.update` | `AcademyCatalogController@updateCourse` | `api` |
| `DELETE` | `/api/v1/academy-catalog/courses/{course}` | `academy.catalog.courses.destroy` | `AcademyCatalogController@destroyCourse` | `api` |
| `GET` | `/api/v1/academy-catalog/batches` | `academy.catalog.batches.index` | `AcademyCatalogController@batches` | `api` |
| `POST` | `/api/v1/academy-catalog/batches` | `academy.catalog.batches.store` | `AcademyCatalogController@storeBatch` | `api` |
| `GET` | `/api/v1/academy-catalog/batches/{batch}` | `academy.catalog.batches.show` | `AcademyCatalogController@showBatch` | `api` |
| `PUT` | `/api/v1/academy-catalog/batches/{batch}` | `academy.catalog.batches.update` | `AcademyCatalogController@updateBatch` | `api` |
| `DELETE` | `/api/v1/academy-catalog/batches/{batch}` | `academy.catalog.batches.destroy` | `AcademyCatalogController@destroyBatch` | `api` |
| `GET` | `/api/v1/academy-catalog/subjects` | `academy.catalog.subjects.index` | `AcademyCatalogController@subjects` | `api` |
| `POST` | `/api/v1/academy-catalog/subjects` | `academy.catalog.subjects.store` | `AcademyCatalogController@storeSubject` | `api` |
| `GET` | `/api/v1/academy-catalog/subjects/{subject}` | `academy.catalog.subjects.show` | `AcademyCatalogController@showSubject` | `api` |
| `PUT` | `/api/v1/academy-catalog/subjects/{subject}` | `academy.catalog.subjects.update` | `AcademyCatalogController@updateSubject` | `api` |
| `DELETE` | `/api/v1/academy-catalog/subjects/{subject}` | `academy.catalog.subjects.destroy` | `AcademyCatalogController@destroySubject` | `api` |
| `GET` | `/api/v1/academy-catalog/teachers` | `academy.catalog.teachers.index` | `TeacherManagementController@index` | `api` |
| `POST` | `/api/v1/academy-catalog/teachers` | `academy.catalog.teachers.store` | `TeacherManagementController@store` | `api` |
| `GET` | `/api/v1/academy-catalog/teachers/{teacher}` | `academy.catalog.teachers.show` | `TeacherManagementController@show` | `api` |
| `PUT` | `/api/v1/academy-catalog/teachers/{teacher}` | `academy.catalog.teachers.update` | `TeacherManagementController@update` | `api` |
| `DELETE` | `/api/v1/academy-catalog/teachers/{teacher}` | `academy.catalog.teachers.destroy` | `TeacherManagementController@destroy` | `api` |

### Academy catalog: courses, batches, and subjects

The catalog endpoints are implemented by `AcademyCatalogController` and delegate persistence to `AcademyCatalogService`. Subjects are independent reusable modules. Course assignments live in `course_subject`, stage assignments live in `course_stage_subject`, and teacher assignments live in `subject_teacher`. All these routes currently use only the standard `api` middleware; no route-level authentication middleware is declared.

#### Courses

- `GET /api/v1/academy-catalog/courses` returns a paginated list (15 per page) with institute, subject, stage, and batch summaries.
- `GET /api/v1/academy-catalog/courses/{course}` returns one course with its institute, subjects, and batches. Unknown IDs return `404`.
- `POST /api/v1/academy-catalog/courses` creates a course. Required fields: `institute_id` (existing institute), `code`, `name`, positive `duration_months`, positive `completion_period_months`, and `course_mode` (`online`, `offline`, or `dual`). Optional fields: `description`, `is_active`, and `subject_ids` for direct module assignments when the course has no stages. Success returns `201` with the course and its modules under `data`.
- `PUT /api/v1/academy-catalog/courses/{course}` updates supplied course fields and optional direct `subject_ids`. Stage module assignments are managed through the nested stage endpoints. Success returns the updated course and its direct modules under `data`.
- `DELETE /api/v1/academy-catalog/courses/{course}` deletes a course only when it has no dependent stages, batches, enrollments, certificate templates, communication groups, price versions, student-expense price versions, or modules. In-use courses return `409`; successful deletion returns `200` with a message.

#### Batches

- `GET /api/v1/academy-catalog/batches` returns a paginated list (15 per page) with course and branch summaries.
- `GET /api/v1/academy-catalog/batches/{batch}` returns one batch with its course and branch. Unknown IDs return `404`.
- `POST /api/v1/academy-catalog/batches` creates a batch. Required fields: `course_id`, `branch_id`, `code`, `name`, `starts_on`, `expected_ends_on` (on or after start), and `status`. Optional fields: `admission_opens_on`, `admission_closes_on` (on or after opening date), `actual_ends_on`, `capacity` (positive integer), and `notes`. The course and branch must exist, and the branch must belong to the same institute as the selected course. Success returns `201` with the created record under `data`.
- `PUT /api/v1/academy-catalog/batches/{batch}` updates supplied fields with the same validation rules, including checking the resulting course/branch pair, and returns the updated record under `data`.
- `DELETE /api/v1/academy-catalog/batches/{batch}` deletes a batch only when it has no enrollments, student-stage records, or communication groups. In-use batches return `409`; successful deletion returns `200` with a message.

#### Subjects (course modules/topics)

- `GET /api/v1/academy-catalog/subjects` returns a paginated list (15 per page) with course and stage assignment summaries.
- `GET /api/v1/academy-catalog/subjects/{subject}` returns one independent module with its course, stage, and teacher assignments. Unknown IDs return `404`.
- `POST /api/v1/academy-catalog/subjects` creates a module. Required fields: unique `code` and `name`. Optional fields: `description` and `is_active`. Success returns `201` with the module under `data`.
- `PUT /api/v1/academy-catalog/subjects/{subject}` updates the module's own fields; assignment management is handled by course and stage endpoints.
- `DELETE /api/v1/academy-catalog/subjects/{subject}` deletes an unassigned module. Assigned modules return `409`; successful deletion returns `200` with a message.

Create/update validation errors return `422`. Collection routes return Laravel paginator links and metadata. No route-level authentication middleware is declared on these catalog routes.

#### Teachers

Teacher management is implemented by `TeacherManagementController` and `TeacherManagementService`. Teacher records belong to an institute, may reference a staff record, and may have module and branch assignments. These routes currently use only the standard `api` middleware; no route-level authentication middleware is declared.

- `GET /api/v1/academy-catalog/teachers` returns a paginated list (25 per page) with institute, staff, module, and branch summaries.
- `GET /api/v1/academy-catalog/teachers/{teacher}` returns one teacher with those relationships. Unknown IDs return `404`.
- `POST /api/v1/academy-catalog/teachers` creates a teacher. Required fields: `institute_id` (existing institute), unique `teacher_code`, `full_name`, and `phone`. Optional profile fields: `staff_id` (nullable, existing staff record), `email`, `qualification`, `specialization`, `designation`, `organization`, `bmdc_number`, `address`, `photo_path`, `is_active`, and `notes`. Optional `subject_assignments` accepts an array of objects with distinct existing `subject_id` values and optional `teaching_role`, `is_active`, and `notes`. Optional `branch_ids` is an array of distinct existing branch IDs. Success returns `201` with the teacher and linked records under `data`.
- `PUT /api/v1/academy-catalog/teachers/{teacher}` updates supplied fields under the same validation rules. Omitting `subject_assignments` or `branch_ids` preserves current links; providing either collection replaces that set of assignments, and an empty array clears it. Success returns the updated record under `data`.
- `DELETE /api/v1/academy-catalog/teachers/{teacher}` deletes a teacher only when no module assignment or institute expense references the teacher. In-use teachers return `409`; successful deletion removes branch links and returns `200` with a message.

Validation errors return `422`. Teacher create/update changes and assignment synchronization run in a database transaction.

### `GET /api/v1/students`

Searches and lists students, newest first by `created_at` and then `id` by default, and includes admission-branch and latest-enrollment summaries. Criteria can be combined; enrollment-related criteria are matched against the same enrollment record. A student is returned once even if multiple enrollments match.

| Query parameter | Type | Behavior / validation |
| --- | --- | --- |
| `search` | string | Partial, case-insensitive match against student code, name words, and phone numbers. |
| `student_code` | string | Partial, case-insensitive registration-code match. |
| `name` | string | Partial, case-insensitive match; every word must appear in the student's name. |
| `phone` | string | Partial match against the primary, alternate, and stored phone-number list. |
| `course_id`, `batch_id` | integer | Must identify existing records; match an enrollment. |
| `admission_date` | date | Exact admission-date match. |
| `admission_month` | integer | Admission month from `1` to `12`. Can be combined with year/date filters. |
| `admission_year` | integer | Admission year from `1900` to `2100`. |
| `student_status` | string | One of `active`, `inactive`, or `blocked`. |
| `enrollment_status` | string | One of `active`, `enrolled`, `ongoing`, `on hold`, `completed`, `withdrawn`, `cancelled`, or `none`. `none` returns students without enrollments; with `branch_id`, it matches their admission branch. `none` cannot be combined with course, batch, or stage filters. |
| `institute_id` | integer | Must identify an existing institute. |
| `branch_id` | integer | Must identify an existing branch. Matches the admission branch or an enrollment branch. When combined with other enrollment filters, it must match that same enrollment. |
| `stage_id` | integer | Must identify an existing course stage; matches a stage record belonging to the matched enrollment. |
| `date_from`, `date_to` | date | Inclusive admission-date range; `date_to` must be on or after `date_from`. |
| `sort_by` | string | One of `student_code`, `full_name`, `admission_date`, or `created_at`. Defaults to `created_at`. |
| `sort_direction` | string | `asc` or `desc`; defaults to `desc`. Student ID is used as a stable tie-breaker. |
| `page` | integer | Must be at least `1`. |
| `per_page` | integer | Defaults to `15`; must be between `1` and `100`. |
| `include_filters` | boolean | Optional. When `true`/`1`, adds a top-level `filters` object containing active lookup lists for courses, batches, branches, and course stages. |

Success returns `200 OK` with a Laravel resource collection and paginator links/metadata. When requested, the response also includes `filters.courses` (`id`, `name`), `filters.batches` (`id`, `name`, `course_id`), `filters.branches` (`id`, `name`), and `filters.stages` (`id`, `name`, `course_id`). Unsupported query parameters and invalid filter values return `422`. Empty matches return the same successful paginated response with an empty `data` array. No authentication middleware is declared on this route beyond the standard `api` middleware.

Each student item includes `id`, `student_code`, `full_name`, `phone`, `phone_numbers`, `status`, `admission_date`, `admission_branch`, and the latest matching `latest_enrollment` summary (including course, branch, and batch when present). The response is a Laravel resource collection with paginator metadata and links.

### `GET /api/v1/students/{studentCode}`

Returns a detailed student record located by `student_code`. Returns `404` when the code is not found. No authentication middleware is declared on this route.

The `data` resource includes profile fields, institute and admission-branch summaries, medical-college summary, enrollments and their course/branch/batch and tuition/payment details, plus other expenses and their payment details. Optional relations can be `null` or omitted according to the resource's loaded-relation behavior.

### `POST /api/v1/students/admissions`

Creates a student and their initial enrollment. No route-level authentication middleware or request authorization check is currently applied. A same-origin web form is also available at `POST /academy/students/admissions`, which uses the web CSRF protection.

Required request fields:

| Field | Type | Validation |
| --- | --- | --- |
| `student_code` | string | Required, max 255, unique in `students`. |
| `admission_branch_id` | integer | Required, must exist in `branches`. |
| `batch_id` | integer | Required, must exist in `batches`. |
| `full_name` | string | Required, max 255. |
| `phone_numbers` | array of strings | Required when `phone` is absent; at least one; each required, max 30, distinct. |

Optional fields: `course_id` (nullable, existing course), `admission_date` (date), `father_name`, `mother_name` (strings up to 255), `date_of_birth` (date), `gender` (string up to 50), `phone` (string up to 30, required when `phone_numbers` absent), `alternative_phone` (string up to 30), `email` (valid email up to 255), `present_address`, `permanent_address`, `notes` (strings), `medical_college_id` (existing medical college), `qualification` (string up to 255), and `graduation_year` (integer from 1900 through 2100).

Additional relationship validation checks that the batch is offered at the selected admission branch, the branch belongs to the course's institute, and, when `course_id` is supplied, the batch belongs to that course. Validation errors return `422`.

Success returns `201 Created` with `status`, `message`, and `data.student` (`id`, `student_code`, `full_name`) and `data.enrollment` (`id`, `enrollment_code`). An unexpected failure is logged and returns `500` with a generic failure message.

### `POST /api/v1/student/admission`

Alternate URL to the same `StudentAdmissionController@store` as `POST /api/v1/students/admissions`. It has the same validation, side effects, and response described above. Both URLs are currently registered and have no route-level authentication middleware.

### `POST /api/v1/students/{studentCode}/enrollments`

Creates an enrollment for the student identified by `studentCode`. The route currently has no authentication middleware declared.

Request body:

| Field | Type | Validation |
| --- | --- | --- |
| `course_id` | integer | Required; must exist in `courses`. |
| `batch_id` | integer | Required; must exist in `batches`. |
| `enrollment_date` | date | Required. |

Additional validation requires the course to belong to the student's institute, the batch to belong to the selected course, and the batch branch to belong to the course's institute. Success returns `201 Created` with `data.student_code` and an `enrollment` object containing `enrollment_code`, `course_id`, `batch_id`, `branch_id`, `enrollment_date`, and `status`. Invalid request data returns `422`; an unknown student returns `404`.

### `GET /api/v1/student/{studentCode}/edit`

Returns the student detail resource and a list of active branches belonging to that student's institute. No authentication middleware is declared.

Response shape:

```json
{
  "data": {},
  "branches": [{ "id": 1, "name": "Example Branch" }]
}
```

The `data` member uses the student detail resource. An unknown student returns `404`.

### `PUT /api/v1/student/{studentCode}/edit`

Updates the student's profile. No authentication middleware is declared. On success, returns the updated `StudentDetailResource` (the resource fields are described under `GET /api/v1/students/{studentCode}`). An unknown student returns `404`; validation errors return `422`.

Required fields: `student_code` (string, max 255, unique except for this student), `full_name` (string, max 255), `admission_branch_id` (integer, existing branch), and `phone_numbers` (array with at least one distinct string, each max 30).

Optional fields: `father_name`, `mother_name` (strings up to 255), `date_of_birth` (date), `gender` (string up to 50), `email` (valid email up to 255), `present_address`, `permanent_address` (strings), `admission_date` (date), `qualification` (string up to 255), `graduation_year` (integer from 1900 through 2100), and `bmdc_number` (string up to 255).

## Institute & Branches

The module route file exists at `app/Modules/Institute/routes/api.php`, but it currently registers no routes.

## Finance

The module route file exists at `app/Modules/Finance/routes/api.php`, but it currently registers no routes.

## Security & User Access

The module route file exists at `app/Modules/Security/routes/api.php`, but it currently registers no routes. Authentication and authorization middleware used by other routes are framework/application infrastructure, not routes exposed by this module file.

## Routes outside the API

Web and settings routes (including page and account-management routes) are registered separately in `routes/web.php` and `routes/settings.php`; they are not part of this API reference.

## Planned work not yet represented by routes

The draft `FTR-SAM-03-003` academic-timeline endpoint is not registered yet. The timeline is a separate feature from student search and will need its event-source and response decisions before implementation.
