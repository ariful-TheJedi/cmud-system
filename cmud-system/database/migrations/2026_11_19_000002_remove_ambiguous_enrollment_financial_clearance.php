<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('enrollments', 'financial_clearance_status')) {
            return;
        }

        $unresolved = DB::table('enrollments')
            ->whereIn('financial_clearance_status', ['cleared', 'under_review'])
            ->count();

        if ($unresolved > 0) {
            throw new RuntimeException(
                "Cannot remove {$unresolved} enrollment-level financial clearance value(s). "
                .'Semester-specific clearance must be mapped to stage/expense obligations first.',
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS chk_enrollments_financial_clearance_status");
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP TRIGGER IF EXISTS chk_enrollment_financial_clearance_insert");
            DB::statement("DROP TRIGGER IF EXISTS chk_enrollment_financial_clearance_update");
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('financial_clearance_status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('enrollments', 'financial_clearance_status')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('financial_clearance_status')
                ->default('not_cleared')
                ->nullable(false);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE enrollments ADD CONSTRAINT chk_enrollments_financial_clearance_status CHECK (financial_clearance_status IN ('not_cleared','cleared','under_review'))");
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER chk_enrollment_financial_clearance_insert BEFORE INSERT ON enrollments WHEN NEW.financial_clearance_status NOT IN ('not_cleared','cleared','under_review') BEGIN SELECT RAISE(ABORT, 'invalid financial clearance status'); END");
            DB::statement("CREATE TRIGGER chk_enrollment_financial_clearance_update BEFORE UPDATE ON enrollments WHEN NEW.financial_clearance_status NOT IN ('not_cleared','cleared','under_review') BEGIN SELECT RAISE(ABORT, 'invalid financial clearance status'); END");
        }
    }
};
