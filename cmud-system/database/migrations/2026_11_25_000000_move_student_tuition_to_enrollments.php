<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Preserve tuition metadata on its enrollment before removing the redundant table. */
    public function up(): void
    {
        $this->withSqliteSchemaChanges(fn () => $this->moveToEnrollments());
    }

    private function moveToEnrollments(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('tuition_number')->nullable()->unique();
            $table->foreignId('course_price_version_id')->nullable()->constrained('course_price_versions')->restrictOnDelete();
            $table->decimal('tuition_original_amount', 12, 2)->nullable();
            $table->decimal('tuition_waiver_amount', 12, 2)->default(0);
            $table->text('tuition_waiver_reason')->nullable();
            $table->foreignId('tuition_waiver_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('tuition_waiver_approved_at')->nullable();
            $table->date('tuition_issued_on')->nullable();
            $table->date('tuition_due_on')->nullable();
            $table->string('tuition_status')->nullable();
            $table->timestamp('tuition_cancelled_at')->nullable();
            $table->text('tuition_cancellation_reason')->nullable();
            $table->text('tuition_notes')->nullable();
            // Retain historical receipt hashing and the original IDs/timestamps for rollback.
            $table->unsignedBigInteger('tuition_legacy_id')->nullable()->unique();
            $table->timestamp('tuition_created_at')->nullable();
            $table->timestamp('tuition_updated_at')->nullable();
        });
        Schema::table('student_tuition_payments', function (Blueprint $table): void {
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
        });

        DB::table('student_tuitions')->orderBy('id')->chunkById(200, function ($tuitions): void {
            foreach ($tuitions as $tuition) {
                $values = ['tuition_legacy_id' => $tuition->id];
                foreach ($this->columns() as $old => $new) {
                    $values[$new] = $tuition->{$old};
                }
                DB::table('enrollments')->where('id', $tuition->enrollment_id)->update($values);
                DB::table('student_tuition_payments')->where('student_tuition_id', $tuition->id)
                    ->update(['enrollment_id' => $tuition->enrollment_id]);
            }
        });
        if (DB::table('student_tuition_payments')->whereNull('enrollment_id')->exists()) {
            throw new RuntimeException('Cannot migrate tuition payments with no owning enrollment.');
        }
        Schema::table('student_tuition_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('enrollment_id')->nullable(false)->change();
        });
        // Discover the historical custom FK name rather than assuming Laravel's default.
        foreach (Schema::getForeignKeys('student_tuition_payments') as $foreignKey) {
            if ($foreignKey['columns'] === ['student_tuition_id']) {
                Schema::table('student_tuition_payments', fn (Blueprint $table) => $table->dropForeign(
                    DB::getDriverName() === 'sqlite' ? ['student_tuition_id'] : $foreignKey['name'],
                ));
            }
        }
        Schema::table('student_tuition_payments', fn (Blueprint $table) => $table->dropColumn('student_tuition_id'));
        Schema::drop('student_tuitions');
    }

    /** Reconstruct the original obligations and payment links without losing new receipts. */
    public function down(): void
    {
        $this->withSqliteSchemaChanges(fn () => $this->restoreTuitionTable());
    }

    private function restoreTuitionTable(): void
    {
        Schema::create('student_tuitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('course_price_version_id')->nullable()->constrained('course_price_versions')->restrictOnDelete();
            $table->string('tuition_number')->unique();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->text('waiver_reason')->nullable();
            $table->foreignId('waiver_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('waiver_approved_at')->nullable();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('student_tuition_payments', fn (Blueprint $table) => $table->unsignedBigInteger('student_tuition_id')->nullable());
        $nextId = (int) DB::table('enrollments')->max('tuition_legacy_id');
        DB::table('enrollments')->whereNotNull('tuition_number')->orderBy('id')->chunkById(200, function ($enrollments) use (&$nextId): void {
            foreach ($enrollments as $enrollment) {
                $id = $enrollment->tuition_legacy_id ?? ++$nextId;
                $values = ['id' => $id, 'enrollment_id' => $enrollment->id];
                foreach ($this->columns() as $old => $new) {
                    $values[$old] = $enrollment->{$new};
                }
                DB::table('student_tuitions')->insert($values);
                DB::table('student_tuition_payments')->where('enrollment_id', $enrollment->id)->update(['student_tuition_id' => $id]);
            }
        });
        if (DB::table('student_tuition_payments')->whereNull('student_tuition_id')->exists()) {
            throw new RuntimeException('Cannot roll back a payment without enrollment tuition details.');
        }
        Schema::table('student_tuition_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('student_tuition_id')->nullable(false)->change();
            $table->foreign('student_tuition_id', 'fk_stp_tuition_restored')->references('id')->on('student_tuitions')->restrictOnDelete();
            $table->dropForeign(['enrollment_id']);
        });
        Schema::table('student_tuition_payments', fn (Blueprint $table) => $table->dropColumn('enrollment_id'));
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropForeign(['course_price_version_id']);
            $table->dropForeign(['tuition_waiver_approved_by']);
            $table->dropUnique(['tuition_number']);
            $table->dropUnique(['tuition_legacy_id']);
        });
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn([
            ...array_values($this->columns()), 'tuition_legacy_id',
        ]));
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('student_tuitions', 'id'), COALESCE((SELECT MAX(id) FROM student_tuitions), 1), EXISTS(SELECT 1 FROM student_tuitions))");
        }
    }

    /** SQLite rebuilds altered tables; preserve their existing validation triggers. */
    private function withSqliteSchemaChanges(callable $callback): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $callback();

            return;
        }
        $triggers = DB::table('sqlite_master')->where('type', 'trigger')
            ->whereIn('tbl_name', ['enrollments', 'student_tuition_payments'])->get(['name', 'sql']);
        Schema::withoutForeignKeyConstraints(function () use ($callback, $triggers): void {
            $callback();
            foreach ($triggers as $trigger) {
                if (! DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger->name)->exists()) {
                    DB::statement($trigger->sql);
                }
            }
        });
    }

    /** @return array<string, string> */
    private function columns(): array
    {
        return [
            'tuition_number' => 'tuition_number', 'course_price_version_id' => 'course_price_version_id',
            'original_amount' => 'tuition_original_amount', 'waiver_amount' => 'tuition_waiver_amount',
            'waiver_reason' => 'tuition_waiver_reason', 'waiver_approved_by' => 'tuition_waiver_approved_by',
            'waiver_approved_at' => 'tuition_waiver_approved_at', 'issued_on' => 'tuition_issued_on',
            'due_on' => 'tuition_due_on', 'status' => 'tuition_status',
            'cancelled_at' => 'tuition_cancelled_at', 'cancellation_reason' => 'tuition_cancellation_reason',
            'notes' => 'tuition_notes', 'created_at' => 'tuition_created_at', 'updated_at' => 'tuition_updated_at',
        ];
    }
};
