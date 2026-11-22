<?php

namespace App\Modules\Academy\Services\Students;

use App\Models\Academy\Student;
use App\Models\Institute\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentProfileService
{
    /** @return array{student: Student, branches: Collection<int, Branch>} */
    public function editData(string $studentCode): array
    {
        $student = Student::query()
            ->where('student_code', $studentCode)
            ->with('admissionBranch:id,name')
            ->firstOrFail();

        return [
            'student' => $student,
            'branches' => Branch::query()
                ->where('institute_id', $student->institute_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(string $studentCode, array $data): Student
    {
        return DB::transaction(function () use ($studentCode, $data): Student {
            $student = Student::query()
                ->where('student_code', $studentCode)
                ->lockForUpdate()
                ->firstOrFail();
            $branch = Branch::query()->findOrFail((int) $data['admission_branch_id']);

            if (! $branch->is_active || (int) $branch->institute_id !== (int) $student->institute_id) {
                throw ValidationException::withMessages([
                    'admission_branch_id' => 'The admission branch must be active and belong to the student’s institute.',
                ]);
            }

            $phones = array_values($data['phone_numbers']);
            unset($data['phone_numbers']);
            $student->fill($data);
            $student->setAttribute('phone_numbers', $phones);
            $student->phone = $phones[0];
            $student->alternative_phone = $phones[1] ?? null;
            $student->save();

            return Student::query()
                ->whereKey($student->id)
                ->with([
                    'institute:id,name',
                    'admissionBranch:id,name',
                    'medicalCollege:id,name',
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
                ])
                ->firstOrFail();
        });
    }
}
