<?php

namespace App\Modules\Institute\Services\Institute;

use App\Models\Institute\Institute;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Resolve one stable institute for app-wide branding rather than the settings selector. */
class InstituteBrandingService
{
    public function get(): array
    {
        $institute = Schema::hasTable('institutes') ? Institute::query()->orderBy('id')->first() : null;

        return [
            'institute_id' => $institute?->id,
            'name' => $institute?->name ?? config('app.name'),
            'phone' => $institute?->phone,
            'email' => $institute?->email,
            'logo_url' => $institute?->logo_path ? Storage::disk('public')->url($institute->logo_path) : '/apple-touch-icon.png',
            'site_icon_url' => $institute?->logo_path ? Storage::disk('public')->url($institute->logo_path) : '/apple-touch-icon.png',
            'custom_site_icon' => (bool) $institute?->logo_path,
        ];
    }
}
