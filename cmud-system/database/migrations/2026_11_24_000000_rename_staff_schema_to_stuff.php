<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Rename the employee directory schema and all foreign-key columns that point to it. */
    public function up(): void
    {
        Schema::rename('staff', 'stuff');
        Schema::rename('staff_assignments', 'stuff_assignments');

        Schema::table('stuff', function (Blueprint $table): void {
            $table->renameColumn('staff_code', 'stuff_code');
        });

        Schema::table('stuff_assignments', function (Blueprint $table): void {
            $table->renameColumn('staff_id', 'stuff_id');
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->renameColumn('staff_id', 'stuff_id');
        });

        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->renameColumn('staff_id', 'stuff_id');
        });
    }

    /** Restore the legacy staff table and foreign-key column names on rollback. */
    public function down(): void
    {
        Schema::table('institute_expenses', function (Blueprint $table): void {
            $table->renameColumn('stuff_id', 'staff_id');
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->renameColumn('stuff_id', 'staff_id');
        });

        Schema::table('stuff_assignments', function (Blueprint $table): void {
            $table->renameColumn('stuff_id', 'staff_id');
        });

        Schema::table('stuff', function (Blueprint $table): void {
            $table->renameColumn('stuff_code', 'staff_code');
        });

        Schema::rename('stuff_assignments', 'staff_assignments');
        Schema::rename('stuff', 'staff');
    }
};
