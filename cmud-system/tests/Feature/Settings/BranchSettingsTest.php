<?php

namespace Tests\Feature\Settings;

use App\Models\Institute\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_institute_settings_page_is_rendered(): void
    {
        $this->get('/settings/institute')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/institute'));
    }

    public function test_branch_can_be_created_and_updated_without_changing_institute_profile(): void
    {
        $institute = Institute::query()->create([
            'code' => 'BRANCH-SETTINGS',
            'name' => 'Branch Settings Institute',
            'phone' => '01700000031',
            'email' => 'branch-settings@example.test',
            'address' => 'Institute address',
            'is_active' => true,
        ]);

        $createResponse = $this->putJson("/api/v1/settings/institutes/{$institute->id}", [
            'branches' => [[
                'code' => 'NORTH-01',
                'name' => 'North Branch',
                'phone' => '01700000032',
                'email' => 'north@example.test',
                'address' => 'North address',
                'is_active' => true,
                'is_main_branch' => true,
            ]],
        ]);

        $createResponse
            ->assertOk()
            ->assertJsonPath('data.branches.0.name', 'North Branch');

        $branchId = $createResponse->json('data.branches.0.id');

        $this->putJson("/api/v1/settings/institutes/{$institute->id}", [
            'branches' => [[
                'id' => $branchId,
                'code' => 'NORTH-01',
                'name' => 'North Campus',
                'phone' => '01700000033',
                'email' => 'campus@example.test',
                'address' => 'Updated north address',
                'is_active' => true,
                'is_main_branch' => true,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.branches.0.name', 'North Campus')
            ->assertJsonPath('data.branches.0.is_active', true)
            ->assertJsonPath('data.branches.0.is_main_branch', true);

        $this->assertDatabaseHas('institutes', [
            'id' => $institute->id,
            'name' => 'Branch Settings Institute',
        ]);
        $this->assertDatabaseHas('branches', [
            'id' => $branchId,
            'name' => 'North Campus',
            'is_active' => true,
            'is_main_branch' => true,
        ]);

        $this->putJson("/api/v1/settings/institutes/{$institute->id}", [
            'branches' => [[
                'code' => 'SOUTH-01',
                'name' => 'South Branch',
                'phone' => '01700000034',
                'email' => 'south@example.test',
                'address' => 'South address',
                'is_active' => true,
                'is_main_branch' => true,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('branches', ['id' => $branchId, 'is_main_branch' => false]);
        $this->assertDatabaseHas('branches', ['code' => 'SOUTH-01', 'is_main_branch' => true]);
    }
}
