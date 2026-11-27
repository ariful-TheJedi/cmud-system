<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add stage certificate configuration, stage certificate ownership,
     * stage module assignment, and explicit enrollment readmission tracking.
     *
     * Existing module-to-course links still use subjects.course_id at this
     * migration point; the independent-module migration later moves them to a pivot.
     * The nullable stage link lets the institute assign modules deliberately.
     */
    public function up(): void
    {
        Schema::table('course_stages', function (Blueprint $table): void {
            // Only stages explicitly configured by the institute can award certificates.
            $table->boolean('certificate_enabled')->default(false);
        });

        Schema::table('certificates', function (Blueprint $table): void {
            // A certificate belongs either to a full enrollment or to one completed stage.
            $table->foreignId('student_stage_record_id')
                ->nullable()
                ->constrained('student_stage_records')
                ->restrictOnDelete();

            // Stage certificates need enrollment_id nullable so they can reference only a stage.
            $table->unsignedBigInteger('enrollment_id')->nullable()->change();
        });

        Schema::table('subjects', function (Blueprint $table): void {
            // Nullable during rollout to preserve existing course-level subject assignments.
            $table->foreignId('course_stage_id')
                ->nullable()
                ->constrained('course_stages')
                ->restrictOnDelete();
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            // Links a readmission to the prior enrollment while preserving its history.
            $table->foreignId('readmitted_from_enrollment_id')
                ->nullable()
                ->constrained('enrollments')
                ->restrictOnDelete();
        });

        // Ensure each certificate has exactly one owner: a full enrollment or a stage record.
        if (DB::getDriverName() === 'pgsql') {
            // Existing certificates are enrollment-owned; retain them as course certificates.
            DB::statement('ALTER TABLE certificates DROP CONSTRAINT IF EXISTS chk_certificates_single_owner');
            DB::statement('UPDATE certificates SET student_stage_record_id = NULL WHERE enrollment_id IS NOT NULL');
            DB::statement(
                'ALTER TABLE certificates ADD CONSTRAINT chk_certificates_single_owner '
                .'CHECK ((enrollment_id IS NOT NULL AND student_stage_record_id IS NULL) '
                .'OR (enrollment_id IS NULL AND student_stage_record_id IS NOT NULL))',
            );
            DB::statement('CREATE UNIQUE INDEX uq_certificates_enrollment_template ON certificates (enrollment_id, certificate_template_id) WHERE enrollment_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX uq_certificates_stage_template ON certificates (student_stage_record_id, certificate_template_id) WHERE student_stage_record_id IS NOT NULL');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER chk_certificates_single_owner_insert BEFORE INSERT ON certificates WHEN NOT ((NEW.enrollment_id IS NOT NULL AND NEW.student_stage_record_id IS NULL) OR (NEW.enrollment_id IS NULL AND NEW.student_stage_record_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'certificate must belong to exactly one enrollment or stage'); END");
            DB::statement("CREATE TRIGGER chk_certificates_single_owner_update BEFORE UPDATE OF enrollment_id, student_stage_record_id ON certificates WHEN NOT ((NEW.enrollment_id IS NOT NULL AND NEW.student_stage_record_id IS NULL) OR (NEW.enrollment_id IS NULL AND NEW.student_stage_record_id IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'certificate must belong to exactly one enrollment or stage'); END");
            DB::statement('CREATE UNIQUE INDEX uq_certificates_enrollment_template ON certificates (enrollment_id, certificate_template_id) WHERE enrollment_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX uq_certificates_stage_template ON certificates (student_stage_record_id, certificate_template_id) WHERE student_stage_record_id IS NOT NULL');
        }

        // Permit expired enrollment records while keeping the existing lifecycle values valid.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS chk_enrollments_status');
            DB::statement("ALTER TABLE enrollments ADD CONSTRAINT chk_enrollments_status CHECK (status IN ('enrolled','ongoing','on_hold','completed','discontinued','cancelled','expired'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS chk_enrollments_status_insert');
            DB::statement('DROP TRIGGER IF EXISTS chk_enrollments_status_update');
            foreach (['insert' => 'BEFORE INSERT', 'update' => 'BEFORE UPDATE'] as $suffix => $timing) {
                DB::statement("CREATE TRIGGER chk_enrollments_status_{$suffix} {$timing} ON enrollments BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('enrolled','ongoing','on_hold','completed','discontinued','cancelled','expired') THEN RAISE(ABORT, 'invalid enrollment status') END; END");
            }
        }
    }

    /** Remove the added schema while retaining pre-existing enrollment data. */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE certificates DROP CONSTRAINT IF EXISTS chk_certificates_single_owner');
            DB::statement('DROP INDEX IF EXISTS uq_certificates_enrollment_template');
            DB::statement('DROP INDEX IF EXISTS uq_certificates_stage_template');
            DB::statement('ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS chk_enrollments_status');
            DB::statement("ALTER TABLE enrollments ADD CONSTRAINT chk_enrollments_status CHECK (status IN ('enrolled','ongoing','on_hold','completed','discontinued','cancelled'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach ([
                'chk_certificates_single_owner_insert',
                'chk_certificates_single_owner_update',
                'chk_enrollments_status_insert',
                'chk_enrollments_status_update',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
            DB::statement('DROP INDEX IF EXISTS uq_certificates_enrollment_template');
            DB::statement('DROP INDEX IF EXISTS uq_certificates_stage_template');
            foreach (['insert' => 'BEFORE INSERT', 'update' => 'BEFORE UPDATE'] as $suffix => $timing) {
                DB::statement("CREATE TRIGGER chk_enrollments_status_{$suffix} {$timing} ON enrollments BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('enrolled','ongoing','on_hold','completed','discontinued','cancelled') THEN RAISE(ABORT, 'invalid enrollment status') END; END");
            }
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('readmitted_from_enrollment_id');
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_stage_id');
        });
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_stage_record_id');
            $table->unsignedBigInteger('enrollment_id')->nullable(false)->change();
        });
        Schema::table('course_stages', function (Blueprint $table): void {
            $table->dropColumn('certificate_enabled');
        });
    }
};
