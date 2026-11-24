<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add a private-storage path for each staff member's NID document. */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->string('nid_file_path')->nullable()->after('nid_number');
        });
    }

    /** Remove the NID document path column when rolling back this migration. */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropColumn('nid_file_path');
        });
    }
};
