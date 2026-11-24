<?php

namespace App\Modules\Security\Services\UserControl;

use App\Models\Institute\Stuff;
use App\Models\Institute\Branch;
use App\Models\Security\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides employee choices and provisions linked system accounts. */
class UserManagementService
{
    /** Return safe account fields for the User Control list. */
    public function accounts(): array
    {
        return User::query()
            ->with('branch:id,name,code')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'designation', 'is_active', 'is_super_admin', 'branch_id'])
            ->toArray();
    }

    /** Return active branches available for regular user account assignment. */
    public function branches(): array
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'institute_id'])
            ->toArray();
    }

    /** Return employees with an email address who are not already linked to a login. */
    public function eligibleEmployees(): array
    {
        return Stuff::query()
            ->with('stuffAssignments.branch:id,name,code,is_active')
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email', 'phone', 'designation', 'stuff_code'])
            ->map(function (Stuff $stuff): array {
                // Department assignments may repeat a branch; account scope is based on distinct branches.
                $branches = $stuff->stuffAssignments
                    ->pluck('branch')
                    ->filter()
                    ->unique('id')
                    ->values();
                $branch = $branches->count() === 1 ? $branches->first() : null;

                return [
                    'id' => $stuff->id,
                    'full_name' => $stuff->full_name,
                    'email' => $stuff->email,
                    'phone' => $stuff->phone,
                    'designation' => $stuff->designation,
                    'stuff_code' => $stuff->stuff_code,
                    'branch_id' => $branch?->id,
                    'branch_name' => $branch?->name,
                    'branch_assignment_count' => $branches->count(),
                    'branch_is_active' => $branch?->is_active ?? false,
                ];
            })
            ->all();
    }

    /** Create a login and attach it to an employee in one transaction. */
    public function createFromEmployee(int $stuffId, string $password, bool $isActive): User
    {
        return DB::transaction(function () use ($stuffId, $password, $isActive): User {
            $stuff = Stuff::query()->lockForUpdate()->findOrFail($stuffId);

            if ($stuff->user_id !== null) {
                throw ValidationException::withMessages([
                    'stuff_id' => 'This employee already has a user account.',
                ]);
            }

            if (! filled($stuff->email)) {
                throw ValidationException::withMessages([
                    'stuff_id' => 'Add an email address to this employee before creating an account.',
                ]);
            }

            $assignedBranches = $stuff->stuffAssignments()
                ->with('branch:id,name,is_active')
                ->get()
                ->pluck('branch')
                ->filter()
                ->unique('id')
                ->values();

            if ($assignedBranches->count() !== 1 || ! $assignedBranches->first()->is_active) {
                throw ValidationException::withMessages([
                    'stuff_id' => 'This employee must have exactly one active branch assignment before creating a user account.',
                ]);
            }

            $email = Str::lower(trim($stuff->email));

            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'stuff_id' => 'This employee email is already used by another user account.',
                ]);
            }

            $user = User::query()->create([
                'name' => $stuff->full_name,
                'email' => $email,
                'phone' => $stuff->phone,
                'designation' => $stuff->designation,
                'branch_id' => $assignedBranches->first()->id,
                'password' => Hash::make($password),
                'is_active' => $isActive,
            ]);

            $stuff->user()->associate($user);
            $stuff->save();

            return $user;
        });
    }

    /** Create a regular login account with its required single branch scope. */
    public function createStandalone(array $data): User
    {
        $email = Str::lower(trim((string) $data['email']));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email address is already used by another user account.',
            ]);
        }

        $branchExists = Branch::query()
            ->whereKey($data['branch_id'])
            ->where('is_active', true)
            ->exists();

        if (! $branchExists) {
            throw ValidationException::withMessages([
                'branch_id' => 'Select an active branch for this user.',
            ]);
        }

        return User::query()->create([
            'name' => trim((string) $data['name']),
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'branch_id' => (int) $data['branch_id'],
            'password' => Hash::make((string) $data['password']),
            'is_active' => (bool) $data['is_active'],
            'is_super_admin' => false,
        ]);
    }

    /** Update profile and account status without allowing edits to grant super-user access. */
    public function update(User $user, array $data): User
    {
        $email = Str::lower(trim((string) $data['email']));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->where('id', '<>', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email address is already used by another user account.',
            ]);
        }

        $attributes = [
            'name' => trim((string) $data['name']),
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];

        // Super users keep global scope; only regular users can be assigned to one branch.
        if (! $user->is_super_admin) {
            $branchExists = Branch::query()
                ->whereKey($data['branch_id'])
                ->where('is_active', true)
                ->exists();

            if (! $branchExists) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Select an active branch for this user.',
                ]);
            }

            $attributes['branch_id'] = (int) $data['branch_id'];
        }

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = Hash::make((string) $data['password']);
        }

        $user->update($attributes);

        return $user->refresh()->load('branch:id,name,code');
    }
}
