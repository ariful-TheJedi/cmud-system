# Student Billing Backend Implementation Instruction

## Objective

Implement the backend that supports the completed Student Billing UI at `/billings/student-bill`. Build the API and Finance module code against the existing database schema. Do not run or create database migrations.

The UI has two submission dialogs:

- **Create tuition payment**: Student ID, payment amount, payment date, payment method, optional remarks, and the bKash fields/status when Mobile Banking is selected.
- **Create student other bill**: the same payment fields plus an **Other expense item** selector. Creating the bill and recording its payment must be one operation.

The register search UI has only a student ID/name field, a payment date field, and a Search button. It shows paginated student payment records.

## Current Code and Schema

- Student billing controllers and services were removed. Rebuild the missing Finance controller/service layer and repair the student routes that still reference `StudentFinanceController`.
- Keep the existing Institute Billing controller, service, and routes intact.
- Tuition is stored on `enrollments`; there is no `student_tuitions` table. Tuition receipts are in `student_tuition_payments` and link through `enrollment_id`.
- Other student bills are in `student_expenses`. Their receipts use the `StudentExpensePayment` model and the `student_other_payments` table.
- `payment_methods` stores institute-specific methods. Payment records have `payment_method_id`, `financial_account_id`, `amount`, `paid_at`, `transaction_reference`, `payer_phone`, `status`, and verification fields including `verified_at`, `verified_by`, `verification_reference`, and `verification_response`.
- `financial_account_id` is required by the current schema even though the UI does not ask for a deposit account. Resolve an appropriate active account inside the service using the institute, branch, and payment method. Never require a client-supplied account field. Return a validation error if no eligible account is configured.
- Branch-scoped access is already represented by `RequireBillingBranch` and the user's `branch_id` / `is_super_admin` fields. Preserve the rule: branch users see and mutate only their branch's records; super admins can access all branches.
- The current schema has no separate student-ID verification record. Validate that the entered Student ID identifies a student with an eligible enrollment in the viewer's branch. Treat payment verification as a separate state.

## API Requirements

Use the existing API prefix and session-authenticated Finance routes. Keep routes organized in `app/Modules/Finance/routes/api.php`.

Use this API contract so the UI can be connected consistently:

- `GET /student-payments?search={student ID or name}&date=YYYY-MM-DD&page=1&per_page=15`
- `GET /students/{studentCode}/billing-options`
- `POST /student-payments/tuition`
- `POST /student-payments/other-expenses`

All paths are relative to the configured API prefix. Keep route names under the Finance module's existing naming convention.

### Student payment register

Implement a paginated `GET /student-payments` endpoint with:

- `search`: optional Student ID or student-name search.
- `date`: optional exact payment date (`YYYY-MM-DD`), applied server-side to `paid_at`.
- `page`: pagination page.
- `per_page`: defaults to 15 and is capped at a safe maximum.

Search both `student_tuition_payments` and `student_other_payments`, return their rows together in newest-payment-first order, and include pagination metadata. Apply branch scope in the database query before filtering and pagination. Super admins receive all branches.

Each row must contain the receipt number, payment amount/date/method/status, Student ID and name, branch, enrollment/course/batch context, bill category/description, and recorder. Include transaction ID and payer phone where appropriate so the UI can show and print the record. Pending bKash receipts must appear in the register and remain printable.

### Student billing options

Provide a branch-scoped read endpoint for a Student ID that returns the student and the billing choices needed by both dialogs:

- eligible enrollment(s) and their branch/course context;
- tuition status, payable amount, and outstanding balance;
- active payment methods for the enrollment's institute;
- active other-expense items with their currently approved, effective price;
- the server-resolved eligible account configuration needed to record a payment, without exposing a client-selected account requirement.

If one Student ID has multiple eligible enrollments, do not silently charge an arbitrary enrollment. Return a clear `422` response that identifies the need for an enrollment choice, or extend the UI to let the user choose one before submission. Apply branch scope before returning any student or enrollment data.

### Tuition payment submission

Provide a `POST` endpoint for tuition payment submission. Accept the Student ID, amount, payment date, payment method, transaction ID/reference, payer phone, remarks, and an idempotency key. Resolve the eligible enrollment and tuition obligation server-side; do not require a removed Student charge selector or a client-supplied financial account ID.

Validate that tuition is issued and not cancelled, the amount is positive and does not exceed the outstanding balance, and the payment method belongs to the student's institute and is active. Record the receipt against `student_tuition_payments.enrollment_id`.

### Other bill and payment submission

Provide a `POST` endpoint that creates the selected other-expense bill and records its payment atomically. Accept the Student ID, expense item ID, payment amount/date/method, bKash transaction ID and payer phone when applicable, remarks, and an idempotency key.

Resolve the student's eligible enrollment, active expense item, and effective approved price on the server. The UI currently has one expense item and no quantity input, so use quantity `1` unless the approved feature specification changes. Create the `student_expenses` row and its `student_other_payments` row in one database transaction. Reject payment amounts above the created bill's payable amount. A failed payment insert must roll back the bill insert.

### Frontend API integration

After implementing the API, connect the completed UI using the shared `useApi` hook in `resources/js/hooks/use-api.ts`:

- Use endpoint paths relative to the configured API prefix, such as `/student-payments`.
- Use the student billing options endpoint to resolve the Student ID, populate the other-expense item list, and load real institute payment-method IDs before submission. Replace temporary UI-only method IDs with the returned IDs.
- Send the selected date with the register request and render the server-filtered paginated response. Remove the current client-only date filter over the loaded page.
- Pass `AbortSignal` values for requests tied to component lifecycle. Show `ApiRequestError` validation errors to the user; do not replace failed requests with success-shaped defaults.
- Submit each form to its matching endpoint, display the returned receipt/bill, refresh the register, and allow printing from the returned record data.

## Payment Method and Verification Rules

- Support Cash and Mobile Banking with bKash as the current provider.
- Resolve the Cash and Mobile Banking/bKash method to active `payment_methods` rows for the student's institute. Preserve a provider-specific method identity using those existing rows/codes so additional providers can be added later without adding a column.
- For Cash, record the payment as confirmed according to the current ledger policy. The UI does not collect an account; resolve the eligible cash account internally.
- For bKash, require and store payer phone and transaction ID in `payer_phone` and `transaction_reference`. Record an unverified/pending status. Leave verification fields such as `verified_at`, `verified_by`, and provider response unset. Do not call a mobile banking API or mark a bKash payment verified in this feature.
- The bKash **Request verification** button is currently a UI placeholder. Do not create a verification endpoint until the provider API is approved for implementation.
- Keep idempotency behavior for both submission endpoints so retries do not create duplicate receipts or bills.

## Architecture and Error Responses

Follow the module flow:

`Route -> Form Request -> Controller -> Service -> Model/Database -> JSON response`

- Form Requests validate types, required fields, amounts, dates, payment method IDs, and bKash-specific fields. Keep database workflows out of requests.
- Controllers remain thin and return consistent JSON responses.
- Services own branch checks, payment/account resolution, transaction handling, balance calculations, idempotency, and status assignment.
- Return Laravel-appropriate status codes: `200` for reads, `201` for created records, `401` for unauthenticated requests, `403` for an unassigned non-admin billing user, `404` for unavailable/out-of-scope records, and `422` for validation or business-rule failures.
- Do not add module-specific authorization policy logic beyond the established branch rule.
- Add clear comments explaining non-obvious billing and verification decisions.
- Keep controller/service responsibilities separated according to the module conventions.

## Pest Verification

Write Pest feature tests for:

- payment register search by Student ID and name, exact date filtering, latest-first ordering, pagination, and both receipt sources;
- branch employees seeing only their branch and super admins seeing all branches;
- missing branch assignment returning `403`;
- valid and invalid tuition payment amounts and tuition states;
- Cash account resolution without a client-supplied account ID;
- bKash requiring payer phone and transaction ID and remaining pending/unverified;
- other bill plus payment succeeding atomically and rolling back on failure;
- duplicate idempotency keys not creating duplicate payments or bills;
- validation, not-found, and out-of-scope responses.

Never use the production database for tests. Do not run migrations. Run targeted Finance Pest tests and report exactly what ran, pass/fail counts, untested scenarios, and outstanding defects. Do not claim unrun tests passed.

## Implementation Boundary

Implement the backend and connect it to the completed UI using this contract. Keep the agreed UI design unchanged. Do not implement bKash provider verification or create schema migrations.
