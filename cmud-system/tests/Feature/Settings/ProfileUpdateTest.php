<?php

namespace Tests\Feature\Settings;

use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_can_be_uploaded_and_replaced(): void
    {
        Storage::fake('public');
        $user = User::forceCreate(['name' => 'Profile User', 'email' => 'profile@example.test', 'password' => 'password', 'is_active' => true]);

        foreach (['first.png', 'second.png'] as $name) {
            $oldPath = $user->photo_path;
            $this->actingAs($user)->post(route('profile.update'), [
                '_method' => 'patch',
                'name' => 'Updated Name',
                'email' => $user->email,
                'phone' => '01712345678',
                'photo' => UploadedFile::fake()->image($name),
            ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

            $user->refresh();
            Storage::disk('public')->assertExists($user->photo_path);
            $this->assertSame('01712345678', $user->phone);
            $this->assertNotNull($user->avatar);
            if ($oldPath) {
                Storage::disk('public')->assertMissing($oldPath);
            }
        }
    }

    public function test_profile_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        $user = User::forceCreate(['name' => 'Profile User', 'email' => 'profile@example.test', 'password' => 'password', 'is_active' => true]);
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->create('document.pdf'),
        ])->assertSessionHasErrors('photo');
        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
