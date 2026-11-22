<?php

namespace Tests\Feature\Settings;

use App\Models\Institute\Institute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstituteBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function institute(): Institute
    {
        return Institute::query()->create([
            'code' => 'BRANDING', 'name' => 'Branding Institute', 'phone' => '01700000000',
            'email' => 'branding@example.test', 'address' => 'Dhaka', 'is_active' => true,
        ]);
    }

    private function icon(string $name = 'icon.png', int $size = 32): UploadedFile
    {
        // Generate a valid square PNG without depending on the optional GD extension.
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $pixels = str_repeat("\x00".str_repeat("\x00\x80\x80\xff", $size), $size);
        $bytes = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 6, 0, 0, 0)).$chunk('IDAT', gzcompress($pixels)).$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_logo_upload_is_shared_as_app_branding(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $response = $this->postJson("/api/v1/settings/institutes/{$institute->id}", [
            '_method' => 'PUT', 'logo' => $this->icon('logo.png'),
        ])->assertOk();
        Storage::disk('public')->assertExists($institute->fresh()->logo_path);
        $this->get('/settings/institute')->assertInertia(fn (Assert $page) => $page
            ->where('branding.logo_url', $response->json('data.logo_url'))
            ->where('branding.site_icon_url', $response->json('data.logo_url')));
        $response->assertJsonPath('data.site_icon_url', $response->json('data.logo_url'));
        $this->get('/settings/institute')->assertSee($response->json('data.logo_url'), false);
    }

    public function test_logo_replacement_and_removal_update_both_images(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $url = "/api/v1/settings/institutes/{$institute->id}";
        $this->postJson($url, ['_method' => 'PUT', 'logo' => $this->icon()])->assertOk();
        $oldPath = $institute->fresh()->logo_path;
        $response = $this->postJson($url, ['_method' => 'PUT', 'logo' => $this->icon('replacement.png')])->assertOk();
        Storage::disk('public')->assertMissing($oldPath);
        $response->assertJsonPath('data.site_icon_url', $response->json('data.logo_url'));
        $newPath = $institute->fresh()->logo_path;
        $this->putJson($url, ['remove_logo' => true])->assertOk()
            ->assertJsonPath('data.logo_url', null)->assertJsonPath('data.site_icon_url', null);
        Storage::disk('public')->assertMissing($newPath);
        $this->get('/settings/institute')->assertInertia(fn (Assert $page) => $page
            ->where('branding.logo_url', '/apple-touch-icon.png')
            ->where('branding.site_icon_url', '/apple-touch-icon.png'));
        $this->assertSame('Branding Institute', $institute->fresh()->name);
    }

    public function test_invalid_upload_and_upload_remove_conflict_are_rejected(): void
    {
        Storage::fake('public');
        $institute = $this->institute();
        $url = "/api/v1/settings/institutes/{$institute->id}";
        $this->postJson($url, ['_method' => 'PUT', 'logo' => UploadedFile::fake()->create('file.txt', 1, 'text/plain')])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->postJson($url, ['_method' => 'PUT', 'logo' => $this->icon(), 'remove_logo' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->assertNull($institute->fresh()->logo_path);
    }
}
