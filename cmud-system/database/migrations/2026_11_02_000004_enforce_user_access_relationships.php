<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $orphanedSession = DB::table('sessions as user_sessions')
            ->leftJoin('users', 'users.id', '=', 'user_sessions.user_id')
            ->whereNotNull('user_sessions.user_id')
            ->whereNull('users.id')
            ->exists();

        if ($orphanedSession) {
            throw new RuntimeException(
                'Cannot add the session user foreign key while sessions for missing users exist.'
            );
        }

        $orphanedResetToken = DB::table('password_reset_tokens as tokens')
            ->leftJoin('users', 'users.email', '=', 'tokens.email')
            ->whereNull('users.id')
            ->exists();

        if ($orphanedResetToken) {
            throw new RuntimeException(
                'Cannot add the password reset token foreign key while tokens for missing users exist.'
            );
        }

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->foreign('email')
                ->references('email')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropForeign(['email']);
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
    }
};
