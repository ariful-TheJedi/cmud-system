<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $inactiveCount = DB::table('students')->where('status', 'inactive')->count();
        $archivedCount = DB::table('students')->where('status', 'archived')->count();

        if ($archivedCount > 0) {
            throw new RuntimeException(
                "Cannot safely migrate {$archivedCount} student record(s) with status 'archived'. "
                .'No approved mapping to deactivated or suspended was provided.',
            );
        }
        $completedCount = DB::table('students')->where('status', 'completed')->count();
        if ($completedCount > 0) {
            throw new RuntimeException(
                "Cannot safely migrate {$completedCount} student record(s) with status 'completed'. "
                .'Course completion must not determine institution-level student status.',
            );
        }

        if ($inactiveCount > 0) {
            DB::table('students')->where('status', 'inactive')->update(['status' => 'deactivated']);
        }

        DB::table('enrollments')->where('status', 'active')->update(['status' => 'ongoing']);
        DB::table('student_stage_records')->where('status', 'in_progress')->update(['status' => 'ongoing']);

        Schema::table('exam_records', function (Blueprint $table): void {
            $table->string('attempt_status')->nullable()->after('attempt_number');
        });
        DB::table('exam_records')->update(['attempt_status' => 'appeared']);

        $duplicateAttempts = DB::table('exam_records')
            ->select('student_stage_record_id', 'exam_name', 'attempt_number')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('student_stage_record_id', 'exam_name', 'attempt_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateAttempts) {
            throw new RuntimeException(
                'Cannot add examination attempt uniqueness: duplicate stage, exam name, '
                .'and attempt-number rows already exist.',
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE exam_records ALTER COLUMN attempt_status SET NOT NULL");

            $this->addPostgresCheck('students', 'chk_students_status', ['registered', 'active', 'deactivated', 'suspended']);
            $this->addPostgresCheck('enrollments', 'chk_enrollments_status', ['enrolled', 'ongoing', 'on_hold', 'completed', 'discontinued', 'cancelled']);
            $this->addPostgresCheck('student_stage_records', 'chk_student_stage_records_status', ['pending', 'ongoing', 'on_hold', 'completed', 'failed', 'incomplete']);
            $this->addPostgresCheck('exam_records', 'chk_exam_records_attempt_status', ['scheduled', 'appeared', 'absent', 'cancelled'], 'attempt_status');
            $this->addPostgresCheck('exam_records', 'chk_exam_records_result_status', ['pending', 'passed', 'failed', 'incomplete'], 'result_status');
            $this->addPostgresCheck('certificates', 'chk_certificates_status', ['not_eligible', 'eligibility_pending', 'pending', 'processing', 'ready', 'issued', 'delivered']);
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('exam_records', function (Blueprint $table): void {
                $table->string('attempt_status')->nullable(false)->change();
            });

            $this->addSqliteTrigger('students', 'chk_students_status_insert', 'BEFORE INSERT');
            $this->addSqliteTrigger('students', 'chk_students_status_update', 'BEFORE UPDATE');
            $this->addSqliteTrigger('enrollments', 'chk_enrollments_status_insert', 'BEFORE INSERT');
            $this->addSqliteTrigger('enrollments', 'chk_enrollments_status_update', 'BEFORE UPDATE');
            $this->addSqliteTrigger('student_stage_records', 'chk_student_stage_records_status_insert', 'BEFORE INSERT');
            $this->addSqliteTrigger('student_stage_records', 'chk_student_stage_records_status_update', 'BEFORE UPDATE');
            $this->addSqliteTrigger('exam_records', 'chk_exam_records_status_insert', 'BEFORE INSERT');
            $this->addSqliteTrigger('exam_records', 'chk_exam_records_status_update', 'BEFORE UPDATE');
            $this->addSqliteTrigger('exam_records', 'chk_exam_records_result_insert', 'BEFORE INSERT', 'result_status');
            $this->addSqliteTrigger('exam_records', 'chk_exam_records_result_update', 'BEFORE UPDATE', 'result_status');
            $this->addSqliteTrigger('certificates', 'chk_certificates_status_insert', 'BEFORE INSERT');
            $this->addSqliteTrigger('certificates', 'chk_certificates_status_update', 'BEFORE UPDATE');
        }

        Schema::table('exam_records', function (Blueprint $table): void {
            $table->unique(
                ['student_stage_record_id', 'exam_name', 'attempt_number'],
                'uq_exam_attempt',
            );
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'chk_certificates_status',
                'chk_exam_records_result_status',
                'chk_exam_records_attempt_status',
                'chk_student_stage_records_status',
                'chk_enrollments_status',
                'chk_students_status',
            ] as $constraint) {
                $table = match ($constraint) {
                    'chk_certificates_status' => 'certificates',
                    'chk_exam_records_result_status', 'chk_exam_records_attempt_status' => 'exam_records',
                    'chk_student_stage_records_status' => 'student_stage_records',
                    'chk_enrollments_status' => 'enrollments',
                    default => 'students',
                };
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        };

        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'chk_students_status_insert', 'chk_students_status_update',
                'chk_enrollments_status_insert', 'chk_enrollments_status_update',
                'chk_student_stage_records_status_insert', 'chk_student_stage_records_status_update',
                'chk_exam_records_status_insert', 'chk_exam_records_status_update',
                'chk_exam_records_result_insert', 'chk_exam_records_result_update',
                'chk_certificates_status_insert', 'chk_certificates_status_update',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }

        Schema::table('exam_records', function (Blueprint $table): void {
            $table->dropUnique('uq_exam_attempt');
            $table->dropColumn('attempt_status');
        });
    }

    private function addPostgresCheck(string $table, string $name, array $values, ?string $column = null): void
    {
        $column ??= 'status';
        $allowed = implode(', ', array_map(fn (string $value): string => "'".$value."'", $values));
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$column} IN ({$allowed}))");
    }

    private function addSqliteTrigger(
        string $table,
        string $name,
        string $timing,
        ?string $columnOverride = null,
    ): void {
        $allowed = match ($table) {
            'students' => "registered','active','deactivated','suspended",
            'enrollments' => "enrolled','ongoing','on_hold','completed','discontinued','cancelled",
            'student_stage_records' => "pending','ongoing','on_hold','completed','failed','incomplete",
            'exam_records' => str_contains($name, 'result')
                ? "pending','passed','failed','incomplete"
                : "scheduled','appeared','absent','cancelled",
            'certificates' => "not_eligible','eligibility_pending','pending','processing','ready','issued','delivered",
        };
        $allowed = "'".str_replace("','", "','", $allowed)."'";
        $column = $columnOverride
            ?? ($table === 'exam_records' ? 'attempt_status' : 'status');
        $when = $timing === 'BEFORE UPDATE' ? 'BEFORE UPDATE ON '.$table : 'BEFORE INSERT ON '.$table;
        DB::statement("CREATE TRIGGER {$name} {$when} BEGIN SELECT CASE WHEN NEW.{$column} IS NULL OR NEW.{$column} NOT IN ({$allowed}) THEN RAISE(ABORT, 'invalid status') END; END");
    }
};
