<?php

use App\Models\Finance\InstituteExpense;
use App\Models\Finance\InstituteExpenseType;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Build the minimum linked records required by the Institute Expense ledger. */
function instituteBillStatusContext(): array
{
    $institute = Institute::query()->create([
        'code' => 'STATUS-'.fake()->unique()->numerify('####'),
        'name' => 'Status Test Institute',
        'phone' => '01700000001',
        'email' => fake()->unique()->safeEmail(),
        'address' => 'Status test address',
        'is_active' => true,
    ]);
    $branch = Branch::query()->create([
        'institute_id' => $institute->id,
        'code' => 'BR-'.fake()->unique()->numerify('####'),
        'name' => 'Status Test Branch',
        'phone' => '01700000002',
        'address' => 'Branch address',
        'is_active' => true,
    ]);
    $type = InstituteExpenseType::query()->create([
        'institute_id' => $institute->id,
        'code' => 'TYPE-'.fake()->unique()->numerify('####'),
        'name' => 'Status Test Expenses',
        'is_active' => true,
    ]);
    $category = $type->categories()->create([
        'code' => 'ITEM-'.fake()->unique()->numerify('####'),
        'name' => 'Status Test Item',
        'is_active' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Status Test User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $branch->id,
    ]);
    $bill = InstituteExpense::query()->create([
        'expense_number' => 'IB-'.fake()->unique()->numerify('########'),
        'bill_name' => 'Status Test Bill',
        'branch_id' => $branch->id,
        'institute_expense_type_id' => $type->id,
        'institute_expense_category_id' => $category->id,
        'payee_name' => 'Test Supplier',
        'description' => 'Status transition test bill',
        'quantity' => '1.000',
        'unit_amount' => '100.00',
        'expense_date' => '2026-03-01',
        'status' => 'draft',
        'recorded_by' => $user->id,
    ]);

    return [$bill, $user];
}

it('approves a bill and records who approved it', function (): void {
    [$bill, $user] = instituteBillStatusContext();

    $this->actingAs($user)
        ->patchJson('/api/v1/institute-bills/'.$bill->id.'/status', ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'approved');

    $this->assertDatabaseHas('institute_expenses', [
        'id' => $bill->id,
        'status' => 'approved',
        'approved_by' => $user->id,
    ]);
    expect($bill->fresh()->approved_at)->not->toBeNull();
});

it('cancels a bill and stores the cancellation reason', function (): void {
    [$bill, $user] = instituteBillStatusContext();

    $this->actingAs($user)
        ->patchJson('/api/v1/institute-bills/'.$bill->id.'/status', [
            'status' => 'cancelled',
            'cancellation_reason' => 'Duplicate supplier invoice',
        ])
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'cancelled');

    $this->assertDatabaseHas('institute_expenses', [
        'id' => $bill->id,
        'status' => 'cancelled',
        'cancellation_reason' => 'Duplicate supplier invoice',
    ]);
    expect($bill->fresh()->cancelled_at)->not->toBeNull();
});

it('rejects unsupported bill status values without changing the bill', function (): void {
    [$bill, $user] = instituteBillStatusContext();

    $this->actingAs($user)
        ->patchJson('/api/v1/institute-bills/'.$bill->id.'/status', ['status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseHas('institute_expenses', ['id' => $bill->id, 'status' => 'draft']);
});

it('allows a branch user to change the status of a bill in their branch', function (): void {
    [$bill] = instituteBillStatusContext();
    $otherUser = User::query()->create([
        'name' => 'Unrelated User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'is_super_admin' => false,
        'branch_id' => $bill->branch_id,
    ]);

    $this->actingAs($otherUser)
        ->patchJson('/api/v1/institute-bills/'.$bill->id.'/status', ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'approved');

    $this->assertDatabaseHas('institute_expenses', [
        'id' => $bill->id,
        'status' => 'approved',
        'approved_by' => $otherUser->id,
    ]);
});

it('allows a super user to change another users bill status', function (): void {
    [$bill] = instituteBillStatusContext();
    $superUser = User::query()->create([
        'name' => 'Finance Super User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'is_super_admin' => true,
    ]);

    $this->actingAs($superUser)
        ->patchJson('/api/v1/institute-bills/'.$bill->id.'/status', ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.approval_status', 'approved');

    $this->assertDatabaseHas('institute_expenses', [
        'id' => $bill->id,
        'status' => 'approved',
        'approved_by' => $superUser->id,
    ]);
});
