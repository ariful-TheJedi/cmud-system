<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_status");
            DB::statement("ALTER TABLE students ADD CONSTRAINT chk_students_status CHECK (status IN ('registered','active','deactivated','suspended'))");

            $duplicateAttempts = DB::table('exam_records')
                ->select('student_stage_record_id', 'exam_name', 'attempt_number')
                ->selectRaw('COUNT(*) as duplicate_count')
                ->groupBy('student_stage_record_id', 'exam_name', 'attempt_number')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($duplicateAttempts) {
                throw new RuntimeException(
                    'Cannot finalize examination attempt uniqueness: duplicate stage, '
                    .'exam name, and attempt-number rows already exist.',
                );
            }

            DB::statement("ALTER TABLE exam_records DROP CONSTRAINT IF EXISTS uq_exam_attempt");
            DB::statement(
                'ALTER TABLE exam_records ADD CONSTRAINT uq_exam_attempt '
                .'UNIQUE (student_stage_record_id, exam_name, attempt_number)',
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'chk_students_status_insert',
                'chk_students_status_update',
                'chk_certificate_reissue_status_insert',
                'chk_certificate_reissue_status_update',
                'chk_certificate_reissue_not_self_insert',
                'chk_certificate_reissue_not_self_update',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }

            DB::statement("CREATE TRIGGER chk_students_status_insert BEFORE INSERT ON students BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('registered','active','deactivated','suspended') THEN RAISE(ABORT, 'invalid status') END; END");
            DB::statement("CREATE TRIGGER chk_students_status_update BEFORE UPDATE ON students BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('registered','active','deactivated','suspended') THEN RAISE(ABORT, 'invalid status') END; END");

            if (Schema::hasTable('certificate_reissue_requests')) {
                DB::statement("CREATE TRIGGER chk_certificate_reissue_status_insert BEFORE INSERT ON certificate_reissue_requests BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('requested','under_review','approved','rejected','processing','issued','delivered','cancelled') THEN RAISE(ABORT, 'invalid status') END; END");
                DB::statement("CREATE TRIGGER chk_certificate_reissue_status_update BEFORE UPDATE ON certificate_reissue_requests BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('requested','under_review','approved','rejected','processing','issued','delivered','cancelled') THEN RAISE(ABORT, 'invalid status') END; END");
                DB::statement("CREATE TRIGGER chk_certificate_reissue_not_self_insert BEFORE INSERT ON certificate_reissue_requests WHEN NEW.replacement_certificate_id IS NOT NULL AND NEW.replacement_certificate_id = NEW.original_certificate_id BEGIN SELECT RAISE(ABORT, 'certificate cannot replace itself'); END");
                DB::statement("CREATE TRIGGER chk_certificate_reissue_not_self_update BEFORE UPDATE ON certificate_reissue_requests WHEN NEW.replacement_certificate_id IS NOT NULL AND NEW.replacement_certificate_id = NEW.original_certificate_id BEGIN SELECT RAISE(ABORT, 'certificate cannot replace itself'); END");
            }
        }

        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('certificate_reissue_requests')) {
            DB::statement("ALTER TABLE certificate_reissue_requests DROP CONSTRAINT IF EXISTS chk_certificate_reissue_requests_status");
            DB::statement("ALTER TABLE certificate_reissue_requests ADD CONSTRAINT chk_certificate_reissue_requests_status CHECK (status IN ('requested','under_review','approved','rejected','processing','issued','delivered','cancelled'))");
            DB::statement("ALTER TABLE certificate_reissue_requests DROP CONSTRAINT IF EXISTS chk_certificate_reissue_not_self_replacement");
            DB::statement("ALTER TABLE certificate_reissue_requests ADD CONSTRAINT chk_certificate_reissue_not_self_replacement CHECK (replacement_certificate_id IS NULL OR replacement_certificate_id != original_certificate_id)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_status");
            if (Schema::hasTable('certificate_reissue_requests')) {
                DB::statement("ALTER TABLE certificate_reissue_requests DROP CONSTRAINT IF EXISTS chk_certificate_reissue_requests_status");
                DB::statement("ALTER TABLE certificate_reissue_requests DROP CONSTRAINT IF EXISTS chk_certificate_reissue_not_self_replacement");
            }
        }

        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'chk_students_status_insert',
                'chk_students_status_update',
                'chk_certificate_reissue_status_insert',
                'chk_certificate_reissue_status_update',
                'chk_certificate_reissue_not_self_insert',
                'chk_certificate_reissue_not_self_update',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
    }
};
