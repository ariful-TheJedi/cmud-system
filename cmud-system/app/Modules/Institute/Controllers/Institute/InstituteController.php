<?php

namespace App\Modules\Institute\Controllers\Institute;

use App\Http\Controllers\Controller;
use App\Models\Institute\Institute;
use App\Modules\Institute\Requests\Institute\InstituteUpdateRequest;
use App\Modules\Institute\Services\Institute\InstituteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/** Handles API validation and responses for institute settings. */
class InstituteController extends Controller
{
    /** List available institutes so the settings page can select a target record. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Institute::query()->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    /** Return institute settings and branch records for the selected institute. */
    public function show(Institute $institute, InstituteSettingService $settings): JsonResponse
    {
        return response()->json(['data' => $this->format($settings->get($institute->id))]);
    }

    /** Validate and persist institute details, branch edits, and optional logo changes. */
    public function update(InstituteUpdateRequest $request, Institute $institute, InstituteSettingService $settings): JsonResponse
    {
        $data = $request->validated();

        $updated = $settings->update(
            $institute->id,
            $data,
            $request->file('logo'),
            (bool) ($data['remove_logo'] ?? false),
        );

        return response()->json(['data' => $this->format($updated), 'message' => 'Institute settings saved.']);
    }

    /** Convert a model to a stable API shape and expose a browser-ready logo URL. */
    private function format(Institute $institute): array
    {
        $data = $institute->toArray();
        $data['logo_url'] = $institute->logo_path ? Storage::disk('public')->url($institute->logo_path) : null;

        $data['site_icon_url'] = $data['logo_url'];

        return $data;
    }
}
