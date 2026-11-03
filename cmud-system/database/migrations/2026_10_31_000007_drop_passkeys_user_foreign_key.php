<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('passkeys')) {
            return;
        }

        foreach (Schema::getForeignKeys('passkeys') as $foreignKey) {
            if (in_array('user_id', $foreignKey['columns'], true)) {
                Schema::table('passkeys', function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }
    }

    /**
     * Keep the foreign key removed on rollback as relationships are being deferred.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
