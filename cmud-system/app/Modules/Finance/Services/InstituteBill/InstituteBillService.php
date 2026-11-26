<?php

namespace App\Modules\Finance\Services\InstituteBill;

use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpenseCategory;
use App\Models\Finance\InstituteExpensePayment;
use App\Models\Finance\InstituteExpensePriceVersion;
use App\Models\Finance\InstituteExpenseType;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Read, search, and create institute bills using the Finance expense ledger. */
class InstituteBillService
{
    /**
     * Return paginated bills with their payment balance and optional register filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{bills: LengthAwarePaginator, total: int}
     */
    public function list(array $filters, User $user): array
    {
        $query = $this->billQuery();
        $this->applyBranchScope($query, $user);
        $this->applyFilters($query, $filters);

        $bills = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return ['bills' => $bills];
    }

    /** Return one bill with item, approval, and payment detail. */
    public function show(InstituteExpense $bill, User $viewer): array
    {
        $this->assertBillBranchAccess($bill, $viewer);

        return $this->formatBill(
            $bill->loadMissing([
                'expenseType:id,institute_id,name',
                'expenseCategory:id,institute_expense_type_id,name,code',
                'branch:id,institute_id,name,code,phone,email,address',
                'department:id,institute_id,name,code',
                'priceVersion:id,unit_name',
                'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
                'recorder:id,name,designation',
                'recorder.stuff:id,user_id,stuff_code',
                'payments.paymentMethod:id,name',
                'payments.financialAccount:id,name,account_type',
            ]),
        );
    }

    /** Return selectable billing items and locations for the bill creation form. */
    public function formOptions(User $user): array
    {
        $branch = $user->is_super_admin ? null : Branch::query()->find($user->branch_id);
        $instituteId = $branch?->institute_id;

        return [
            // Type names remain available to filter the bill register; generation uses billing_items only.
            'types' => InstituteExpenseType::query()
                ->where('is_active', true)
                ->when(! $user->is_super_admin, fn (Builder $query) => $query->where('institute_id', $instituteId ?? 0))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when(! $user->is_super_admin, fn (Builder $query) => $query->whereKey($branch?->id ?? 0))
                ->orderBy('name')
                ->get(['id', 'institute_id', 'name', 'code'])
                ->toArray(),
            'departments' => Department::query()
                ->where('is_active', true)
                ->when(! $user->is_super_admin, fn (Builder $query) => $query->where('institute_id', $instituteId ?? 0))
                ->orderBy('name')
                ->get(['id', 'institute_id', 'name', 'code'])
                ->toArray(),
            'cash_accounts' => FinancialAccount::query()
                ->where('account_type', 'cash')
                ->where('is_active', true)
                ->when(! $user->is_super_admin, fn (Builder $query) => $query
                    ->where('institute_id', $instituteId ?? 0)
                    ->where(fn (Builder $accountQuery) => $accountQuery
                        ->whereNull('branch_id')
                        ->orWhere('branch_id', $branch?->id ?? 0)))
                ->orderBy('name')
                ->get(['id', 'institute_id', 'branch_id', 'name', 'code'])
                ->toArray(),
            'payees' => InstituteExpense::query()
                ->whereNotNull('payee_name')
                ->where('payee_name', '<>', '')
                ->whereHas('expenseType', fn (Builder $query) => $query->where('is_active', true))
                ->when(! $user->is_super_admin, fn (Builder $query) => $query->where('branch_id', $branch?->id ?? 0))
                ->select('payee_name')
                ->distinct()
                ->orderBy('payee_name')
                ->pluck('payee_name')
                ->all(),
        ];
    }

    /** Search active billable items and include their approved configured prices. */
    public function searchBillingItems(string $search, User $user): array
    {
        $branch = $user->is_super_admin ? null : Branch::query()->find($user->branch_id);
        $instituteId = $branch?->institute_id;

        return InstituteExpenseCategory::query()
            ->with([
                'expenseType:id,institute_id,name,is_active',
                'priceVersions' => function ($query) use ($user, $branch): void {
                    $query
                        ->select(['id', 'institute_expense_category_id', 'branch_id', 'amount', 'unit_name', 'effective_from', 'effective_to', 'status'])
                        ->where('status', 'approved')
                        ->when(! $user->is_super_admin, fn (Builder $priceQuery) => $priceQuery
                            ->where(fn (Builder $scopeQuery) => $scopeQuery
                                ->whereNull('branch_id')
                                ->orWhere('branch_id', $branch?->id ?? 0)))
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id');
                },
            ])
            ->where('is_active', true)
            ->when(! $user->is_super_admin, fn (Builder $query) => $query->whereHas(
                'expenseType',
                fn (Builder $typeQuery) => $typeQuery->where('institute_id', $instituteId ?? 0),
            ))
            ->whereHas('expenseType', fn (Builder $query) => $query->where('is_active', true))
            ->when(trim($search) !== '', function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($search)).'%']);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'institute_expense_type_id', 'code', 'name'])
            ->map(fn (InstituteExpenseCategory $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'is_other' => strtolower($item->code) === 'other' || strtolower($item->name) === 'other',
                'institute_id' => $item->expenseType->institute_id,
                'prices' => $item->priceVersions
                    ->where('status', 'approved')
                    ->map(fn (InstituteExpensePriceVersion $price): array => [
                        'id' => $price->id,
                        'branch_id' => $price->branch_id,
                        'amount' => (string) $price->amount,
                        'unit_name' => $price->unit_name,
                        'effective_from' => $price->getRawOriginal('effective_from'),
                        'effective_to' => $price->getRawOriginal('effective_to'),
                    ])->values()->all(),
            ])
            ->all();
    }

    /** Create a pending bill and return it in the register and detail response shape. */
    public function create(array $data, User $recorder, InstituteBillNumberPolicy $numberPolicy): array
    {
        // Super-user bills are institute-wide; regular users are constrained to their assigned branch.
        if ($recorder->is_super_admin) {
            $data['branch_id'] = null;
        } elseif ($recorder->branch_id !== null) {
            $data['branch_id'] = (int) $recorder->branch_id;
        } else {
            throw ValidationException::withMessages([
                'branch_id' => 'Assign this user to a branch before creating an institute bill.',
            ]);
        }
        $recordedBy = (int) $recorder->getAuthIdentifier();

        $category = InstituteExpenseCategory::query()
            ->with('expenseType:id,institute_id,name,is_active')
            ->where('is_active', true)
            ->findOrFail($data['billing_item_id']);
        $type = $category->expenseType;
        if (! $type || ! $type->is_active) {
            throw ValidationException::withMessages([
                'billing_item_id' => 'Select an active billing item.',
            ]);
        }
        $instituteId = (int) $type->institute_id;
        $isOtherCategory = strtolower($category->code) === 'other' || strtolower($category->name) === 'other';
        $billName = $isOtherCategory ? trim((string) ($data['billing_name'] ?? '')) : $category->name;
        if ($isOtherCategory && $billName === '') {
            throw ValidationException::withMessages([
                'billing_name' => 'Enter a name for the Other billing item.',
            ]);
        }
        if ($isOtherCategory && (! isset($data['amount']) || (float) $data['amount'] <= 0)) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a custom amount greater than zero for the Other billing item.',
            ]);
        }
        $priceVersion = null;
        if (! $isOtherCategory) {
            $priceVersion = InstituteExpensePriceVersion::query()
                ->whereKey($data['price_version_id'] ?? 0)
                ->where('institute_expense_category_id', $category->id)
                ->where('status', 'approved')
                ->whereDate('effective_from', '<=', $data['billing_date'])
                ->where(function (Builder $query) use ($data): void {
                    $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['billing_date']);
                })
                ->where(function (Builder $query) use ($data): void {
                    $query->whereNull('branch_id');
                    if (filled($data['branch_id'] ?? null)) {
                        $query->orWhere('branch_id', $data['branch_id']);
                    }
                })
                ->first();

            if ($priceVersion === null) {
                throw ValidationException::withMessages([
                    'price_version_id' => 'Choose an approved price that is effective for the selected date and branch.',
                ]);
            }
        }

        $this->validateLocations($instituteId, $data);
        $initialStatus = $data['status'] ?? 'pending';

        // Resolve and validate payment configuration before opening the creation transaction.
        $cashPayment = $data['cash_payment'] ?? null;
        $cashAccount = null;
        $cashMethod = null;
        if (is_array($cashPayment)) {
            if ($initialStatus !== 'approved') {
                throw ValidationException::withMessages([
                    'cash_payment' => 'A bill must be approved before a payment can be recorded.',
                ]);
            }
            if ($recordedBy === null) {
                throw ValidationException::withMessages([
                    'cash_payment' => 'An authenticated user is required to record a cash payment.',
                ]);
            }

            [$cashMethod, $cashAccount] = $this->cashPaymentConfiguration(
                $instituteId,
                isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                (int) $cashPayment['financial_account_id'],
            );

            $billAmountCents = $this->lineTotalCents(
                $data['quantity'],
                $isOtherCategory ? $data['amount'] : $priceVersion->amount,
            );
            if ($this->toCents($cashPayment['amount']) > $billAmountCents) {
                throw ValidationException::withMessages([
                    'cash_payment.amount' => 'The cash payment cannot exceed the bill total.',
                ]);
            }
        }

        $bill = DB::transaction(function () use ($data, $recordedBy, $type, $category, $priceVersion, $isOtherCategory, $billName, $numberPolicy, $cashPayment, $cashAccount, $cashMethod, $initialStatus): InstituteExpense {
            // The database assigns the numeric primary key; this module assigns the human-readable invoice number.
            $invoiceNumber = $numberPolicy->generate();
            $status = $initialStatus;
            $expense = InstituteExpense::query()->create([
                // Keep the legacy public identifier aligned until older finance consumers migrate to invoice_number.
                'expense_number' => $invoiceNumber,
                'bill_name' => $billName,
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'institute_expense_type_id' => $type->id,
                'institute_expense_category_id' => $category->id,
                'institute_expense_price_version_id' => $priceVersion?->id,
                'payee_name' => $data['payee_name'],
                'invoice_number' => $invoiceNumber,
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'quantity' => number_format((int) $data['quantity'], 3, '.', ''),
                'unit_amount' => number_format((float) ($isOtherCategory ? $data['amount'] : $priceVersion->amount), 2, '.', ''),
                'expense_date' => $data['billing_date'],
                'due_on' => $data['due_date'] ?? null,
                'status' => $status,
                'approved_by' => $status === 'approved' ? $recordedBy : null,
                'approved_at' => $status === 'approved' ? now() : null,
                'cancelled_at' => $status === 'cancelled' ? now() : null,
                'recorded_by' => $recordedBy,
                'notes' => $data['notes'] ?? null,
            ]);

            if (is_array($cashPayment) && $cashAccount !== null && $cashMethod !== null) {
                // Cash is received during creation, so record it as immediately confirmed and manually verified.
                $this->createConfirmedCashPayment(
                    $expense,
                    $cashMethod,
                    $cashAccount,
                    $cashPayment['amount'],
                    (int) $recordedBy,
                );
            }

            return $expense->load($this->detailRelations());
        });

        return $this->formatBill($bill);
    }

    /** Record cash against an existing approved bill, allowing partial payments. */
    public function recordCashPayment(InstituteExpense $bill, array $data, User $recordedBy): array
    {
        DB::transaction(function () use ($bill, $data, $recordedBy): void {
            // Serialize payment writes so two concurrent requests cannot overpay the remaining balance.
            $lockedBill = InstituteExpense::query()
                ->with('expenseType:id,institute_id')
                ->lockForUpdate()
                ->findOrFail($bill->id);

            $this->assertBillIssuerOrSuperUser($lockedBill, $recordedBy);
            if ($lockedBill->getRawOriginal('status') !== 'approved') {
                throw ValidationException::withMessages([
                    'bill' => 'The bill must be approved before a payment can be recorded.',
                ]);
            }

            [$cashMethod, $cashAccount] = $this->cashPaymentConfiguration(
                (int) $lockedBill->expenseType->institute_id,
                $lockedBill->branch_id === null ? null : (int) $lockedBill->branch_id,
                (int) $data['financial_account_id'],
            );

            $billTotal = $this->lineTotalCents($lockedBill->quantity, $lockedBill->unit_amount);
            $paidAmount = $this->toCents($lockedBill->payments()
                ->where('status', 'confirmed')
                ->sum('amount'));
            $paymentAmount = $this->toCents($data['amount']);

            if ($paymentAmount > max(0, $billTotal - $paidAmount)) {
                throw ValidationException::withMessages([
                    'amount' => 'The cash payment cannot exceed the bill balance.',
                ]);
            }

            $this->createConfirmedCashPayment(
                $lockedBill,
                $cashMethod,
                $cashAccount,
                $data['amount'],
                (int) $recordedBy->getAuthIdentifier(),
            );
        });

        return $this->show($bill->fresh(), $recordedBy);
    }

    /**
     * Update a bill's approval state while keeping its audit timestamps and actor consistent.
     * Payment status remains derived from confirmed payments and is not changed here.
     *
     * @param  array{status: string, cancellation_reason?: string|null}  $data
     */
    public function updateStatus(InstituteExpense $bill, array $data, User $updatedBy): array
    {
        DB::transaction(function () use ($bill, $data, $updatedBy): void {
            // Lock the row so concurrent decisions cannot leave stale audit metadata behind.
            $bill = InstituteExpense::query()->lockForUpdate()->findOrFail($bill->id);

            // Status actions follow billing branch access: branch staff manage their branch's bills, super admins manage all.
            $this->assertBillBranchAccess($bill, $updatedBy);

            $status = $data['status'];

            $bill->status = $status;
            $bill->approved_by = $status === 'approved' ? $updatedBy->getAuthIdentifier() : null;
            $bill->approved_at = $status === 'approved' ? now() : null;
            $bill->cancelled_at = $status === 'cancelled' ? now() : null;
            $bill->cancellation_reason = $status === 'cancelled'
                ? ($data['cancellation_reason'] ?? $bill->cancellation_reason)
                : null;
            $bill->save();
        });

        return $this->show($bill->fresh(), $updatedBy);
    }

    /** Build the shared eager-load plan for bill rows and their payment totals. */
    private function billQuery(): Builder
    {
        return InstituteExpense::query()
            ->select('institute_expenses.*')
            ->with([
                'expenseType:id,institute_id,name',
                'expenseCategory:id,institute_expense_type_id,name,code',
                'branch:id,institute_id,name,code',
                'department:id,institute_id,name,code',
                'priceVersion:id,unit_name',
                'recorder:id,name,designation',
                'recorder.stuff:id,user_id,stuff_code',
            ])
            ->withSum([
                'payments as confirmed_paid_amount' => fn ($query) => $query->where('status', 'confirmed'),
            ], 'amount');
    }

    /** @return array<int, string> */
    private function detailRelations(): array
    {
        return [
            'expenseType:id,institute_id,name',
            'expenseCategory:id,institute_expense_type_id,name,code',
            'branch:id,institute_id,name,code,phone,email,address',
            'department:id,institute_id,name,code',
            'priceVersion:id,unit_name',
            'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
            'payments.paymentMethod:id,name',
            'payments.financialAccount:id,name,account_type',
            'recorder:id,name,designation',
            'recorder.stuff:id,user_id,stuff_code',
        ];
    }

    /** Apply ID, billing name, type, date, branch, and paid-balance filters. */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['id'] ?? null)) {
            $id = trim((string) $filters['id']);
            $query->where(function (Builder $idQuery) use ($id): void {
                if (ctype_digit($id)) {
                    $idQuery->whereKey((int) $id);
                }

                $this->applyTextMatch($idQuery, 'institute_expenses.invoice_number', $id, ctype_digit($id) ? 'orWhereRaw' : 'whereRaw');
            });
        }

        if (filled($filters['billing_name'] ?? null)) {
            $name = trim((string) $filters['billing_name']);
            $query->where(function (Builder $nameQuery) use ($name): void {
                $this->applyTextMatch($nameQuery, 'institute_expenses.bill_name', $name);
                $this->applyTextMatch($nameQuery, 'institute_expenses.payee_name', $name, 'orWhereRaw');
                $this->applyTextMatch($nameQuery, 'institute_expenses.description', $name, 'orWhereRaw');
            });
        }

        if (filled($filters['type'] ?? null)) {
            $type = trim((string) $filters['type']);
            $query->whereHas('expenseType', function (Builder $typeQuery) use ($type): void {
                $this->applyTextMatch($typeQuery, 'institute_expense_types.name', $type);
            });
        }

        if (filled($filters['date'] ?? null)) {
            $query->whereDate('expense_date', $filters['date']);
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }
        if (filled($filters['branch_id'] ?? null)) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (filled($filters['payment_status'] ?? null)) {
            $this->applyPaymentStatus($query, (string) $filters['payment_status']);
        }
    }

    /** Restrict regular users to their assigned branch while superusers retain global visibility. */
    private function applyBranchScope(Builder $query, User $user): void
    {
        if ($user->is_super_admin) {
            return;
        }

        if ($user->branch_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('institute_expenses.branch_id', $user->branch_id);
    }

    /** Hide bills outside a regular user's branch rather than revealing their existence. */
    private function assertBillBranchAccess(InstituteExpense $bill, User $user): void
    {
        if (! $user->is_super_admin
            && ($user->branch_id === null || (int) $bill->branch_id !== (int) $user->branch_id)) {
            abort(404, 'Bill not found.');
        }
    }

    /** Apply payment-state filtering using only confirmed outgoing payments. */
    private function applyPaymentStatus(Builder $query, string $status): void
    {
        $paymentTotals = DB::table('institute_expense_payments')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('institute_expense_id', 'institute_expenses.id')
            ->where('status', 'confirmed');

        if ($status === 'unpaid') {
            $query->whereRaw('('.$paymentTotals->toSql().') = 0', $paymentTotals->getBindings());
        } elseif ($status === 'paid') {
            $query->whereRaw('('.$paymentTotals->toSql().') >= (quantity * unit_amount)', $paymentTotals->getBindings());
        } else {
            $query->whereRaw('('.$paymentTotals->toSql().') > 0', $paymentTotals->getBindings())
                ->whereRaw('('.$paymentTotals->toSql().') < (quantity * unit_amount)', $paymentTotals->getBindings());
        }
    }

    /** Check branch and department ownership against the institute linked to the expense type. */
    private function validateLocations(int $instituteId, array $data): void
    {
        if (isset($data['branch_id']) && ! Branch::query()
            ->whereKey($data['branch_id'])
            ->where('institute_id', $instituteId)
            ->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch does not belong to the institute expense type.',
            ]);
        }

        if (isset($data['department_id']) && ! Department::query()
            ->whereKey($data['department_id'])
            ->where('institute_id', $instituteId)
            ->exists()) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department does not belong to the institute expense type.',
            ]);
        }
    }

    /** Resolve an active cash method and a cash account owned by the bill's institute and branch. */
    private function cashPaymentConfiguration(int $instituteId, ?int $branchId, int $accountId): array
    {
        $cashAccount = FinancialAccount::query()
            ->whereKey($accountId)
            ->where('institute_id', $instituteId)
            ->where('account_type', 'cash')
            ->where('is_active', true)
            ->when($branchId !== null, fn (Builder $query) => $query
                ->where(fn (Builder $accountQuery) => $accountQuery
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId)))
            ->first();

        if ($cashAccount === null) {
            throw ValidationException::withMessages([
                'financial_account_id' => 'Select an active cash account for this institute and branch.',
            ]);
        }

        $cashMethod = PaymentMethod::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', ['cash'])
            ->first();

        if ($cashMethod === null) {
            throw ValidationException::withMessages([
                'cash_payment' => 'Cash payment is not configured for this institute.',
            ]);
        }

        return [$cashMethod, $cashAccount];
    }

    /** Save a cash payment as confirmed with an auditable manual verification record. */
    private function createConfirmedCashPayment(
        InstituteExpense $bill,
        PaymentMethod $cashMethod,
        FinancialAccount $cashAccount,
        string|int|float $amount,
        int $recordedBy,
    ): InstituteExpensePayment {
        return InstituteExpensePayment::query()->create([
            'institute_expense_id' => $bill->id,
            'payment_number' => 'IBP-'.now()->format('Y').'-'.Str::upper((string) Str::ulid()),
            'payment_method_id' => $cashMethod->id,
            'financial_account_id' => $cashAccount->id,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'paid_at' => now(),
            'status' => 'confirmed',
            'verification_method' => 'manual',
            'verified_by' => $recordedBy,
            'verified_at' => now(),
            'recorded_by' => $recordedBy,
        ]);
    }

    /** Keep bill payment and status changes under the issuer/super-user policy. */
    private function assertBillIssuerOrSuperUser(InstituteExpense $bill, User $user): void
    {
        $this->assertBillBranchAccess($bill, $user);

        if (! $user->is_super_admin && (int) $bill->recorded_by !== (int) $user->getAuthIdentifier()) {
            abort(403, 'Only the bill issuer or a super user may perform this action.');
        }
    }

    /** Format an expense model into the Institute Billing API's stable record shape. */
    private function formatBill(InstituteExpense $bill): array
    {
        $amountCents = $this->lineTotalCents($bill->quantity, $bill->unit_amount);
        $paidCents = $this->toCents($bill->confirmed_paid_amount ?? $bill->payments
            ->where('status', 'confirmed')
            ->sum('amount'));

        return [
            'id' => $bill->id,
            // expense_number remains as a compatibility alias for existing Finance API consumers.
            'expense_number' => $bill->invoice_number,
            'billing_name' => $bill->bill_name ?: $bill->expenseCategory?->name ?: 'Institute bill',
            'type' => $bill->expenseType ? [
                'id' => $bill->expenseType->id,
                'institute_id' => $bill->expenseType->institute_id,
                'name' => $bill->expenseType->name,
                'category' => $bill->expenseCategory?->name,
            ] : null,
            'payee_name' => $bill->payee_name,
            'invoice_number' => $bill->invoice_number,
            'branch' => $bill->branch ? [
                'id' => $bill->branch->id,
                'name' => $bill->branch->name,
                'code' => $bill->branch->code,
                'phone' => $bill->branch->phone,
                'email' => $bill->branch->email,
                'address' => $bill->branch->address,
            ] : null,
            'department' => $bill->department ? [
                'id' => $bill->department->id,
                'name' => $bill->department->name,
                'code' => $bill->department->code,
            ] : null,
            'billing_date' => $bill->getRawOriginal('expense_date'),
            'due_date' => $bill->getRawOriginal('due_on'),
            'amount' => $this->formatCents($amountCents),
            'paid_amount' => $this->formatCents($paidCents),
            'balance' => $this->formatCents(max(0, $amountCents - $paidCents)),
            'payment_status' => $this->paymentStatus($amountCents, $paidCents),
            'approval_status' => $bill->getRawOriginal('status'),
            'created_by_id' => $bill->recorded_by,
            'created_by_name' => $bill->recorder?->name,
            'created_by_designation' => $bill->recorder?->designation,
            'created_by_employee_id' => $bill->recorder?->stuff?->stuff_code,
            'items' => [[
                'item_name' => $bill->bill_name ?: $bill->expenseCategory?->name ?: 'Institute bill',
                'description' => $bill->description,
                'quantity' => (string) $bill->quantity,
                'unit_name' => $bill->priceVersion?->unit_name,
                'unit_amount' => (string) $bill->unit_amount,
                'total' => $this->formatCents($amountCents),
            ]],
            'payments' => $bill->relationLoaded('payments')
                ? $bill->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => (string) $payment->amount,
                    'paid_at' => $payment->getRawOriginal('paid_at'),
                    'payment_method' => $payment->paymentMethod?->name,
                    'financial_account' => $payment->financialAccount?->name,
                    'transaction_reference' => $payment->transaction_reference,
                    'status' => $payment->getRawOriginal('status'),
                ])->all()
                : [],
            'notes' => $bill->notes,
        ];
    }

    /** Apply case-insensitive text matching in a database-portable way. */
    private function applyTextMatch(Builder $query, string $column, string $value, string $method = 'whereRaw'): void
    {
        $query->{$method}('LOWER('.$column.') LIKE ?', ['%'.mb_strtolower($value).'%']);
    }

    private function paymentStatus(int $amountCents, int $paidCents): string
    {
        if ($paidCents >= $amountCents) {
            return 'paid';
        }

        return $paidCents > 0 ? 'partial' : 'unpaid';
    }

    private function lineTotalCents(mixed $quantity, mixed $unitAmount): int
    {
        return intdiv(($this->toThousandths($quantity) * $this->toCents($unitAmount)) + 500, 1000);
    }

    private function toThousandths(mixed $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    private function toCents(mixed $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
