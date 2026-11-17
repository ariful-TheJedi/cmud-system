<?php

namespace App\Modules\Setting\Services;

use App\Models\Institute\Institute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Reads and updates an institute's settings and its associated branch records. */
class InstituteSetting
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
        unset($data['branches']);
        unset($data['deleted_branch_ids']);

        $oldLogoPath = $institute->logo_path;
        $newLogoPath = null;

        if ($logo !== null) {
            $newLogoPath = $logo->store('institutes/logos', 'public');
            if ($newLogoPath === false) {
                throw ValidationException::withMessages(['logo' => 'The institute logo could not be saved.']);
            }
            $data['logo_path'] = $newLogoPath;
        } elseif ($removeLogo) {
            $data['logo_path'] = null;
        }

        try {
            DB::transaction(function () use ($institute, $data, $branches, $deletedBranchIds): void {
                $institute->update($data);

                // Branches are synchronized only when explicitly provided by the caller.
                if ($branches !== null) {
                    foreach ($branches as $branchData) {
                        $branchId = $branchData['id'] ?? null;
                        unset($branchData['id']);

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
            if ($newLogoPath !== null) {
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
