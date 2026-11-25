<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpenseCategory;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\InstituteExpensePayment;
use App\Models\Finance\InstituteExpensePriceVersion;
use App\Models\Finance\InstituteExpenseType;
use App\Models\Finance\PaymentMethod;
use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use App\Modules\Finance\Services\InstituteBill\InstituteBillNumberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstituteBillApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_supports_name_type_branch_and_date_filters(): void
    {
        [, $branch, $type, $user] = $this->billContext();
        $matchingBill = $this->createBill($type, $branch, $user, 'Electricity - March', '2026-03-05');
        $this->createBill($type, $branch, $user, 'Internet - March', '2026-03-06');

        $this->actingAs($user)->getJson('/api/v1/institute-bills?billing_name=electricity&type=Utilities&branch_id='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-05')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matchingBill->id)
            ->assertJsonPath('data.0.billing_name', 'Electricity - March');
    }

    public function test_branch_users_are_limited_to_their_branch_while_superusers_can_see_all_branches(): void
    {
        [$institute, $branch, $type, $branchUser] = $this->billContext();
        $ownBill = $this->createBill($type, $branch, $branchUser, 'Own branch bill', '2026-03-05');
        $otherBranch = Branch::query()->create([
            'institute_id' => $institute->id,
            'code' => 'BR-'.fake()->unique()->numerify('####'),
            'name' => 'Other Branch',
            'phone' => '01700000003',
            'address' => 'Other branch address',
            'is_active' => true,
        ]);
        $otherBranchUser = User::query()->create([
            'name' => 'Other Branch User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'branch_id' => $otherBranch->id,
        ]);
        $otherBill = $this->createBill($type, $otherBranch, $otherBranchUser, 'Other branch bill', '2026-03-06');

        $this->actingAs($branchUser)
            ->getJson('/api/v1/institute-bills')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownBill->id);

        $this->actingAs($branchUser)
            ->getJson('/api/v1/institute-bills/'.$otherBill->id)
            ->assertNotFound();

        $this->actingAs($branchUser)
            ->patchJson('/api/v1/institute-bills/'.$otherBill->id.'/status', ['status' => 'approved'])
            ->assertNotFound();

        $this->actingAs($branchUser)
            ->postJson('/api/v1/institute-bills/'.$otherBill->id.'/payments', [
                'amount' => '100.00',
                'financial_account_id' => FinancialAccount::query()->where('branch_id', $branch->id)->value('id'),
            ])
            ->assertNotFound();

        $this->actingAs($branchUser)
            ->getJson('/api/v1/institute-bills/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.branches.0.id', $branch->id);

        $superUser = User::query()->create([
            'name' => 'All Branch Super User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $this->actingAs($superUser)
            ->getJson('/api/v1/institute-bills')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($superUser)
            ->getJson('/api/v1/institute-bills/'.$otherBill->id)
            ->assertOk()
            ->assertJsonPath('data.id', $otherBill->id);
    }

    public function test_guest_cannot_read_billing_register_or_options(): void
    {
        $this->getJson('/api/v1/institute-bills')->assertUnauthorized();
        $this->getJson('/api/v1/institute-bills/options')->assertUnauthorized();
    }

    public function test_detail_returns_bill_items_and_balance(): void
    {
        [, $branch, $type, $user] = $this->billContext();
        $bill = $this->createBill($type, $branch, $user, 'Water invoice', '2026-03-07');

        $this->actingAs($user)->getJson('/api/v1/institute-bills/'.$bill->id)
            ->assertOk()
            ->assertJsonPath('data.id', $bill->id)
            ->assertJsonPath('data.amount', '1250.50')
            ->assertJsonPath('data.balance', '1250.50')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.items.0.description', 'Water service');
    }

    public function test_options_return_register_types_and_payees(): void
    {
        [, $branch, $type, $user] = $this->billContext();
        $this->createBill($type, $branch, $user, 'Water invoice', '2026-03-07');

        $this->actingAs($user)->getJson('/api/v1/institute-bills/options')
            ->assertOk()
            ->assertJsonPath('data.types.0.name', 'Utilities')
            ->assertJsonPath('data.payees.0', 'Utility Provider');
    }

    public function test_billing_item_search_returns_matching_items_and_prices(): void
    {
        [, , $type, $user] = $this->billContext();
        $item = $type->categories()->first();

        $this->actingAs($user)->getJson('/api/v1/institute-bills/billing-items?search=water')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.name', 'Water')
            ->assertJsonPath('data.0.prices.0.amount', '1250.50');
    }

    public function test_billing_items_are_returned_newest_first(): void
    {
        [, , $type, $user] = $this->billContext();
        $newest = $type->categories()->create([
            'code' => 'NEWEST',
            'name' => 'Recently added item',
            'is_active' => true,
        ]);

        $this->actingAs($user)->getJson('/api/v1/institute-bills/billing-items')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id);
    }

    public function test_other_category_accepts_custom_bill_amount_without_a_price_version(): void
    {
        [$institute, , , $user] = $this->billContext();
        $otherType = InstituteExpenseType::query()->create([
            'institute_id' => $institute->id,
            'code' => 'OTHER',
            'name' => 'Other',
            'is_active' => true,
        ]);
        $otherCategory = InstituteExpenseCategory::query()->create([
            'institute_expense_type_id' => $otherType->id,
            'code' => 'OTHER',
            'name' => 'Other',
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_name' => 'Emergency water delivery',
            'billing_item_id' => $otherCategory->id,
            'quantity' => '1',
            'payee_name' => 'Local Supplier',
            'description' => 'Emergency drinking water',
            'amount' => '850.00',
            'billing_date' => '2026-03-01',
            'recorded_by' => $user->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.billing_name', 'Emergency water delivery')
            ->assertJsonPath('data.amount', '850.00')
            ->assertJsonPath('data.type.category', 'Other');
    }

    public function test_authenticated_user_can_create_a_pending_bill(): void
    {
        [, $branch, $type, $user] = $this->billContext();
        $untrustedRecorder = User::query()->create([
            'name' => 'Untrusted Client Value',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $type->categories()->first()->id,
            'price_version_id' => $type->categories()->first()->priceVersions()->first()->id,
            'quantity' => '2',
            'payee_name' => 'Property Owner',
            'branch_id' => $branch->id,
            'amount' => '20000.00',
            'billing_date' => '2026-03-01',
            'due_date' => '2026-03-10',
            'recorded_by' => $untrustedRecorder->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.billing_name', 'Water')
            ->assertJsonPath('data.amount', '2501.00')
            ->assertJsonPath('data.items.0.quantity', '2.000')
            ->assertJsonPath('data.items.0.unit_name', 'service')
            ->assertJsonPath('data.created_by_name', $user->name)
            ->assertJsonPath('data.items.0.description', null)
            ->assertJsonPath('data.approval_status', 'pending');

        $this->assertDatabaseHas('institute_expenses', [
            'bill_name' => 'Water',
            'recorded_by' => $user->id,
            'status' => 'pending',
            'quantity' => '2.000',
        ]);
    }

    public function test_super_user_creates_an_institute_wide_bill_even_if_a_branch_is_submitted(): void
    {
        [, $branch, $type, $user] = $this->billContext();
        $user->update(['is_super_admin' => true]);
        $category = $type->categories()->first();

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $category->id,
            'price_version_id' => $category->priceVersions()->first()->id,
            'quantity' => '1',
            'payee_name' => 'Institute Supplier',
            'branch_id' => $branch->id,
            'amount' => '1250.50',
            'billing_date' => '2026-03-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.branch', null);

        $this->assertDatabaseHas('institute_expenses', [
            'recorded_by' => $user->id,
            'branch_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_bill_creation_can_record_a_confirmed_cash_payment_atomically(): void
    {
        [, $branch, $type, $user, , $paymentMethod, $cashAccount] = $this->billContext();
        $category = $type->categories()->first();

        $response = $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $category->id,
            'price_version_id' => $category->priceVersions()->first()->id,
            'quantity' => '2',
            'payee_name' => 'Cash Supplier',
            'branch_id' => $branch->id,
            'amount' => '20000.00',
            'billing_date' => '2026-03-01',
            'status' => 'approved',
            'cash_payment' => [
                'amount' => '400.00',
                'financial_account_id' => $cashAccount->id,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.amount', '2501.00')
            ->assertJsonPath('data.paid_amount', '400.00')
            ->assertJsonPath('data.balance', '2101.00')
            ->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.payments.0.payment_method', 'Cash')
            ->assertJsonPath('data.payments.0.financial_account', $cashAccount->name)
            ->assertJsonPath('data.payments.0.status', 'confirmed');

        $payment = InstituteExpensePayment::query()->sole();
        $this->assertDatabaseHas('institute_expense_payments', [
            'institute_expense_id' => $response->json('data.id'),
            'payment_method_id' => $paymentMethod->id,
            'financial_account_id' => $cashAccount->id,
            'amount' => '400.00',
            'status' => 'confirmed',
            'verification_method' => 'manual',
            'verified_by' => $user->id,
            'recorded_by' => $user->id,
        ]);
        $this->assertNotEmpty($payment->payment_number);
    }

    public function test_cash_payment_cannot_exceed_the_bill_total(): void
    {
        [, $branch, $type, $user, , , $cashAccount] = $this->billContext();
        $category = $type->categories()->first();

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $category->id,
            'price_version_id' => $category->priceVersions()->first()->id,
            'quantity' => '1',
            'payee_name' => 'Cash Supplier',
            'branch_id' => $branch->id,
            'amount' => '20000.00',
            'billing_date' => '2026-03-01',
            'status' => 'approved',
            'cash_payment' => [
                'amount' => '1250.51',
                'financial_account_id' => $cashAccount->id,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_payment.amount']);

        $this->assertDatabaseCount('institute_expenses', 0);
        $this->assertDatabaseCount('institute_expense_payments', 0);
    }

    public function test_pending_bill_cannot_be_created_with_a_cash_payment(): void
    {
        [, $branch, $type, $user, , , $cashAccount] = $this->billContext();
        $category = $type->categories()->first();

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $category->id,
            'price_version_id' => $category->priceVersions()->first()->id,
            'quantity' => '1',
            'payee_name' => 'Cash Supplier',
            'branch_id' => $branch->id,
            'amount' => '20000.00',
            'billing_date' => '2026-03-01',
            'status' => 'pending',
            'cash_payment' => [
                'amount' => '100.00',
                'financial_account_id' => $cashAccount->id,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_payment']);

        $this->assertDatabaseCount('institute_expenses', 0);
        $this->assertDatabaseCount('institute_expense_payments', 0);
    }

    public function test_approved_bill_can_receive_cash_after_it_is_created(): void
    {
        [, $branch, $type, $user, , , $cashAccount] = $this->billContext();
        $bill = $this->createBill($type, $branch, $user, 'Cash payable bill', '2026-03-07');
        $bill->update(['status' => 'pending']);

        $this->actingAs($user)
            ->patchJson('/api/v1/institute-bills/'.$bill->id.'/status', ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved');

        $this->actingAs($user)
            ->postJson('/api/v1/institute-bills/'.$bill->id.'/payments', [
                'amount' => '400.00',
                'financial_account_id' => $cashAccount->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.paid_amount', '400.00')
            ->assertJsonPath('data.balance', '850.50')
            ->assertJsonPath('data.payment_status', 'partial');

        $this->assertDatabaseHas('institute_expense_payments', [
            'institute_expense_id' => $bill->id,
            'financial_account_id' => $cashAccount->id,
            'amount' => '400.00',
            'status' => 'confirmed',
            'recorded_by' => $user->id,
        ]);
    }

    public function test_pending_bill_cannot_receive_cash_through_the_payment_endpoint(): void
    {
        [, $branch, $type, $user, , , $cashAccount] = $this->billContext();
        $bill = $this->createBill($type, $branch, $user, 'Pending bill', '2026-03-07');
        $bill->update(['status' => 'pending']);

        $this->actingAs($user)
            ->postJson('/api/v1/institute-bills/'.$bill->id.'/payments', [
                'amount' => '100.00',
                'financial_account_id' => $cashAccount->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bill']);

        $this->assertDatabaseCount('institute_expense_payments', 0);
    }

    public function test_cash_payment_after_approval_cannot_exceed_the_remaining_balance(): void
    {
        [, $branch, $type, $user, , , $cashAccount] = $this->billContext();
        $bill = $this->createBill($type, $branch, $user, 'Cash payable bill', '2026-03-07');
        $bill->update(['status' => 'approved']);

        $this->actingAs($user)
            ->postJson('/api/v1/institute-bills/'.$bill->id.'/payments', [
                'amount' => '1250.51',
                'financial_account_id' => $cashAccount->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('institute_expense_payments', 0);
    }

    public function test_only_the_bill_issuer_or_super_user_can_record_cash_payment(): void
    {
        [, $branch, $type, $user, , , $cashAccount] = $this->billContext();
        $bill = $this->createBill($type, $branch, $user, 'Protected bill', '2026-03-07');
        $bill->update(['status' => 'approved']);
        $otherUser = User::query()->create([
            'name' => 'Unrelated Cashier',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
            'branch_id' => $bill->branch_id,
        ]);

        $this->actingAs($otherUser)
            ->postJson('/api/v1/institute-bills/'.$bill->id.'/payments', [
                'amount' => '100.00',
                'financial_account_id' => $cashAccount->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('institute_expense_payments', 0);
    }

    public function test_fractional_quantity_is_rejected_without_creating_a_bill(): void
    {
        [, , $type, $user] = $this->billContext();
        $category = $type->categories()->first();

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $category->id,
            'price_version_id' => $category->priceVersions()->first()->id,
            'quantity' => '2.5',
            'payee_name' => 'Supplier',
            'amount' => '1250.50',
            'billing_date' => '2026-03-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseCount('institute_expenses', 0);
    }

    public function test_created_bill_identifier_follows_the_replaceable_number_policy(): void
    {
        [, , $type, $user] = $this->billContext();
        $this->mock(InstituteBillNumberPolicy::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('CUSTOM-BILL-0007');

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_item_id' => $type->categories()->first()->id,
            'price_version_id' => $type->categories()->first()->priceVersions()->first()->id,
            'quantity' => '1',
            'payee_name' => 'Supplier',
            'description' => 'A bill used to verify identifier policy injection.',
            'amount' => '500.00',
            'billing_date' => '2026-03-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.expense_number', 'CUSTOM-BILL-0007');
    }

    public function test_invalid_bill_payload_returns_laravel_validation_errors(): void
    {
        [, , , $user] = $this->billContext();

        $this->actingAs($user)->postJson('/api/v1/institute-bills', [
            'billing_name' => '',
            'amount' => '-3',
            'billing_date' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_item_id', 'payee_name', 'amount', 'billing_date']);
    }

    public function test_guest_cannot_create_a_bill(): void
    {
        $this->billContext();

        $this->postJson('/api/v1/institute-bills', [
        ])->assertUnauthorized();
    }

    /** Create only the relational records needed for isolated API feature tests. */
    private function billContext(): array
    {
        $institute = Institute::query()->create([
            'code' => 'TEST-'.fake()->unique()->numerify('####'),
            'name' => 'Test Institute',
            'phone' => '01700000001',
            'email' => fake()->unique()->safeEmail(),
            'address' => 'Test address',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'institute_id' => $institute->id,
            'code' => 'BR-'.fake()->unique()->numerify('####'),
            'name' => 'Central Branch',
            'phone' => '01700000002',
            'address' => 'Branch address',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'institute_id' => $institute->id,
            'code' => 'DP-'.fake()->unique()->numerify('####'),
            'name' => 'Administration',
            'is_active' => true,
        ]);
        $type = InstituteExpenseType::query()->create([
            'institute_id' => $institute->id,
            'code' => 'UTIL-'.fake()->unique()->numerify('####'),
            'name' => 'Utilities',
            'is_active' => true,
        ]);
        $category = InstituteExpenseCategory::query()->create([
            'institute_expense_type_id' => $type->id,
            'code' => 'WATER-'.fake()->unique()->numerify('####'),
            'name' => 'Water',
            'is_active' => true,
        ]);
        InstituteExpensePriceVersion::query()->create([
            'institute_expense_category_id' => $category->id,
            'branch_id' => null,
            'version_number' => 1,
            'amount' => '1250.50',
            'unit_name' => 'service',
            'effective_from' => '2020-01-01',
            'effective_to' => null,
            'status' => 'approved',
        ]);
        $user = User::query()->create([
            'name' => 'Bill Recorder',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'institute_id' => $institute->id,
            'code' => 'CASH-'.fake()->unique()->numerify('####'),
            'name' => 'Cash',
            'is_active' => true,
        ]);
        $cashAccount = FinancialAccount::query()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'code' => 'CASH-ACCOUNT-'.fake()->unique()->numerify('####'),
            'name' => 'Main Cash Drawer',
            'account_type' => 'cash',
            'currency_code' => 'BDT',
            'opening_balance' => '0.00',
            'opening_balance_as_of' => '2026-01-01 00:00:00',
            'is_active' => true,
        ]);

        return [$institute, $branch, $type, $user, $department, $paymentMethod, $cashAccount];
    }

    /** Create a historical ledger bill to exercise register and detail responses. */
    private function createBill(InstituteExpenseType $type, Branch $branch, User $user, string $name, string $date): InstituteExpense
    {
        return InstituteExpense::query()->create([
            'expense_number' => 'IB-'.fake()->unique()->numerify('########'),
            'bill_name' => $name,
            'branch_id' => $branch->id,
            'institute_expense_type_id' => $type->id,
            'institute_expense_category_id' => $type->categories()->first()->id,
            'payee_name' => 'Utility Provider',
            'description' => 'Water service',
            'quantity' => '1.000',
            'unit_amount' => '1250.50',
            'expense_date' => $date,
            'status' => 'draft',
            'recorded_by' => $user->id,
        ]);
    }
}
