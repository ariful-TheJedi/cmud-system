<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use App\Modules\Academy\Http\Requests\StudentUpdateRequest;
use App\Modules\Academy\Http\Resources\StudentDetailResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentUpdateController extends Controller
{
    public function edit(string $studentCode): JsonResponse
    {
        $student = Student::query()->where('student_code', $studentCode)->firstOrFail();

        return response()->json([
            'data' => new StudentDetailResource($student->load('admissionBranch:id,name')),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->where('institute_id', $student->institute_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(StudentUpdateRequest $request, string $studentCode): StudentDetailResource
    {
        $student = Student::query()->where('student_code', $studentCode)->firstOrFail();
        $data = $request->validated();

        DB::transaction(function () use ($student, $data): void {
            $phones = array_values($data['phone_numbers']);
            $student->fill(Arr::except($data, ['phone_numbers']));
            $student->phone_numbers = $phones;
            $student->phone = $phones[0];
            $student->alternative_phone = $phones[1] ?? null;
            $student->save();
        });

        $student->load([
            'institute:id,name',
            'admissionBranch:id,name',
            'medicalCollege:id,name',
            'enrollments' => fn ($query) => $query->orderByDesc('id'),
            'enrollments.course:id,code,name',
            'enrollments.branch:id,name',
            'enrollments.batch:id,name',
            'enrollments.tuition.payments' => fn ($query) => $query->orderByDesc('paid_at'),
            'enrollments.tuition.payments.paymentMethod:id,name',
            'expenses' => fn ($query) => $query->orderByDesc('id'),
            'expenses.expenseType:id,name',
            'expenses.branch:id,name',
            'expenses.payments' => fn ($query) => $query->orderByDesc('paid_at'),
            'expenses.payments.paymentMethod:id,name',
        ]);

        return new StudentDetailResource($student);
    }
}
