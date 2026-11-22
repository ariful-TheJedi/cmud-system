<?php

namespace Tests\Feature\Academy;

use App\Modules\Academy\Services\Shared\AcademyPageDataService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademicManagementPageTest extends TestCase
{
    public function test_management_entry_redirects_to_courses(): void
    {
        $this->get('/academy/manage')->assertRedirect('/academy/manage/courses');
    }

    public function test_each_catalog_section_has_its_own_page(): void
    {
        $this->mock(AcademyPageDataService::class, function ($mock) {
            $mock->shouldReceive('managementPageData')->times(4)->andReturn([
                'institutes' => [],
                'branches' => [],
            ]);
        });

        foreach (['courses', 'batches', 'subjects', 'teachers'] as $section) {
            $this->get(route("academy.manage.{$section}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component("academy/management/{$section}")
                    ->has('institutes')
                    ->has('branches'));
        }
    }
}
