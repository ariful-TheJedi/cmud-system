<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
            $table->string('designation', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_super_admin')->default(false);
            $table->foreignId('permission_group_id')->nullable()
                ->constrained('permission_groups')
                ->restrictOnDelete();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('label', 150);
            $table->foreignId('permission_group_id')
                ->constrained('permission_groups')
                ->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 100);
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
            $table->timestamp('updated_at')->nullable();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                'CREATE TRIGGER audit_logs_subject_pair_insert '
                .'BEFORE INSERT ON audit_logs '
                .'WHEN NOT ((NEW.subject_type IS NULL AND NEW.subject_id IS NULL) '
                .'OR (NEW.subject_type IS NOT NULL AND NEW.subject_id IS NOT NULL)) '
                .'BEGIN SELECT RAISE(ABORT, "subject_type and subject_id must both be present or both be NULL"); END'
            );
            DB::unprepared(
                'CREATE TRIGGER audit_logs_subject_pair_update '
                .'BEFORE UPDATE ON audit_logs '
                .'WHEN NOT ((NEW.subject_type IS NULL AND NEW.subject_id IS NULL) '
                .'OR (NEW.subject_type IS NOT NULL AND NEW.subject_id IS NOT NULL)) '
                .'BEGIN SELECT RAISE(ABORT, "subject_type and subject_id must both be present or both be NULL"); END'
            );
        } else {
            DB::statement(
                'ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_subject_pair_check '
                .'CHECK ((subject_type IS NULL AND subject_id IS NULL) '
                .'OR (subject_type IS NOT NULL AND subject_id IS NOT NULL))'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['permission_group_id']);
        });
        Schema::dropIfExists('permission_groups');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'designation', 'is_active', 'is_super_admin', 'permission_group_id']);
        });
    }
};
