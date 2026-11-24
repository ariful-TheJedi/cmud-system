<?php

namespace Tests\Feature\Settings;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControlPageTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_user_control(): void
    {
        $this->get('/settings/user-control')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_render_user_control_page(): void
    {
        $this->actingAs(new \App\Models\Security\User(['name' => 'Test User']))
            ->get('/settings/user-control')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/user-control/index'));
    }
}
