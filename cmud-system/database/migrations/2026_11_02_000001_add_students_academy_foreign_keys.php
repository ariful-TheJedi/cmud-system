<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign key columns described by the Students & Academy relationships in relation.txt.
     * Institute, branch, and staff references are already added by the foundation migration.
     *
     * @return array<string, array<string, string>>
     */
    private function studentsAcademyForeignKeys(): array
    {
        return [
            'students' => [
                'medical_college_id' => 'medical_colleges',
            ],
            'course_stages' => [
                'course_id' => 'courses',
            ],
            'batches' => [
                'course_id' => 'courses',
            ],
            'enrollments' => [
                'student_id' => 'students',
                'course_id' => 'courses',
                'batch_id' => 'batches',
            ],
            'student_stage_records' => [
                'enrollment_id' => 'enrollments',
                'course_stage_id' => 'course_stages',
                'batch_id' => 'batches',
            ],
            'exam_records' => [
                'student_stage_record_id' => 'student_stage_records',
            ],
            'certificate_templates' => [
                'course_id' => 'courses',
            ],
            'certificates' => [
                'enrollment_id' => 'enrollments',
                'certificate_template_id' => 'certificate_templates',
            ],
            'communication_groups' => [
                'course_id' => 'courses',
                'batch_id' => 'batches',
            ],
            'student_group_memberships' => [
                'student_id' => 'students',
                'communication_group_id' => 'communication_groups',
            ],
            'course_teacher' => [
                'course_id' => 'courses',
                'teacher_id' => 'teachers',
            ],
            'student_tuitions' => [
                'enrollment_id' => 'enrollments',
            ],
            'student_expense_price_versions' => [
                'course_id' => 'courses',
                'course_stage_id' => 'course_stages',
            ],
            'student_expenses' => [
                'student_id' => 'students',
                'enrollment_id' => 'enrollments',
                'student_stage_record_id' => 'student_stage_records',
            ],
            'institute_expenses' => [
                'teacher_id' => 'teachers',
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->studentsAcademyForeignKeys() as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach ($foreignKeys as $column => $referencedTable) {
                    $table->foreign($column)
                        ->references('id')
                        ->on($referencedTable);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->studentsAcademyForeignKeys(), true) as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach (array_keys($foreignKeys) as $column) {
                    $table->dropForeign([$column]);
                }
            });
        }
    }
};
