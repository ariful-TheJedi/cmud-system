<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add an explicit main-branch marker and initialize existing institutes safely. */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('is_main_branch')->default(false);
        });

        // Preserve a usable main office for existing institutes by marking their first active branch.
        $mainBranchIds = DB::table('branches')
            ->where('is_active', true)
            ->selectRaw('MIN(id) as id')
            ->groupBy('institute_id')
            ->pluck('id');

        if ($mainBranchIds->isNotEmpty()) {
            DB::table('branches')->whereIn('id', $mainBranchIds)->update(['is_main_branch' => true]);
        }
    }

    /** Remove the main-branch marker from the branch schema. */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('is_main_branch');
        });
    }
};
