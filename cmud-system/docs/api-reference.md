# CMUD API Route Reference

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

Creates a student and their initial enrollment. Requires `auth` middleware and an active authenticated user who is a super administrator or has the `students.admissions.create` permission.

Required request fields:

| Field | Type | Validation |
| --- | --- | --- |
| `student_code` | string | Required, max 255, unique in `students`. |
| `admission_branch_id` | integer | Required, must exist in `branches`. |
| `batch_id` | integer | Required, must exist in `batches`. |
| `full_name` | string | Required, max 255. |
| `phone_numbers` | array of strings | Required when `phone` is absent; at least one; each required, max 30, distinct. |

Optional fields: `course_id` (nullable, existing course), `admission_date` (date), `father_name`, `mother_name` (strings up to 255), `date_of_birth` (date), `gender` (string up to 50), `phone` (string up to 30, required when `phone_numbers` absent), `alternative_phone` (string up to 30), `email` (valid email up to 255), `present_address`, `permanent_address`, `notes` (strings), `medical_college_id` (existing medical college), `qualification` (string up to 255), and `graduation_year` (integer from 1900 through 2100).

Additional relationship validation checks that the batch is offered at the selected admission branch, the branch belongs to the course's institute, and, when `course_id` is supplied, the batch belongs to that course. Validation errors return `422`; failed authorization returns `403`.

Success returns `201 Created` with `status`, `message`, and `data.student` (`id`, `student_code`, `full_name`) and `data.enrollment` (`id`, `enrollment_code`). An unexpected failure is logged and returns `500` with a generic failure message.

### `POST /api/v1/student/admission`

Legacy/alternate URL to the same `StudentAdmissionController@store` as `POST /api/v1/students/admissions`. It has the same authentication, authorization, validation, side effects, and response described above. Both URLs are currently registered.

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
