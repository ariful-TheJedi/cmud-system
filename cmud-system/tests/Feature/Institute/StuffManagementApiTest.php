<?php

use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use App\Models\Institute\Institute;
use App\Models\Institute\Stuff;
use App\Models\Institute\StuffAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->institute = Institute::query()->create([
        'code' => 'STUFF-TEST',
        'name' => 'Stuff Test Institute',
        'phone' => '01700000001',
        'email' => 'stuff-institute@example.test',
        'address' => 'Test address',
    ]);

    $this->branch = Branch::query()->create([
        'institute_id' => $this->institute->id,
        'code' => 'MAIN',
        'name' => 'Main branch',
        'phone' => '01700000002',
        'address' => 'Main branch address',
    ]);

    $this->department = Department::query()->create([
        'institute_id' => $this->institute->id,
        'code' => 'ADMIN',
        'name' => 'Administration',
    ]);

    $this->stuff = Stuff::query()->create([
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-001',
        'full_name' => 'Amina Rahman',
        'phone' => '01700000003',
        'email' => 'amina@example.test',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'active',
    ]);
});

it('returns stuff records with pagination and distinct branch totals', function (): void {
    // Two department assignments in one branch should still count as one stuff member.
    StuffAssignment::query()->create([
        'stuff_id' => $this->stuff->id,
        'branch_id' => $this->branch->id,
        'department_id' => $this->department->id,
    ]);
    StuffAssignment::query()->create([
        'stuff_id' => $this->stuff->id,
        'branch_id' => $this->branch->id,
        'department_id' => Department::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'FIN',
            'name' => 'Finance',
        ])->id,
    ]);

    $this->getJson('/api/v1/stuff?search=Amina&per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.stuff_code', 'STF-001')
        ->assertJsonPath('data.0.stuff_assignments.0.branch.name', 'Main branch')
        ->assertJsonPath('data.0.teachers', [])
        ->assertJsonPath('data.0.institute_expenses_count', 0)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.total_stuff', 1)
        ->assertJsonPath('meta.branch_totals.0.stuff_count', 1);
});

it('creates a stuff profile with branch assignments and optional media', function (): void {
    Storage::fake('public');

    $response = $this->withHeader('Accept', 'application/json')->post('/api/v1/stuff', [
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-NEW',
        'full_name' => 'Nusrat Jahan',
        'phone' => '01700000011',
        'email' => 'nusrat@example.test',
        'nid_number' => '1234567890123',
        'designation' => 'Accounts Officer',
        'joining_date' => '2026-03-01',
        'employment_status' => 'active',
        'assignments' => [[
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
        ]],
        'nid_file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
        'photo' => UploadedFile::fake()->image('profile.png'),
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Stuff member created.')
        ->assertJsonPath('data.stuff_code', 'STF-NEW')
        ->assertJsonPath('data.full_name', 'Nusrat Jahan')
        ->assertJsonPath('data.designation', 'Accounts Officer')
        ->assertJsonPath('data.nid_number', '1234567890123')
        ->assertJsonPath('data.has_nid_file', true)
        ->assertJsonPath('data.stuff_assignments.0.branch.name', 'Main branch')
        ->assertJsonMissingPath('data.nid_file_path')
        ->assertJsonMissingPath('data.photo_path');

    $this->assertDatabaseHas('stuff', [
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-NEW',
        'nid_number' => '1234567890123',
    ]);
    $this->assertDatabaseHas('stuff_assignments', [
        'stuff_id' => $response->json('data.id'),
        'branch_id' => $this->branch->id,
        'department_id' => $this->department->id,
    ]);
    expect(Storage::disk('public')->allFiles('stuff/nid-documents'))->toHaveCount(1)
        ->and(Storage::disk('public')->allFiles('stuff/photos'))->toHaveCount(1);
});

it('returns institute branch and department choices for the employee form', function (): void {
    $this->getJson('/api/v1/stuff/options')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->institute->id)
        ->assertJsonPath('data.0.branches.0.id', $this->branch->id)
        ->assertJsonPath('data.0.departments.0.id', $this->department->id);
});

it('validates required create fields and institute scoped stuff code uniqueness', function (): void {
    $this->postJson('/api/v1/stuff', [])->assertUnprocessable()->assertJsonValidationErrors([
        'institute_id', 'stuff_code', 'full_name', 'phone', 'designation', 'joining_date', 'employment_status',
    ]);

    $this->postJson('/api/v1/stuff', [
        'institute_id' => $this->institute->id,
        'stuff_code' => $this->stuff->stuff_code,
        'full_name' => 'Duplicate Code',
        'phone' => '01700000012',
        'designation' => 'Assistant',
        'joining_date' => '2026-03-01',
        'employment_status' => 'active',
    ])->assertUnprocessable()->assertJsonValidationErrors('stuff_code');
});

it('rejects invalid stuff create input and assignments outside the institute', function (): void {
    $otherInstitute = Institute::query()->create([
        'code' => 'OTHER-TEST',
        'name' => 'Other Institute',
        'phone' => '01700000020',
        'email' => 'other@example.test',
        'address' => 'Other address',
    ]);
    $otherBranch = Branch::query()->create([
        'institute_id' => $otherInstitute->id,
        'code' => 'OTHER',
        'name' => 'Other branch',
        'phone' => '01700000021',
        'address' => 'Other branch address',
    ]);

    $this->postJson('/api/v1/stuff', [
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-BAD',
        'full_name' => 'Bad Input',
        'phone' => '01700000022',
        'email' => 'invalid',
        'designation' => 'Assistant',
        'joining_date' => '2026-03-01',
        'employment_status' => 'unknown',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'employment_status']);

    $this->postJson('/api/v1/stuff', [
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-BAD',
        'full_name' => 'Bad Assignment',
        'phone' => '01700000022',
        'designation' => 'Assistant',
        'joining_date' => '2026-03-01',
        'employment_status' => 'active',
        'assignments' => [[
            'branch_id' => $otherBranch->id,
            'department_id' => $this->department->id,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('assignments.0.branch_id');

    $this->assertDatabaseMissing('stuff', ['stuff_code' => 'STF-BAD']);
});

it('returns an individual stuff profile and a not found response for an unknown record', function (): void {
    $this->getJson('/api/v1/stuff/'.$this->stuff->id)
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Amina Rahman')
        ->assertJsonPath('data.institute.name', 'Stuff Test Institute')
        ->assertJsonPath('data.stuff_assignments', [])
        ->assertJsonPath('data.teachers', [])
        ->assertJsonPath('data.institute_expenses_count', 0);

    $this->getJson('/api/v1/stuff/999999')->assertNotFound();
    $this->putJson('/api/v1/stuff/999999', [])->assertNotFound();
});

it('filters stuff by database ID, code, name, branch, and employment status', function (): void {
    StuffAssignment::query()->create([
        'stuff_id' => $this->stuff->id,
        'branch_id' => $this->branch->id,
        'department_id' => $this->department->id,
    ]);

    $secondBranch = Branch::query()->create([
        'institute_id' => $this->institute->id,
        'code' => 'UTTARA',
        'name' => 'Uttara Campus',
        'phone' => '01700000005',
        'address' => 'Uttara branch address',
    ]);
    $secondDepartment = Department::query()->create([
        'institute_id' => $this->institute->id,
        'code' => 'OPS',
        'name' => 'Operations',
    ]);
    $otherStuff = Stuff::query()->create([
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-002',
        'full_name' => 'Rafiq Hasan',
        'phone' => '01700000006',
        'designation' => 'Coordinator',
        'joining_date' => '2026-02-10',
        'employment_status' => 'inactive',
    ]);
    StuffAssignment::query()->create([
        'stuff_id' => $otherStuff->id,
        'branch_id' => $secondBranch->id,
        'department_id' => $secondDepartment->id,
    ]);

    $this->getJson('/api/v1/stuff?id='.$this->stuff->id.'&stuff_code=STF-001&name=amina&branch=main&status=active')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $this->stuff->id);

    $this->getJson('/api/v1/stuff?branch_id='.$secondBranch->id.'&employment_status=inactive')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $otherStuff->id);
});

it('updates stuff profile fields and validates duplicate stuff codes', function (): void {
    $this->putJson('/api/v1/stuff/'.$this->stuff->id, [
        'stuff_code' => 'STF-001',
        'full_name' => 'Amina Akter',
        'phone' => '01700000003',
        'nid_number' => '19876543210987654',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'on_leave',
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.full_name', 'Amina Akter')
        ->assertJsonPath('data.nid_number', '19876543210987654')
        ->assertJsonPath('data.employment_status', 'on_leave');

    $anotherStuff = Stuff::query()->create([
        'institute_id' => $this->institute->id,
        'stuff_code' => 'STF-002',
        'full_name' => 'Rafiq Hasan',
        'phone' => '01700000004',
        'designation' => 'Finance officer',
        'joining_date' => '2026-02-10',
        'employment_status' => 'active',
    ]);

    $this->putJson('/api/v1/stuff/'.$this->stuff->id, [
        'stuff_code' => $anotherStuff->stuff_code,
        'full_name' => 'Amina Akter',
        'phone' => '01700000003',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'on_leave',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stuff_code');
});

it('rejects invalid stuff update values', function (): void {
    $this->putJson('/api/v1/stuff/'.$this->stuff->id, [
        'email' => 'not-an-email',
        'joining_date' => 'not-a-date',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'joining_date']);
});

it('requires all core fields for a PUT stuff update', function (): void {
    $this->putJson('/api/v1/stuff/'.$this->stuff->id, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'stuff_code',
            'full_name',
            'phone',
            'designation',
            'joining_date',
            'employment_status',
        ]);
});

it('stores the NID file privately and returns only a presence indicator', function (): void {
    Storage::fake('public');

    $response = $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            '_method' => 'PUT',
            'stuff_code' => 'STF-001',
            'full_name' => 'Amina Rahman',
            'phone' => '01700000003',
            'designation' => 'Administrator',
            'joining_date' => '2026-01-05',
            'employment_status' => 'active',
            'nid_file' => UploadedFile::fake()->create('nid-copy.pdf', 100, 'application/pdf'),
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.has_nid_file', true)
        ->assertJsonMissingPath('data.nid_file_path');
    expect($response->json('data.nid_file_download_url'))
        ->toBe(route('stuff.nid.download', $this->stuff->id));

    $this->get('/api/v1/stuff/'.$this->stuff->id.'/nid/download')
        ->assertOk()
        ->assertDownload('nid-document.pdf');

    $storedFiles = Storage::disk('public')->allFiles('stuff/nid-documents');
    expect($storedFiles)->toHaveCount(1);
    Storage::disk('public')->assertExists($storedFiles[0]);
});

it('rejects unsupported NID files and conflicting remove requests', function (): void {
    $requiredFields = [
        'stuff_code' => 'STF-001',
        'full_name' => 'Amina Rahman',
        'phone' => '01700000003',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'active',
    ];

    $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'nid_file' => UploadedFile::fake()->create('nid-copy.exe', 100, 'application/octet-stream'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nid_file');

    $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'remove_nid_file' => '1',
            'nid_file' => UploadedFile::fake()->create('nid-copy.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nid_file');
});

it('replaces and removes a previously stored NID file', function (): void {
    Storage::fake('public');
    $oldPath = UploadedFile::fake()->create('old-nid.pdf', 100, 'application/pdf')
        ->store('stuff/nid-documents', 'public');
    $this->stuff->update(['nid_file_path' => $oldPath]);

    $requiredFields = [
        'stuff_code' => 'STF-001',
        'full_name' => 'Amina Rahman',
        'phone' => '01700000003',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'active',
    ];

    $replacementResponse = $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'nid_file' => UploadedFile::fake()->create('replacement-nid.pdf', 100, 'application/pdf'),
        ]);
    $replacementResponse->assertOk()->assertJsonPath('data.has_nid_file', true);

    Storage::disk('public')->assertMissing($oldPath);
    $replacementFiles = Storage::disk('public')->allFiles('stuff/nid-documents');
    expect($replacementFiles)->toHaveCount(1);

    $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'remove_nid_file' => '1',
        ])
        ->assertOk()
        ->assertJsonPath('data.has_nid_file', false);

    expect(Storage::disk('public')->allFiles('stuff/nid-documents'))->toBeEmpty();
});

it('uploads a profile photo and removes an existing one', function (): void {
    Storage::fake('public');
    $requiredFields = [
        'stuff_code' => 'STF-001',
        'full_name' => 'Amina Rahman',
        'phone' => '01700000003',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'active',
    ];

    $uploadResponse = $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->image('profile.png'),
        ]);

    $uploadResponse
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path');
    expect($uploadResponse->json('data.photo_url'))
        ->toStartWith(config('filesystems.disks.public.url').'/stuff/photos/');

    $photoPaths = Storage::disk('public')->allFiles('stuff/photos');
    expect($photoPaths)->toHaveCount(1);
    Storage::disk('public')->assertExists($photoPaths[0]);

    $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'remove_photo' => '1',
        ])
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);

    expect(Storage::disk('public')->allFiles('stuff/photos'))->toBeEmpty();
});

it('rejects unsupported profile photos and conflicting removal requests', function (): void {
    $requiredFields = [
        'stuff_code' => 'STF-001',
        'full_name' => 'Amina Rahman',
        'phone' => '01700000003',
        'designation' => 'Administrator',
        'joining_date' => '2026-01-05',
        'employment_status' => 'active',
    ];

    $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->create('profile.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');

    $this->withHeader('Accept', 'application/json')
        ->post('/api/v1/stuff/'.$this->stuff->id, [
            ...$requiredFields,
            '_method' => 'PUT',
            'remove_photo' => '1',
            'photo' => UploadedFile::fake()->image('profile.png'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo');
});
