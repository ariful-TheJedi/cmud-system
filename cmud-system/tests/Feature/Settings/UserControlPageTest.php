<?php

namespace Tests\Feature\Settings;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControlPageTest extends TestCase
{
    public function test_guest_can_render_the_user_control_folder_page(): void
    {
        $this->get('/settings/user-control')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/user-control/index'));
    }
}
