<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the Student & Academy foundation tables.
     *
     * Reference columns are created here without foreign-key constraints.
     * Those constraints will be added in separate relationship migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->foreignId('admission_branch_id');
            $table->string('student_code')->unique();
            $table->date('admission_date');
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone', 30);
            $table->string('alternative_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('present_address');
            $table->text('permanent_address')->nullable();
            $table->foreignId('medical_college_id')->nullable();
            $table->string('qualification');
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('bmdc_number')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('documents')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('medical_colleges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('country');
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedSmallInteger('completion_period_months');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('course_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->string('code');
            $table->string('name');
            $table->unsignedSmallInteger('sequence_number');
            $table->unsignedSmallInteger('duration_months');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->foreignId('branch_id');
            $table->string('code');
            $table->string('name');
            $table->date('admission_opens_on')->nullable();
            $table->date('admission_closes_on')->nullable();
            $table->date('starts_on');
            $table->date('expected_ends_on');
            $table->date('actual_ends_on')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_code')->unique();
            $table->foreignId('student_id');
            $table->foreignId('course_id');
            $table->foreignId('branch_id');
            $table->foreignId('batch_id')->nullable();
            $table->date('enrollment_date');
            $table->date('starts_on')->nullable();
            $table->date('expected_completion_on');
            $table->date('completion_deadline_on');
            $table->date('completed_on')->nullable();
            $table->string('status');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_stage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id');
            $table->foreignId('course_stage_id');
            $table->foreignId('batch_id');
            $table->date('started_on')->nullable();
            $table->date('expected_completion_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_stage_record_id');
            $table->string('exam_name');
            $table->unsignedSmallInteger('attempt_number');
            $table->date('exam_date');
            $table->string('roll_number')->nullable();
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->decimal('obtained_marks', 8, 2)->nullable();
            $table->string('grade')->nullable();
            $table->string('result_status');
            $table->date('result_published_on')->nullable();
            $table->string('result_file_path')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->foreignId('course_id')->nullable();
            $table->string('name');
            $table->unsignedSmallInteger('version_number');
            $table->string('template_file_path');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id');
            $table->foreignId('certificate_template_id');
            $table->string('certificate_number')->nullable()->unique();
            $table->string('recipient_name');
            $table->string('course_title');
            $table->date('issued_on')->nullable();
            $table->string('certificate_file_path')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by')->nullable();
            $table->string('delivery_method')->nullable();
            $table->string('delivery_reference')->nullable();
            $table->string('status');
            $table->text('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('course_id')->nullable();
            $table->foreignId('batch_id')->nullable();
            $table->string('name');
            $table->string('platform');
            $table->string('group_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id');
            $table->foreignId('communication_group_id');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->foreignId('staff_id')->nullable();
            $table->string('teacher_code')->unique();
            $table->string('full_name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->string('designation')->nullable();
            $table->string('organization')->nullable();
            $table->string('bmdc_number')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('course_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->foreignId('teacher_id');
            $table->string('teaching_role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->foreignId('teacher_id');
            $table->timestamps();

            $table->unique(['branch_id', 'teacher_id']);
        });

        Schema::create('course_price_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->foreignId('branch_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('course_subject', function (Blueprint $table) {
            $table->foreignId('course_id');
            $table->foreignId('subject_id');
            $table->timestamps();

            $table->primary(['course_id', 'subject_id']);
        });

        DB::statement('CREATE UNIQUE INDEX course_price_versions_default_version_unique ON course_price_versions (course_id, version_number) WHERE branch_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX course_price_versions_branch_version_unique ON course_price_versions (course_id, branch_id, version_number) WHERE branch_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('course_price_versions');
        Schema::dropIfExists('branch_teacher');
        Schema::dropIfExists('course_teacher');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('student_group_memberships');
        Schema::dropIfExists('communication_groups');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('exam_records');
        Schema::dropIfExists('student_stage_records');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('course_stages');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('medical_colleges');
        Schema::dropIfExists('students');
    }
};
