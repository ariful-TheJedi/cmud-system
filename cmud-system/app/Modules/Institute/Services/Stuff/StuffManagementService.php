<?php

namespace App\Modules\Institute\Services\Stuff;

use App\Models\Institute\Branch;
use App\Models\Institute\Department;
use App\Models\Institute\Institute;
use App\Models\Institute\Stuff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Reads and updates stuff profiles and their related branch assignments. */
class StuffManagementService
{
    /** Return institute, branch, and department choices used by stuff create forms. */
    public function stuffFormOptions(): array
    {
        return Institute::query()
            ->with([
                'branches:id,institute_id,name,code',
                'departments:id,institute_id,name,code',
            ])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * Create a stuff profile, optional branch assignments, and uploaded media atomically.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $uploads
     */
    public function createStuff(array $data, array $uploads = []): Stuff
    {
        $assignments = $data['assignments'] ?? [];
        unset($data['assignments'], $data['nid_file'], $data['photo']);

        $this->validateAssignments((int) $data['institute_id'], $assignments);

        $mediaFields = [
            'nid_file' => ['path_column' => 'nid_file_path', 'directory' => 'stuff/nid-documents'],
            'photo' => ['path_column' => 'photo_path', 'directory' => 'stuff/photos'],
        ];
        $storedPaths = [];

        try {
            foreach ($mediaFields as $field => $media) {
                $file = $uploads[$field] ?? null;
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                // Store on the configured public disk so generated links honor MEDIA_FILE_PREFIX.
                $path = $file->store($media['directory'], 'public');
                if (! is_string($path)) {
                    throw ValidationException::withMessages([$field => 'The uploaded file could not be stored.']);
                }
                $storedPaths[$media['path_column']] = $path;
            }

            $stuffId = DB::transaction(function () use ($data, $assignments, $storedPaths): int {
                $stuff = Stuff::query()->create([...$data, ...$storedPaths]);

                foreach ($assignments as $assignment) {
                    $stuff->stuffAssignments()->create($assignment);
                }

                return $stuff->id;
            });
        } catch (\Throwable $exception) {
            // Avoid orphaned uploads if creating the profile or an assignment fails.
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        return $this->stuffInformation($stuffId);
    }

    /**
     * Return a paginated stuff directory with optional text, branch, and status filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{stuff: LengthAwarePaginator, total_stuff: int, branch_totals: array<int, array{id: int, name: string, stuff_count: int}>}
     */
    public function stuffRecords(array $filters = []): array
    {
        $query = Stuff::query()
            ->with([
                'institute:id,name',
                'user:id,name,email',
                'stuffAssignments.branch:id,name',
                'stuffAssignments.department:id,name',
                'teachers:id,stuff_id,teacher_code,full_name,designation,is_active',
            ])
            ->withCount('instituteExpenses')
            ->orderBy('full_name')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        return [
            'stuff' => $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString(),
            'total_stuff' => Stuff::query()->count(),
            'branch_totals' => $this->branchStuffTotals(),
        ];
    }

    /** Return one stuff profile with institute, branch, and department information. */
    public function stuffInformation(int $stuffId): Stuff
    {
        return Stuff::query()
            ->with([
                'institute:id,name',
                'user:id,name,email',
                'stuffAssignments.branch:id,institute_id,name,code',
                'stuffAssignments.department:id,institute_id,name,code',
                'teachers:id,stuff_id,teacher_code,full_name,designation,is_active',
            ])
            ->withCount('instituteExpenses')
            ->findOrFail($stuffId);
    }

    /**
     * Update a stuff profile and atomically synchronize any uploaded or removed media files.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $uploads
     * @param  array<string, bool>  $removals
     */
    public function updateStuffInformation(
        int $stuffId,
        array $data,
        array $uploads = [],
        array $removals = [],
    ): Stuff {
        // These fields map request uploads and removal toggles to stuff storage-path columns.
        $mediaFields = [
            'nid_file' => ['path_column' => 'nid_file_path', 'directory' => 'stuff/nid-documents'],
            'photo' => ['path_column' => 'photo_path', 'directory' => 'stuff/photos'],
        ];
        unset($data['nid_file'], $data['remove_nid_file'], $data['photo'], $data['remove_photo']);
        $newPaths = [];
        $oldPaths = [];

        try {
            foreach ($mediaFields as $field => $media) {
                $file = $uploads[$field] ?? null;
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                // Use the configured public media disk so URLs use MEDIA_FILE_PREFIX.
                $storedPath = $file->store($media['directory'], 'public');
                if (! is_string($storedPath)) {
                    throw ValidationException::withMessages([
                        $field => 'The uploaded file could not be stored.',
                    ]);
                }
                $newPaths[$field] = $storedPath;
            }

            $updatedStuffId = DB::transaction(function () use ($stuffId, $data, $mediaFields, $newPaths, $removals, &$oldPaths): int {
                $stuff = Stuff::query()->lockForUpdate()->findOrFail($stuffId);

                foreach ($mediaFields as $field => $media) {
                    $pathColumn = $media['path_column'];
                    $oldPaths[$field] = $stuff->{$pathColumn};

                    if (isset($newPaths[$field])) {
                        $data[$pathColumn] = $newPaths[$field];
                    } elseif ($removals[$field] ?? false) {
                        $data[$pathColumn] = null;
                    }
                }

                $stuff->update($data);

                return $stuff->id;
            });
        } catch (\Throwable $exception) {
            // Remove new uploads if the corresponding database update did not commit.
            foreach ($newPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        // Delete previous files only after the replacement paths have committed.
        foreach ($mediaFields as $field => $media) {
            $oldPath = $oldPaths[$field] ?? null;
            $fileWasChanged = isset($newPaths[$field]) || ($removals[$field] ?? false);
            if ($fileWasChanged && filled($oldPath) && $oldPath !== ($newPaths[$field] ?? null)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return $this->stuffInformation($updatedStuffId);
    }

    /** Apply supported directory filters without introducing duplicate stuff rows. */
    private function applyFilters(Builder $query, array $filters): void
    {
        // Exact numeric ID and stuff code filters are useful when a caller has an identifier.
        if (filled($filters['id'] ?? null)) {
            $query->whereKey((int) $filters['id']);
        }

        if (filled($filters['stuff_code'] ?? null)) {
            $this->applyTextFilter($query, 'stuff_code', (string) $filters['stuff_code']);
        }

        if (filled($filters['name'] ?? null)) {
            $this->applyTextFilter($query, 'full_name', (string) $filters['name']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $searchQuery) use ($search): void {
                foreach (['stuff_code', 'full_name', 'phone', 'email'] as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $searchQuery->{$method}("LOWER({$column}) LIKE ?", ['%'.mb_strtolower($search).'%']);
                }
            });
        }

        if (filled($filters['branch_id'] ?? null)) {
            $query->whereHas('stuffAssignments', fn (Builder $assignmentQuery) => $assignmentQuery->where('branch_id', $filters['branch_id']));
        }

        if (filled($filters['branch'] ?? null)) {
            $branchName = trim((string) $filters['branch']);
            $query->whereHas('stuffAssignments.branch', fn (Builder $branchQuery) => $branchQuery->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($branchName).'%']));
        }

        $status = $filters['status'] ?? $filters['employment_status'] ?? null;
        if (filled($status)) {
            $query->where('employment_status', $status);
        }
    }

    /** Ensure each assignment belongs to the institute selected for the stuff profile. */
    private function validateAssignments(int $instituteId, array $assignments): void
    {
        foreach ($assignments as $index => $assignment) {
            $branchBelongsToInstitute = Branch::query()
                ->whereKey($assignment['branch_id'])
                ->where('institute_id', $instituteId)
                ->exists();
            $departmentBelongsToInstitute = Department::query()
                ->whereKey($assignment['department_id'])
                ->where('institute_id', $instituteId)
                ->exists();

            if (! $branchBelongsToInstitute) {
                throw ValidationException::withMessages(["assignments.{$index}.branch_id" => 'The branch must belong to the selected institute.']);
            }
            if (! $departmentBelongsToInstitute) {
                throw ValidationException::withMessages(["assignments.{$index}.department_id" => 'The department must belong to the selected institute.']);
            }
        }
    }

    /** Apply portable case-insensitive partial matching for stuff text attributes. */
    private function applyTextFilter(Builder $query, string $column, string $value): void
    {
        $query->whereRaw("LOWER({$column}) LIKE ?", ['%'.mb_strtolower(trim($value)).'%']);
    }

    /** Count distinct stuff assigned to each branch so multiple departments do not inflate totals. */
    private function branchStuffTotals(): array
    {
        return Branch::query()
            ->leftJoin('stuff_assignments', 'branches.id', '=', 'stuff_assignments.branch_id')
            ->select(['branches.id', 'branches.name'])
            ->selectRaw('COUNT(DISTINCT stuff_assignments.stuff_id) AS stuff_count')
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'stuff_count' => (int) $branch->stuff_count,
            ])
            ->all();
    }
}
