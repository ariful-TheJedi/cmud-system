<?php

namespace Tests\Feature\Settings;

use App\Models\Security\User;
use Tests\TestCase;

class SettingsLandingPageTest extends TestCase
{
    public function test_settings_redirects_to_the_institute_page(): void
    {
        $this->actingAs(new User)
            ->get('/settings')
            ->assertRedirect('/settings/institute');
    }
}
