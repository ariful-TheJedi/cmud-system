<?php

namespace App\Modules\Academy\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Teacher;
use App\Modules\Academy\Requests\TeacherRequest;
use App\Modules\Academy\Services\Teachers\TeacherManagementService;
use Illuminate\Http\JsonResponse;

class TeacherManagementController extends Controller
{
    public function index(TeacherManagementService $teachers): JsonResponse
    {
        return response()->json($teachers->paginate());
    }

    public function show(Teacher $teacher, TeacherManagementService $teachers): JsonResponse
    {
        return response()->json(['data' => $teachers->find($teacher)]);
    }

    public function store(TeacherRequest $request, TeacherManagementService $teachers): JsonResponse
    {
        return response()->json(['data' => $teachers->create($request->validated())], 201);
    }

    public function update(
        TeacherRequest $request,
        Teacher $teacher,
        TeacherManagementService $teachers,
    ): JsonResponse {
        return response()->json(['data' => $teachers->update($teacher, $request->validated())]);
    }

    public function destroy(Teacher $teacher, TeacherManagementService $teachers): JsonResponse
    {
        if (! $teachers->delete($teacher)) {
            return response()->json(['message' => 'This teacher is in use and cannot be deleted.'], 409);
        }

        return response()->json(['message' => 'Teacher deleted.']);
    }
}
