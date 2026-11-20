<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_reissue_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('original_certificate_id')
                ->constrained('certificates')
                ->restrictOnDelete();
            $table->foreignId('replacement_certificate_id')
                ->nullable()
                ->constrained('certificates')
                ->restrictOnDelete();
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('decided_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('requested_at');
            $table->timestamp('decision_at')->nullable();
            $table->string('status');
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['original_certificate_id', 'status']);
            $table->unique('replacement_certificate_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE certificate_reissue_requests ADD CONSTRAINT chk_certificate_reissue_requests_status CHECK (status IN ('requested','under_review','approved','rejected','processing','issued','delivered','cancelled'))");
            DB::statement("ALTER TABLE certificate_reissue_requests ADD CONSTRAINT chk_certificate_reissue_not_self_replacement CHECK (replacement_certificate_id IS NULL OR replacement_certificate_id != original_certificate_id)");
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER chk_certificate_reissue_status_insert BEFORE INSERT ON certificate_reissue_requests BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('requested','under_review','approved','rejected','processing','issued','delivered','cancelled') THEN RAISE(ABORT, 'invalid status') END; END");
            DB::statement("CREATE TRIGGER chk_certificate_reissue_status_update BEFORE UPDATE ON certificate_reissue_requests BEGIN SELECT CASE WHEN NEW.status IS NULL OR NEW.status NOT IN ('requested','under_review','approved','rejected','processing','issued','delivered','cancelled') THEN RAISE(ABORT, 'invalid status') END; END");
            DB::statement("CREATE TRIGGER chk_certificate_reissue_not_self_insert BEFORE INSERT ON certificate_reissue_requests WHEN NEW.replacement_certificate_id IS NOT NULL AND NEW.replacement_certificate_id = NEW.original_certificate_id BEGIN SELECT RAISE(ABORT, 'certificate cannot replace itself'); END");
            DB::statement("CREATE TRIGGER chk_certificate_reissue_not_self_update BEFORE UPDATE ON certificate_reissue_requests WHEN NEW.replacement_certificate_id IS NOT NULL AND NEW.replacement_certificate_id = NEW.original_certificate_id BEGIN SELECT RAISE(ABORT, 'certificate cannot replace itself'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE certificate_reissue_requests DROP CONSTRAINT IF EXISTS chk_certificate_reissue_requests_status");
            DB::statement("ALTER TABLE certificate_reissue_requests DROP CONSTRAINT IF EXISTS chk_certificate_reissue_not_self_replacement");
        }
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP TRIGGER IF EXISTS chk_certificate_reissue_status_insert");
            DB::statement("DROP TRIGGER IF EXISTS chk_certificate_reissue_status_update");
            DB::statement("DROP TRIGGER IF EXISTS chk_certificate_reissue_not_self_insert");
            DB::statement("DROP TRIGGER IF EXISTS chk_certificate_reissue_not_self_update");
        }
        Schema::dropIfExists('certificate_reissue_requests');
    }
};
