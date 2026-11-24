<?php

use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use App\Models\Institute\Institute;
use App\Models\Institute\Stuff;
use App\Models\Institute\StuffAssignment;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Build an employee with one active branch assignment for account provisioning tests. */
function makeEmployeeForUserManagement(array $overrides = []): Stuff
{
    $institute = Institute::query()->create([
        'code' => fake()->unique()->bothify('USER-####'),
        'name' => 'User Test Institute',
        'phone' => '01700000001',
        'email' => fake()->unique()->safeEmail(),
        'address' => 'Test address',
    ]);
    $branch = Branch::query()->create([
        'institute_id' => $institute->id,
        'code' => fake()->unique()->bothify('BR-####'),
        'name' => 'Main branch',
        'phone' => '01700000004',
        'address' => 'Main branch address',
        'is_active' => true,
    ]);
    $department = Department::query()->create([
        'institute_id' => $institute->id,
        'code' => fake()->unique()->bothify('DP-####'),
        'name' => 'General department',
        'is_active' => true,
    ]);

    $stuff = Stuff::query()->create(array_merge([
        'institute_id' => $institute->id,
        'stuff_code' => 'EMP-'.fake()->unique()->numerify('####'),
        'full_name' => 'Amina Rahman',
        'phone' => '01700000002',
        'email' => fake()->unique()->safeEmail(),
        'designation' => 'Teacher',
        'joining_date' => '2026-01-05',
        'employment_status' => 'active',
    ], $overrides));

    StuffAssignment::query()->create([
        'stuff_id' => $stuff->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);

    return $stuff;
}

it('lists user accounts without exposing passwords and includes branch scope', function (): void {
    $employee = makeEmployeeForUserManagement();
    $branchId = $employee->stuffAssignments()->value('branch_id');
    User::query()->create([
        'name' => 'Existing User',
        'email' => 'existing@example.test',
        'branch_id' => $branchId,
        'password' => 'secure-password',
    ]);

    $this->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Existing User')
        ->assertJsonPath('data.0.branch_id', $branchId)
        ->assertJsonPath('data.0.branch.name', 'Main branch')
        ->assertJsonMissingPath('data.0.password');
});

it('lists eligible employees with their unique branch assignment', function (): void {
    $eligible = makeEmployeeForUserManagement();
    $branchId = $eligible->stuffAssignments()->value('branch_id');
    makeEmployeeForUserManagement(['email' => null]);
    makeEmployeeForUserManagement([
        'email' => 'linked@example.test',
        'user_id' => User::query()->create([
            'name' => 'Linked User',
            'email' => 'linked@example.test',
            'password' => 'secure-password',
        ])->id,
    ]);

    $this->getJson('/api/v1/users/eligible-employees')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $eligible->id)
        ->assertJsonPath('data.0.branch_id', $branchId)
        ->assertJsonPath('data.0.branch_assignment_count', 1);
});

it('returns active branch choices for standalone account creation', function (): void {
    $employee = makeEmployeeForUserManagement();
    $branchId = $employee->stuffAssignments()->value('branch_id');

    $this->getJson('/api/v1/users/branches')
        ->assertOk()
        ->assertJsonPath('data.0.id', $branchId)
        ->assertJsonPath('data.0.name', 'Main branch');
});

it('creates an employee account and inherits the employee branch', function (): void {
    $employee = makeEmployeeForUserManagement();
    $branchId = $employee->stuffAssignments()->value('branch_id');

    $response = $this->postJson('/api/v1/users/from-employee', [
        'stuff_id' => $employee->id,
        'password' => 'initial-password',
        'password_confirmation' => 'initial-password',
        'is_active' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Amina Rahman')
        ->assertJsonPath('data.email', $employee->email)
        ->assertJsonPath('data.branch_id', $branchId)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonMissingPath('data.password');

    $userId = $response->json('data.id');
    expect(Hash::check('initial-password', User::query()->findOrFail($userId)->password))->toBeTrue();
    $this->assertDatabaseHas('stuff', ['id' => $employee->id, 'user_id' => $userId]);
    $this->assertDatabaseHas('users', ['id' => $userId, 'branch_id' => $branchId]);
});

it('requires valid credentials and prevents duplicate employee accounts', function (): void {
    $employee = makeEmployeeForUserManagement();

    $this->postJson('/api/v1/users/from-employee', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['stuff_id', 'password', 'is_active']);

    $this->postJson('/api/v1/users/from-employee', [
        'stuff_id' => $employee->id,
        'password' => 'short',
        'password_confirmation' => 'different',
        'is_active' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    User::query()->create([
        'name' => 'Existing User',
        'email' => $employee->email,
        'password' => 'secure-password',
    ]);
    $this->postJson('/api/v1/users/from-employee', [
        'stuff_id' => $employee->id,
        'password' => 'initial-password',
        'password_confirmation' => 'initial-password',
        'is_active' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['stuff_id']);
});

it('requires an employee to have exactly one active branch before account creation', function (): void {
    $employee = makeEmployeeForUserManagement();
    $secondBranch = Branch::query()->create([
        'institute_id' => $employee->institute_id,
        'code' => fake()->unique()->bothify('BR-####'),
        'name' => 'Second branch',
        'phone' => '01700000005',
        'address' => 'Second branch address',
        'is_active' => true,
    ]);
    $secondDepartment = Department::query()->create([
        'institute_id' => $employee->institute_id,
        'code' => fake()->unique()->bothify('DP-####'),
        'name' => 'Second department',
        'is_active' => true,
    ]);
    StuffAssignment::query()->create([
        'stuff_id' => $employee->id,
        'branch_id' => $secondBranch->id,
        'department_id' => $secondDepartment->id,
    ]);

    $this->postJson('/api/v1/users/from-employee', [
        'stuff_id' => $employee->id,
        'password' => 'initial-password',
        'password_confirmation' => 'initial-password',
        'is_active' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['stuff_id']);
});

it('creates a standalone regular user assigned to one active branch', function (): void {
    $employee = makeEmployeeForUserManagement();
    $branchId = $employee->stuffAssignments()->value('branch_id');

    $this->postJson('/api/v1/users', [
        'name' => 'Standalone User',
        'email' => 'standalone@example.test',
        'branch_id' => $branchId,
        'password' => 'initial-password',
        'password_confirmation' => 'initial-password',
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Standalone User')
        ->assertJsonPath('data.branch_id', $branchId)
        ->assertJsonPath('data.is_super_admin', false);

    $this->assertDatabaseHas('users', [
        'email' => 'standalone@example.test',
        'branch_id' => $branchId,
        'is_super_admin' => false,
    ]);
});

it('requires an active branch for standalone user accounts', function (): void {
    $this->postJson('/api/v1/users', [
        'name' => 'No Branch User',
        'email' => 'no-branch@example.test',
        'password' => 'initial-password',
        'password_confirmation' => 'initial-password',
        'is_active' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['branch_id']);
});

it('updates a regular user profile, branch, status, and optional password', function (): void {
    $employee = makeEmployeeForUserManagement();
    $currentBranchId = $employee->stuffAssignments()->value('branch_id');
    $newBranch = Branch::query()->create([
        'institute_id' => $employee->institute_id,
        'code' => fake()->unique()->bothify('BR-####'),
        'name' => 'Updated branch',
        'phone' => '01700000006',
        'address' => 'Updated branch address',
        'is_active' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Old Name',
        'email' => 'old@example.test',
        'branch_id' => $currentBranchId,
        'password' => 'old-password',
    ]);

    $this->putJson('/api/v1/users/'.$user->id, [
        'name' => 'Updated Name',
        'email' => 'updated@example.test',
        'phone' => '01711112222',
        'designation' => 'Manager',
        'branch_id' => $newBranch->id,
        'is_active' => false,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.branch_id', $newBranch->id)
        ->assertJsonPath('data.branch.name', 'Updated branch')
        ->assertJsonPath('data.is_active', false);

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'updated@example.test',
        'branch_id' => $newBranch->id,
        'is_active' => false,
    ]);
});

it('preserves super-user all-branch access when profile information is updated', function (): void {
    $user = User::query()->create([
        'name' => 'Super User',
        'email' => 'super@example.test',
        'is_super_admin' => true,
        'branch_id' => null,
        'password' => 'secure-password',
    ]);

    $this->putJson('/api/v1/users/'.$user->id, [
        'name' => 'Updated Super User',
        'email' => 'super@example.test',
        'branch_id' => null,
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Super User')
        ->assertJsonPath('data.is_super_admin', true)
        ->assertJsonPath('data.branch_id', null);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_super_admin' => true,
        'branch_id' => null,
    ]);
});
