<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('status')->default('active');
            $table->boolean('is_available_for_enrollment')->default(true);
        });

        DB::table('courses')
            ->where('is_active', false)
            ->update([
                'status' => 'inactive',
                'is_available_for_enrollment' => false,
            ]);

        Schema::table('course_stages', function (Blueprint $table): void {
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('requires_exam')->default(false);
        });

        Schema::create('enrollment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
        });

        Schema::create('student_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_stage_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
            $table->index(['student_stage_record_id', 'created_at']);
        });

        Schema::create('student_academic_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_stage_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('exception_type', 50);
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->json('details')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'exception_type', 'status']);
        });

        Schema::create('student_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type', 50);
            $table->string('channel', 50);
            $table->string('direction', 20)->nullable();
            $table->string('subject')->nullable();
            $table->text('remarks');
            $table->string('outcome')->nullable();
            $table->timestamp('contacted_at');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'contacted_at']);
            $table->index(['responsible_user_id', 'follow_up_at']);
        });

        Schema::create('student_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->string('related_type', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('dedupe_key')->nullable()->unique();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'read_at', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE courses ADD CONSTRAINT chk_courses_status '
                ."CHECK (status IN ('draft', 'active', 'inactive', 'discontinued'))",
            );
            DB::statement(
                'ALTER TABLE student_academic_exceptions ADD CONSTRAINT chk_student_academic_exception_status '
                ."CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))",
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                'CREATE TRIGGER chk_courses_status_insert BEFORE INSERT ON courses '
                ."WHEN NEW.status NOT IN ('draft', 'active', 'inactive', 'discontinued') "
                ."BEGIN SELECT RAISE(ABORT, 'invalid course status'); END",
            );
            DB::statement(
                'CREATE TRIGGER chk_courses_status_update BEFORE UPDATE OF status ON courses '
                ."WHEN NEW.status NOT IN ('draft', 'active', 'inactive', 'discontinued') "
                ."BEGIN SELECT RAISE(ABORT, 'invalid course status'); END",
            );
            DB::statement(
                'CREATE TRIGGER chk_academic_exception_status_insert BEFORE INSERT ON student_academic_exceptions '
                ."WHEN NEW.status NOT IN ('pending', 'approved', 'rejected', 'cancelled') "
                ."BEGIN SELECT RAISE(ABORT, 'invalid academic exception status'); END",
            );
            DB::statement(
                'CREATE TRIGGER chk_academic_exception_status_update BEFORE UPDATE OF status ON student_academic_exceptions '
                ."WHEN NEW.status NOT IN ('pending', 'approved', 'rejected', 'cancelled') "
                ."BEGIN SELECT RAISE(ABORT, 'invalid academic exception status'); END",
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE courses DROP CONSTRAINT IF EXISTS chk_courses_status');
            DB::statement(
                'ALTER TABLE student_academic_exceptions DROP CONSTRAINT IF EXISTS chk_student_academic_exception_status',
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'chk_courses_status_insert',
                'chk_courses_status_update',
                'chk_academic_exception_status_insert',
                'chk_academic_exception_status_update',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }

        Schema::dropIfExists('student_notifications');
        Schema::dropIfExists('student_communications');
        Schema::dropIfExists('student_academic_exceptions');
        Schema::dropIfExists('student_stage_histories');
        Schema::dropIfExists('enrollment_histories');

        Schema::table('course_stages', function (Blueprint $table): void {
            $table->dropColumn(['is_mandatory', 'requires_exam']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['status', 'is_available_for_enrollment']);
        });
    }
};
