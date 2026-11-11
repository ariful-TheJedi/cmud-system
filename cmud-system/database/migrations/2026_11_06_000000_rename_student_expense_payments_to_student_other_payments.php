<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('student_expense_payments', 'student_other_payments');
    }

    public function down(): void
    {
        Schema::rename('student_other_payments', 'student_expense_payments');
    }
};
