<?php

namespace App\Modules\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Institute\Institute;
use App\Modules\Setting\Services\InstituteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Handles API validation and responses for institute settings. */
class InstituteSettingController extends Controller
{
    /** List available institutes so the settings page can select a target record. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Institute::query()->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    /** Return institute settings and branch records for the selected institute. */
    public function show(Institute $institute, InstituteSetting $settings): JsonResponse
    {
        return response()->json(['data' => $this->format($settings->get($institute->id))]);
    }

    /** Validate and persist institute details, branch edits, and optional logo changes. */
    public function update(Request $request, Institute $institute, InstituteSetting $settings): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'tin_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bin_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('institutes', 'bin_number')->ignore($institute->id)],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'remove_logo' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'branches' => ['sometimes', 'array'],
            'branches.*.id' => ['sometimes', 'integer', 'distinct', Rule::exists('branches', 'id')->where('institute_id', $institute->id)],
            'branches.*.code' => ['required', 'string', 'max:255'],
            'branches.*.name' => ['required', 'string', 'max:255'],
            'branches.*.phone' => ['required', 'string', 'max:30'],
            'branches.*.email' => ['nullable', 'email', 'max:255'],
            'branches.*.address' => ['required', 'string'],
            'branches.*.is_active' => ['sometimes', 'boolean'],
            'deleted_branch_ids' => ['sometimes', 'array'],
            'deleted_branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('institute_id', $institute->id)],
        ]);

        if (($data['remove_logo'] ?? false) && $request->hasFile('logo')) {
            throw ValidationException::withMessages(['logo' => 'Upload a new logo or remove the current one, not both.']);
        }

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

        return $data;
    }
}
