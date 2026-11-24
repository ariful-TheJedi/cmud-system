<?php

namespace App\Modules\Security\Controllers\UserControl;

use App\Http\Controllers\Controller;
use App\Models\Security\User;
use App\Modules\Security\Requests\UserControl\CreateUserRequest;
use App\Modules\Security\Requests\UserControl\CreateUserFromStuffRequest;
use App\Modules\Security\Requests\UserControl\UpdateUserRequest;
use App\Modules\Security\Services\UserControl\UserManagementService;
use Illuminate\Http\JsonResponse;

/** Handles HTTP responses for user account management. */
class UserManagementController extends Controller
{
    /** Return safe user account fields for the management screen. */
    public function index(UserManagementService $users): JsonResponse
    {
        return response()->json(['data' => $users->accounts()]);
    }

    /** Return employees that can be linked to a newly created account. */
    public function eligibleEmployees(UserManagementService $users): JsonResponse
    {
        return response()->json(['data' => $users->eligibleEmployees()]);
    }

    /** Return active branch choices for assigning regular user scope. */
    public function branches(UserManagementService $users): JsonResponse
    {
        return response()->json(['data' => $users->branches()]);
    }

    /** Create a standalone regular user assigned to exactly one branch. */
    public function store(CreateUserRequest $request, UserManagementService $users): JsonResponse
    {
        $user = $users->createStandalone($request->validated());

        return response()->json([
            'data' => $this->accountData($user),
            'message' => 'Branch user account created successfully.',
        ], 201);
    }

    /** Create an account from employee details and return safe account fields only. */
    public function storeFromEmployee(
        CreateUserFromStuffRequest $request,
        UserManagementService $users,
    ): JsonResponse {
        $user = $users->createFromEmployee(
            (int) $request->validated('stuff_id'),
            $request->validated('password'),
            (bool) $request->validated('is_active'),
        );

        return response()->json([
            'data' => $this->accountData($user),
            'message' => 'User account created and linked to the employee.',
        ], 201);
    }

    /** Update an account and return its safe display fields. */
    public function update(UpdateUserRequest $request, User $user, UserManagementService $users): JsonResponse
    {
        $user = $users->update($user, $request->validated());

        return response()->json([
            'data' => $this->accountData($user),
            'message' => 'User account updated successfully.',
        ]);
    }

    /** Keep response fields consistent across account creation methods. */
    private function accountData(User $user): array
    {
        $user->loadMissing('branch:id,name,code');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'designation' => $user->designation,
            'branch_id' => $user->branch_id,
            'branch' => $user->branch,
            'is_active' => $user->is_active,
            'is_super_admin' => $user->is_super_admin,
        ];
    }
}
