<?php

namespace App\Modules\Academy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Academy\Batch;
use App\Models\Institute\Branch;
use App\Modules\Academy\Http\Requests\StudentAdmissionRequest;
use App\Modules\Academy\Services\StudentAdmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentAdmissionController extends Controller
{
    public function store(StudentAdmissionRequest $request, StudentAdmissionService $admissions): JsonResponse
    {
        $data = $request->validated();

        try {
            $validator = Validator::make($data, []);
            $validator->after(function ($validator) use ($data): void {
                $branch = Branch::query()->find($data['admission_branch_id']);
                $batch = Batch::query()->with('course')->find($data['batch_id']);
                $course = $batch?->course;

                if ($branch && $batch && $batch->branch_id !== $branch->id) {
                    $validator->errors()->add('batch_id', 'The batch must be offered at the selected admission branch.');
                }

                if ($branch && $course && $branch->institute_id !== $course->institute_id) {
                    $validator->errors()->add('admission_branch_id', 'The branch must belong to the batch course institute.');
                }

                if ($batch && isset($data['course_id']) && (int) $data['course_id'] !== $batch->course_id) {
                    $validator->errors()->add('batch_id', 'The batch must belong to the selected course.');
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'failure',
                    'message' => 'The admission details are invalid.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $admissions->admit($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Student admission submitted successfully.',
                'data' => [
                    'student' => [
                        'id' => $result['student']->id,
                        'student_code' => $result['student']->student_code,
                        'full_name' => $result['student']->full_name,
                    ],
                    'enrollment' => [
                        'id' => $result['enrollment']->id,
                        'enrollment_code' => $result['enrollment']->enrollment_code,
                    ],
                ],
            ], 201);
        } catch (Throwable $exception) {
            Log::error('Student admission API failed.', [
                'student_code' => $data['student_code'] ?? null,
                'exception' => $exception,
            ]);

            return response()->json([
                'status' => 'failure',
                'message' => 'The student admission could not be completed. Please try again.',
            ], 500);
        }
    }
}
