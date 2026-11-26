<?php

namespace App\Modules\Finance\Services\InstituteOtherBill;

use App\Models\Finance\InstituteIncomeBill;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Manage custom Institute Other Bills and their JSON item totals. */
class InstituteOtherBillService
{
    /** Resolve the primary office used on every Institute Other Bill invoice. */
    public function invoiceHeader(): array
    {
        [$institute, $branch] = $this->mainOffice();

        return [
            'name' => $institute?->name ?? config('app.name'),
            'logo_url' => $institute?->logo_path ? Storage::disk('public')->url($institute->logo_path) : '/apple-touch-icon.png',
            'branch_name' => $branch?->name,
            'phone' => $branch?->phone,
            'email' => $branch?->email,
            'address' => $branch?->address,
        ];
    }

    /** Return paginated bills filtered by bill number and bill date. */
    public function list(array $filters, User $viewer): array
    {
        $query = InstituteIncomeBill::query()->with('recorder:id,name')->when(
            ! $viewer->is_super_admin,
            fn (Builder $bills) => $bills->whereHas('recorder', fn (Builder $users) => $users->where('branch_id', $viewer->branch_id)),
        );

        if (filled($filters['search'] ?? null)) {
            $query->where('bill_number', 'like', '%'.trim($filters['search']).'%');
        }

        if (filled($filters['date'] ?? null)) {
            $query->whereDate('bill_date', $filters['date']);
        }

        /** @var LengthAwarePaginator $bills */
        $bills = $query->orderByDesc('bill_date')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return [
            'data' => collect($bills->items())->map(fn (InstituteIncomeBill $bill): array => $this->format($bill))->all(),
            'meta' => [
                'current_page' => $bills->currentPage(),
                'last_page' => $bills->lastPage(),
                'per_page' => $bills->perPage(),
                'total' => $bills->total(),
            ],
        ];
    }

    /** Return one branch-visible bill. */
    public function show(InstituteIncomeBill $bill, User $viewer): array
    {
        $this->authorizeBillScope($bill, $viewer);

        return $this->format($bill->loadMissing('recorder:id,name'));
    }

    /** Create a draft bill after calculating its line totals on the server. */
    public function create(array $data, User $recorder): array
    {
        $this->assertCanIssue($recorder);
        $items = $this->normalizeItems($data['bill_items']);

        $bill = InstituteIncomeBill::query()->create([
            ...$this->billFields($data, $items),
            'bill_number' => $this->newBillNumber(),
            'status' => $data['status'] ?? 'draft',
            'approved_by' => ($data['status'] ?? 'draft') === 'approved' ? $recorder->getAuthIdentifier() : null,
            'approved_at' => ($data['status'] ?? 'draft') === 'approved' ? now() : null,
            'rejection_reason' => ($data['status'] ?? 'draft') === 'rejected' ? ($data['rejection_reason'] ?? null) : null,
            'recorded_by' => $recorder->getAuthIdentifier(),
        ]);

        return $this->format($bill->load('recorder:id,name'));
    }

    /** Replace a draft bill's custom items and header details. */
    public function update(InstituteIncomeBill $bill, array $data, User $viewer): array
    {
        $this->authorizeBillScope($bill, $viewer);
        $this->assertCanIssue($viewer);
        $items = $this->normalizeItems($data['bill_items']);
        $status = $data['status'] ?? $bill->status;
        $bill->update([
            ...$this->billFields($data, $items),
            'status' => $status,
            'approved_by' => $status === 'approved' ? $viewer->getAuthIdentifier() : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'rejection_reason' => $status === 'rejected' ? ($data['rejection_reason'] ?? null) : null,
        ]);

        return $this->format($bill->refresh()->load('recorder:id,name'));
    }

    /** Delete a draft bill that has no received payments. */
    public function delete(InstituteIncomeBill $bill, User $viewer): void
    {
        $this->authorizeBillScope($bill, $viewer);
        $this->ensureEditable($bill);

        if ($bill->payments()->exists()) {
            throw new ConflictHttpException('This bill cannot be deleted because it has recorded payments.');
        }

        $bill->delete();
    }

    /** Calculate money with integer cents and preserve the client-provided row details. */
    private function normalizeItems(array $items): array
    {
        $totalCents = 0;
        $normalized = [];

        foreach ($items as $index => $item) {
            $unitCents = $this->toCents($item['unit_price']);
            $quantity = (int) $item['quantity'];

            if ($unitCents <= 0 || $unitCents > intdiv(999999999999 - $totalCents, $quantity)) {
                throw ValidationException::withMessages(["bill_items.{$index}.unit_price" => 'This item makes the total bill exceed the supported amount.']);
            }

            $lineCents = $unitCents * $quantity;
            $totalCents += $lineCents;

            $normalized[] = [
                'description' => trim($item['description']),
                'quantity' => $quantity,
                'unit_price' => $this->formatCents($unitCents),
                'line_total' => $this->formatCents($lineCents),
            ];
        }

        if ($totalCents <= 0) {
            throw ValidationException::withMessages(['bill_items' => 'The total bill amount is outside the supported range.']);
        }

        return [$normalized, $totalCents];
    }

    /** Map validated headers and normalized item data to persisted bill columns. */
    private function billFields(array $data, array $normalized): array
    {
        [$items, $totalCents] = $normalized;

        return [
            'university_name' => trim($data['university_name']),
            'issuer_name' => trim($data['issuer_name']),
            'bill_items' => $items,
            'total_amount' => $this->formatCents($totalCents),
            'bill_date' => $data['bill_date'],
            'due_on' => $data['due_on'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Keep old prepared bills visible but prevent editing finalized records. */
    private function ensureEditable(InstituteIncomeBill $bill): void
    {
        if (! in_array($bill->status, ['draft', 'pending_approval', 'rejected'], true)) {
            throw new ConflictHttpException('Approved bills are finalized and cannot be changed.');
        }
    }

    /** Hide bills created by users in another branch from branch employees. */
    private function authorizeBillScope(InstituteIncomeBill $bill, User $viewer): void
    {
        if (! $viewer->is_super_admin && (int) $bill->recorder()->value('branch_id') !== (int) $viewer->branch_id) {
            abort(404, 'Institute other bill not found.');
        }
    }

    /** Limit bill issuance to the main office or a super admin. */
    private function assertCanIssue(User $user): void
    {
        if ($user->is_super_admin) {
            return;
        }

        [, $mainBranch] = $this->mainOffice();
        $mainBranchId = $mainBranch?->id;
        abort_unless($mainBranchId && (int) $user->branch_id === (int) $mainBranchId, 403, 'Institute Other Bills can only be issued by the main branch or a super admin.');
    }

    /** Resolve the explicitly marked main branch consistently for invoice details and issuer access. */
    private function mainOffice(): array
    {
        $institute = Institute::query()->orderBy('id')->first();
        $branches = $institute?->branches()->where('is_active', true);
        $branch = Schema::hasColumn('branches', 'is_main_branch')
            ? $branches?->where('is_main_branch', true)->first()
            : $branches?->orderBy('id')->first();

        return [$institute, $branch];
    }

    /** Format the API representation consistently for list, detail, and mutations. */
    private function format(InstituteIncomeBill $bill): array
    {
        return [
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'university_name' => $bill->university_name,
            'issuer_name' => $bill->issuer_name ?: $bill->recorder?->name,
            'bill_items' => $bill->bill_items,
            'total_amount' => (string) $bill->total_amount,
            'bill_date' => $bill->getRawOriginal('bill_date'),
            'due_on' => $bill->getRawOriginal('due_on'),
            'status' => $bill->status,
            'rejection_reason' => $bill->rejection_reason,
            'notes' => $bill->notes,
            'recorded_by' => $bill->recorder?->name,
            'created_at' => $bill->created_at?->toISOString(),
            'updated_at' => $bill->updated_at?->toISOString(),
        ];
    }

    /** Build a unique, readable reference without a schema change. */
    private function newBillNumber(): string
    {
        do {
            $number = 'IOB-'.Str::upper((string) Str::ulid());
        } while (InstituteIncomeBill::query()->where('bill_number', $number)->exists());

        return $number;
    }

    private function toCents(mixed $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
