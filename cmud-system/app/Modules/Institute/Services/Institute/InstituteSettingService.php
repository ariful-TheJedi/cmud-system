<?php

namespace App\Modules\Institute\Services\Institute;

use App\Models\Institute\Institute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Reads and updates an institute's settings and its associated branch records. */
class InstituteSettingService
{
    /** Return the requested institute with its branches for the settings screen. */
    public function get(int $instituteId): Institute
    {
        return Institute::query()
            ->with(['branches' => fn ($query) => $query->orderBy('name')])
            ->findOrFail($instituteId);
    }

    /** Update institute details, branch data, and optionally replace or remove its logo. */
    public function update(int $instituteId, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Institute
    {
        $institute = Institute::query()->findOrFail($instituteId);
        $branches = $data['branches'] ?? null;
        $deletedBranchIds = $data['deleted_branch_ids'] ?? [];

        // Return a clear deployment error instead of querying a column not present before migration.
        if ($branches !== null && collect($branches)->contains(fn (array $branch): bool => array_key_exists('is_main_branch', $branch))
            && ! Schema::hasColumn('branches', 'is_main_branch')) {
            throw new ServiceUnavailableHttpException(null, 'The main-branch database migration has not been applied yet.');
        }

        unset($data['branches']);
        unset($data['deleted_branch_ids'], $data['logo'], $data['remove_logo']);

        $oldLogoPath = $institute->logo_path;
        $newLogoPath = null;

        try {
            if ($logo !== null) {
                $newLogoPath = $logo->store('institutes/logos', 'public');
                if ($newLogoPath === false) {
                    throw ValidationException::withMessages(['logo' => 'The institute logo could not be saved.']);
                }
                $data['logo_path'] = $newLogoPath;
            } elseif ($removeLogo) {
                $data['logo_path'] = null;
            }

            DB::transaction(function () use ($institute, $data, $branches, $deletedBranchIds): void {
                $institute->update($data);

                // Branches are synchronized only when explicitly provided by the caller.
                if ($branches !== null) {
                    foreach ($branches as $branchData) {
                        $branchId = $branchData['id'] ?? null;
                        unset($branchData['id']);

                        $existingBranch = $branchId === null
                            ? null
                            : $institute->branches()->whereKey($branchId)->firstOrFail();
                        $becomesInactive = array_key_exists('is_active', $branchData) && ! $branchData['is_active'];
                        $isCurrentMainBranch = $existingBranch?->is_main_branch === true;

                        if ($becomesInactive && (($branchData['is_main_branch'] ?? false) === true || $isCurrentMainBranch)) {
                            throw ValidationException::withMessages([
                                'branches' => 'The main branch must remain active. Select another active main branch first.',
                            ]);
                        }

                        // A selected main branch replaces the previous one within this institute.
                        if (($branchData['is_main_branch'] ?? false) === true) {
                            $institute->branches()->where('is_main_branch', true)->update(['is_main_branch' => false]);
                        } elseif ($branchId !== null && ($branchData['is_main_branch'] ?? null) === false
                            && $isCurrentMainBranch) {
                            throw ValidationException::withMessages([
                                'branches' => 'Select another main branch before clearing the current main branch.',
                            ]);
                        }

                        if ($branchId !== null) {
                            $institute->branches()->whereKey($branchId)->firstOrFail()->update($branchData);
                        } else {
                            $institute->branches()->create($branchData);
                        }
                    }
                }

                if ($deletedBranchIds !== []) {
                    $institute->branches()->whereIn('id', $deletedBranchIds)->get()->each->delete();
                }
            });
        } catch (\Throwable $exception) {
            // Do not leave an uploaded file behind when the database update fails.
            if (is_string($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        // Remove the previous file only after the new database state has committed.
        if (($newLogoPath !== null || $removeLogo) && $oldLogoPath !== null && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return $this->get($instituteId);
    }

    /** Remove one branch, refusing to delete it if related records prevent deletion. */
    public function deleteBranch(int $instituteId, int $branchId): void
    {
        $institute = Institute::query()->findOrFail($instituteId);
        $branch = $institute->branches()->whereKey($branchId)->firstOrFail();
        $branch->delete();
    }
}
