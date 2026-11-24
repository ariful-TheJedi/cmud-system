<?php

namespace App\Modules\Finance\Services\InstituteBill;

use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpenseCategory;
use App\Models\Finance\InstituteExpensePriceVersion;
use App\Models\Finance\InstituteExpenseType;
use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
    public function list(array $filters = []): array
    {
        $query = $this->billQuery();
        $this->applyFilters($query, $filters);

        $bills = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return ['bills' => $bills];
    }

    /** Return one bill with item, approval, and payment detail. */
    public function show(InstituteExpense $bill): array
    {
        return $this->formatBill(
            $bill->loadMissing([
                'expenseType:id,institute_id,name',
                'expenseCategory:id,institute_expense_type_id,name,code',
                'branch:id,institute_id,name,code',
                'department:id,institute_id,name,code',
                'priceVersion:id,unit_name',
                'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
                'recorder:id,name,designation',
                'recorder.stuff:id,user_id,stuff_code',
                'payments.paymentMethod:id,name',
            ]),
        );
    }

    /** Return selectable billing items and locations for the bill creation form. */
    public function formOptions(): array
    {
        return [
            // Type names remain available to filter the bill register; generation uses billing_items only.
            'types' => InstituteExpenseType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'institute_id', 'name', 'code'])
                ->toArray(),
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'institute_id', 'name', 'code'])
                ->toArray(),
            'payees' => InstituteExpense::query()
                ->whereNotNull('payee_name')
                ->where('payee_name', '<>', '')
                ->whereHas('expenseType', fn (Builder $query) => $query->where('is_active', true))
                ->select('payee_name')
                ->distinct()
                ->orderBy('payee_name')
                ->pluck('payee_name')
                ->all(),
        ];
    }

    /** Search active billable items and include their approved configured prices. */
    public function searchBillingItems(string $search): array
    {
        return InstituteExpenseCategory::query()
            ->with([
                'expenseType:id,institute_id,name,is_active',
                'priceVersions' => fn ($query) => $query
                    ->select(['id', 'institute_expense_category_id', 'branch_id', 'amount', 'unit_name', 'effective_from', 'effective_to', 'status'])
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id'),
            ])
            ->where('is_active', true)
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

    /** Create a draft bill and return it in the same shape as register and detail records. */
    public function create(array $data, ?int $recordedBy, InstituteBillNumberPolicy $numberPolicy): array
    {
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

        $bill = DB::transaction(function () use ($data, $recordedBy, $type, $category, $priceVersion, $isOtherCategory, $billName, $numberPolicy): InstituteExpense {
            // The database assigns the numeric primary key; this module assigns the human-readable invoice number.
            $invoiceNumber = $numberPolicy->generate();
            $status = $data['status'] ?? 'draft';
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

            return $expense->load($this->detailRelations());
        });

        return $this->formatBill($bill);
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
            'branch:id,institute_id,name,code',
            'department:id,institute_id,name,code',
            'priceVersion:id,unit_name',
            'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
            'payments.paymentMethod:id,name',
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
                'name' => $bill->expenseType->name,
                'category' => $bill->expenseCategory?->name,
            ] : null,
            'payee_name' => $bill->payee_name,
            'invoice_number' => $bill->invoice_number,
            'branch' => $bill->branch ? [
                'id' => $bill->branch->id,
                'name' => $bill->branch->name,
                'code' => $bill->branch->code,
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
