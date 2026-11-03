<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the declared User Management & Access Control foreign keys from relation.txt.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Remove the declared foreign keys.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
        });

    }
};
