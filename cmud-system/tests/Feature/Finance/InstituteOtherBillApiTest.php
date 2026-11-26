<?php

use App\Models\Finance\InstituteIncomeBill;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Build a branch-owned operator for the Institute Other Bill API. */
function instituteOtherBillFixture(): array
{
    $institute = Institute::query()->create([
        'code' => 'IOB-'.fake()->unique()->numerify('####'),
        'name' => 'Other Bill Test Institute',
        'phone' => '01710000001',
        'email' => fake()->unique()->safeEmail(),
        'address' => 'Test address',
        'is_active' => true,
    ]);
    $branch = Branch::query()->create([
        'institute_id' => $institute->id,
        'code' => 'IOBB-'.fake()->unique()->numerify('####'),
        'name' => 'Other Bill Test Branch',
        'phone' => '01710000002',
        'email' => 'main@example.invalid',
        'address' => 'Test branch',
        'is_active' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Other Bill Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $branch->id,
    ]);

    return compact('institute', 'branch', 'user');
}

function instituteOtherBillPayload(array $overrides = []): array
{
    return array_merge([
        'university_name' => 'Example University',
        'issuer_name' => 'Other Bill Operator',
        'bill_date' => '2026-11-20',
        'due_on' => '2026-12-01',
        'bill_items' => [
            ['description' => 'Practical examination', 'quantity' => 2, 'unit_price' => '100.00'],
            ['description' => 'Certificate processing', 'quantity' => 1, 'unit_price' => '220.00'],
        ],
        'notes' => 'Test custom bill',
    ], $overrides);
}

it('creates, lists, shows, updates, and deletes a custom bill with a server-calculated JSON total', function (): void {
    $fixture = instituteOtherBillFixture();
    $create = $this->actingAs($fixture['user'])->postJson('/api/v1/institute-other-bills', instituteOtherBillPayload())
        ->assertCreated()
        ->assertJsonPath('data.university_name', 'Example University')
        ->assertJsonPath('data.issuer_name', 'Other Bill Operator')
        ->assertJsonPath('data.total_amount', '420.00')
        ->assertJsonPath('data.bill_items.0.line_total', '200.00')
        ->assertJsonPath('data.status', 'draft');
    $billId = $create->json('data.id');

    $this->actingAs($fixture['user'])->getJson('/api/v1/institute-other-bills?search='.$create->json('data.bill_number').'&date=2026-11-20')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    $this->actingAs($fixture['user'])->getJson('/api/v1/institute-other-bills/'.$billId)
        ->assertOk()->assertJsonPath('data.bill_number', $create->json('data.bill_number'));
    $this->actingAs($fixture['user'])->putJson('/api/v1/institute-other-bills/'.$billId, instituteOtherBillPayload([
        'bill_items' => [['description' => 'Updated item', 'quantity' => 3, 'unit_price' => '50.00']],
    ]))->assertOk()->assertJsonPath('data.total_amount', '150.00');
    $this->actingAs($fixture['user'])->deleteJson('/api/v1/institute-other-bills/'.$billId)->assertNoContent();

    expect(InstituteIncomeBill::query()->whereKey($billId)->exists())->toBeFalse();
});

it('validates item details and due dates before creating a custom bill', function (): void {
    $fixture = instituteOtherBillFixture();

    $this->actingAs($fixture['user'])->postJson('/api/v1/institute-other-bills', instituteOtherBillPayload([
        'due_on' => '2026-11-19',
        'bill_items' => [['description' => '', 'quantity' => 0, 'unit_price' => '0.00']],
    ]))->assertUnprocessable()->assertJsonValidationErrors([
        'due_on',
        'bill_items.0.description',
        'bill_items.0.quantity',
        'bill_items.0.unit_price',
    ]);
});

it('updates the bill approval state and records the approving user', function (): void {
    $fixture = instituteOtherBillFixture();
    $bill = $this->actingAs($fixture['user'])
        ->postJson('/api/v1/institute-other-bills', instituteOtherBillPayload(['status' => 'pending_approval']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval')
        ->json('data');

    $this->actingAs($fixture['user'])
        ->putJson('/api/v1/institute-other-bills/'.$bill['id'], instituteOtherBillPayload([
            'status' => 'approved',
        ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->assertDatabaseHas('institute_income_bill', [
        'id' => $bill['id'],
        'status' => 'approved',
        'approved_by' => $fixture['user']->id,
    ]);
});

it('limits branch operators to bills recorded by their own branch', function (): void {
    $fixture = instituteOtherBillFixture();
    $otherBranch = Branch::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'IOB-OTHER-'.fake()->unique()->numerify('####'),
        'name' => 'Other Other Bill Branch',
        'phone' => '01710000003',
        'address' => 'Other branch',
        'is_active' => true,
    ]);
    $otherRecorder = User::query()->create([
        'name' => 'Other Branch Recorder',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $otherBranch->id,
    ]);
    $bill = InstituteIncomeBill::query()->create([
        'university_name' => 'Other Branch University',
        'bill_number' => 'IOB-OTHER-BRANCH-1',
        'bill_items' => [['description' => 'Fee', 'quantity' => 1, 'unit_price' => '100.00', 'line_total' => '100.00']],
        'total_amount' => '100.00',
        'bill_date' => '2026-11-20',
        'status' => 'draft',
        'recorded_by' => $otherRecorder->id,
    ]);

    $this->actingAs($fixture['user'])->getJson('/api/v1/institute-other-bills/'.$bill->id)->assertNotFound();
    $this->actingAs($fixture['user'])->getJson('/api/v1/institute-other-bills')->assertOk()->assertJsonCount(0, 'data');
});

it('prints the main branch contacts and permits bill issuance only from that branch or super admin', function (): void {
    $fixture = instituteOtherBillFixture();
    $this->actingAs($fixture['user'])->getJson('/api/v1/institute-other-bills/options')
        ->assertOk()
        ->assertJsonPath('data.invoice_header.branch_name', 'Other Bill Test Branch')
        ->assertJsonPath('data.invoice_header.phone', '01710000002')
        ->assertJsonPath('data.invoice_header.email', 'main@example.invalid')
        ->assertJsonPath('data.invoice_header.address', 'Test branch');

    $otherBranch = Branch::query()->create([
        'institute_id' => $fixture['institute']->id,
        'code' => 'IOB-NONMAIN-'.fake()->unique()->numerify('###'),
        'name' => 'Non-main Branch',
        'is_active' => true,
    ]);
    $otherUser = User::query()->create([
        'name' => 'Non-main Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        'branch_id' => $otherBranch->id,
    ]);

    $this->actingAs($otherUser)->postJson('/api/v1/institute-other-bills', instituteOtherBillPayload())
        ->assertForbidden();
});
