<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the user's single branch scope; null remains valid for super users and existing accounts. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('is_super_admin')
                ->constrained('branches')
                ->restrictOnDelete();
        });
    }

    /** Remove the branch scope when rolling back this schema change. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
