<?php

use Database\Seeders\Development\CoreDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds schema-supported core scenario records with valid foreign keys', function (): void {
    $this->seed(CoreDevelopmentSeeder::class);

    expect(DB::table('institutes')->where('code', 'CMUD-DEV-CORE')->count())->toBe(1)
        ->and(DB::table('students')->where('student_code', 'DEV-CORE-STU-001')->count())->toBe(1)
        ->and(DB::table('enrollments')->where('student_id', DB::table('students')->where('student_code', 'DEV-CORE-STU-001')->value('id'))->count())->toBe(0)
        ->and(DB::table('enrollments')->where('student_id', DB::table('students')->where('student_code', 'DEV-CORE-STU-003')->value('id'))->count())->toBe(2)
        ->and(DB::table('student_stage_records')->where('notes', 'like', '%CMUD-CORE:SD-ACA-004-%')->count())->toBe(3)
        ->and(DB::table('exam_records')->where('exam_name', 'Core Demonstration Exam')->count())->toBe(2)
        ->and(DB::table('certificates')->where('notes', 'like', '%CMUD-CORE:SD-ACA-006-%')->count())->toBe(2)
        ->and(DB::table('student_group_memberships')->where('notes', 'like', '%CMUD-CORE:SD-ACA-007-%')->count())->toBe(2)
        ->and(DB::table('student_tuition_payments')->count())->toBe(7)
        ->and(DB::table('student_other_payments')->where('payment_number', 'DEVCORE-SEXP-PAY-001')->count())->toBe(1)
        ->and(DB::table('institute_expense_payments')->where('payment_number', 'DEVCORE-IEXP-PAY-001')->count())->toBe(1)
        ->and(DB::table('institute_other_income_bill_payment')->where('payment_number', 'DEVCORE-INCOME-PAY-001')->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('notes', 'like', '%CMUD-CORE:SD-INS-%')->count())->toBe(3)
        ->and(DB::table('audit_logs')->count())->toBe(0)
        ->and(DB::table('sessions')->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);

    $expensePayment = DB::table('student_other_payments')
        ->where('payment_number', 'DEVCORE-SEXP-PAY-001')
        ->first();

    expect(DB::table('student_expenses')->where('id', $expensePayment->student_expense_id)->exists())->toBeTrue()
        ->and(DB::table('payment_methods')->where('id', $expensePayment->payment_method_id)->exists())->toBeTrue()
        ->and(DB::table('financial_accounts')->where('id', $expensePayment->financial_account_id)->exists())->toBeTrue()
        ->and(DB::table('users')->where('id', $expensePayment->recorded_by)->exists())->toBeTrue();

    expect(DB::select('PRAGMA foreign_key_check'))->toBeEmpty();
});

it('keeps every application table snapshot stable when the core seeder is rerun', function (): void {
    $this->seed(CoreDevelopmentSeeder::class);
    $firstSnapshot = coreApplicationTableSnapshot();

    $this->seed(CoreDevelopmentSeeder::class);
    $secondSnapshot = coreApplicationTableSnapshot();

    expect($secondSnapshot)->toBe($firstSnapshot);
});

/** @return array<string, list<array<string, mixed>>> */
function coreApplicationTableSnapshot(): array
{
    $tables = collect(Schema::getTableListing(null, false))
        ->reject(fn (string $table): bool => $table === 'migrations' || str_starts_with($table, 'sqlite_'))
        ->sort()
        ->values();

    $snapshot = [];
    foreach ($tables as $table) {
        $columns = Schema::getColumnListing($table);
        $orderColumns = in_array('id', $columns, true)
            ? ['id']
            : array_slice($columns, 0, 2);

        $query = DB::table($table);
        foreach ($orderColumns as $column) {
            $query->orderBy($column);
        }

        $snapshot[$table] = $query->get()->map(fn (object $row): array => (array) $row)->all();
    }

    return $snapshot;
}
