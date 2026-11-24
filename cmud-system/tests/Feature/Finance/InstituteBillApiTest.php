<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpenseCategory;
use App\Models\Finance\InstituteExpensePriceVersion;
use App\Models\Finance\InstituteExpenseType;
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

        $this->getJson('/api/v1/institute-bills?billing_name=electricity&type=Utilities&branch_id='.$branch->id.'&date_from=2026-03-01&date_to=2026-03-05')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matchingBill->id)
            ->assertJsonPath('data.0.billing_name', 'Electricity - March');
    }

    public function test_detail_returns_bill_items_and_balance(): void
    {
        [, $branch, $type, $user] = $this->billContext();
        $bill = $this->createBill($type, $branch, $user, 'Water invoice', '2026-03-07');

        $this->getJson('/api/v1/institute-bills/'.$bill->id)
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

        $this->getJson('/api/v1/institute-bills/options')
            ->assertOk()
            ->assertJsonPath('data.types.0.name', 'Utilities')
            ->assertJsonPath('data.payees.0', 'Utility Provider');
    }

    public function test_billing_item_search_returns_matching_items_and_prices(): void
    {
        [, , $type] = $this->billContext();
        $item = $type->categories()->first();

        $this->getJson('/api/v1/institute-bills/billing-items?search=water')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.name', 'Water')
            ->assertJsonPath('data.0.prices.0.amount', '1250.50');
    }

    public function test_billing_items_are_returned_newest_first(): void
    {
        [, , $type] = $this->billContext();
        $newest = $type->categories()->create([
            'code' => 'NEWEST',
            'name' => 'Recently added item',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/institute-bills/billing-items')
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

    public function test_authenticated_user_can_create_a_draft_bill(): void
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
            ->assertJsonPath('data.approval_status', 'draft');

        $this->assertDatabaseHas('institute_expenses', [
            'bill_name' => 'Water',
            'recorded_by' => $user->id,
            'status' => 'draft',
            'quantity' => '2.000',
        ]);
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
        ]);

        return [$institute, $branch, $type, $user, $department];
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
