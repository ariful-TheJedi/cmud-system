<?php

namespace Tests\Feature\Database;

use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use App\Modules\Finance\Services\StudentFinance\StudentFinancialOverviewService;
use App\Modules\Finance\Services\StudentFinance\StudentTuitionPaymentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnrollmentTuitionMigrationTest extends TestCase
{
    use DatabaseMigrations;

    /** Schema tests own their rollback; older unrelated migration downs are outside this test. */
    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    private User $user;

    private PaymentMethod $paymentMethod;

    private FinancialAccount $financialAccount;

    /** Independent fixtures keep schema changes outside a wrapping test transaction. */
    private function enrollments(): array
    {
        $institute = Institute::query()->create(['code' => 'MIG', 'name' => 'Migration Institute', 'phone' => '01700000000', 'email' => 'migration@example.test', 'address' => 'Test address']);
        $branch = Branch::query()->create(['institute_id' => $institute->id, 'code' => 'MIG', 'name' => 'Migration Branch', 'phone' => '01700000000', 'address' => 'Test address']);
        $student = Student::query()->create(['institute_id' => $institute->id, 'admission_branch_id' => $branch->id,
            'student_code' => 'MIG-STUDENT', 'full_name' => 'Migration Student', 'phone' => '01700000000',
            'admission_date' => '2025-01-01', 'present_address' => 'Test address', 'qualification' => 'MBBS', 'status' => 'active']);
        $course = Course::query()->create(['institute_id' => $institute->id, 'code' => 'MIG', 'name' => 'Migration Course',
            'duration_months' => 12, 'completion_period_months' => 15, 'course_mode' => 'offline']);
        $this->user = User::query()->create(['name' => 'Migration Admin', 'email' => 'migration@example.test',
            'password' => 'test-password', 'is_active' => true, 'is_super_admin' => true]);
        $this->paymentMethod = PaymentMethod::query()->create(['institute_id' => $institute->id, 'code' => 'cash', 'name' => 'Cash', 'is_active' => true]);
        $this->financialAccount = FinancialAccount::query()->create(['institute_id' => $institute->id, 'branch_id' => $branch->id,
            'code' => 'MIG-CASH', 'name' => 'Migration Cash', 'account_type' => 'cash', 'opening_balance_as_of' => '2025-01-01', 'is_active' => true]);

        return array_map(fn (int $number) => Enrollment::query()->create([
            'enrollment_code' => 'MIG-ENR-'.$number, 'student_id' => $student->id, 'course_id' => $course->id,
            'branch_id' => $branch->id, 'enrollment_date' => '2025-01-01', 'expected_completion_on' => '2026-01-01',
            'completion_deadline_on' => '2026-04-01', 'status' => 'ongoing',
        ]), [1, 2]);
    }

    public function test_migration_preserves_tuition_payments_balances_and_legacy_retries_and_can_roll_back(): void
    {
        [$enrollment, $unbilled] = $this->enrollments();
        $course = $enrollment->course;
        $migration = require database_path('migrations/2026_11_25_000000_move_student_tuition_to_enrollments.php');
        $migration->down();

        $legacyId = 101;
        $priceId = DB::table('course_price_versions')->insertGetId([
            'course_id' => $course->id, 'version_number' => 1, 'amount' => '1000.00',
            'effective_from' => '2025-01-01', 'status' => 'approved',
        ]);
        $oldTuition = [
            'id' => $legacyId, 'enrollment_id' => $enrollment->id,
            'course_price_version_id' => $priceId, 'tuition_number' => 'MIGRATION-TUITION',
            'original_amount' => '1000.00', 'waiver_amount' => '100.00',
            'waiver_reason' => 'Approved migration fixture', 'waiver_approved_by' => $this->user->id,
            'waiver_approved_at' => '2025-01-02 09:00:00', 'issued_on' => '2025-01-01',
            'due_on' => '2099-01-01', 'status' => 'issued', 'cancelled_at' => null,
            'cancellation_reason' => null, 'notes' => 'Preserve this tuition note',
            'created_at' => '2025-01-01 08:00:00', 'updated_at' => '2025-01-02 09:00:00',
        ];
        DB::table('student_tuitions')->insert($oldTuition);
        $number = 'STP-'.strtoupper(substr(hash('sha256', 'tuition|'.$legacyId.'|migration-retry'), 0, 26));
        $paymentId = DB::table('student_tuition_payments')->insertGetId([
            'student_tuition_id' => $legacyId, 'payment_number' => $number,
            'payment_method_id' => $this->paymentMethod->id, 'financial_account_id' => $this->financialAccount->id,
            'amount' => '250.00', 'paid_at' => '2025-02-01 10:00:00', 'status' => 'confirmed',
            'recorded_by' => $this->user->id, 'notes' => 'Preserve payment history',
        ]);

        $migration->up();
        $enrollment->refresh();
        $this->assertFalse(Schema::hasTable('student_tuitions'));
        $this->assertFalse(Schema::hasColumn('student_tuition_payments', 'student_tuition_id'));
        $this->assertSame('1000.00', $enrollment->tuition_original_amount);
        $this->assertSame('100.00', $enrollment->tuition_waiver_amount);
        $this->assertSame('ongoing', $enrollment->getRawOriginal('status'));
        $this->assertSame($enrollment->id, StudentTuitionPayment::findOrFail($paymentId)->enrollment->id);
        $summary = app(StudentFinancialOverviewService::class)->forEnrollment($enrollment);
        $this->assertSame('650.00', $summary['outstanding_due']);
        $this->assertFalse($unbilled->fresh()->hasTuition());

        $replay = app(StudentTuitionPaymentService::class)->recordCashPayment($enrollment, [
            'amount' => '250.00', 'financial_account_id' => $this->financialAccount->id,
            'idempotency_key' => 'migration-retry', 'notes' => 'Preserve payment history',
        ], $this->user);
        $this->assertTrue($replay['idempotent_replay']);
        $this->assertSame(1, StudentTuitionPayment::count());

        $migration->down();
        $restored = (array) DB::table('student_tuitions')->where('id', $legacyId)->first();
        foreach ($oldTuition as $column => $value) {
            $this->assertEquals($value, $restored[$column], 'Lost tuition field: '.$column);
        }
        $this->assertEquals($legacyId, DB::table('student_tuition_payments')->where('id', $paymentId)->value('student_tuition_id'));
        $migration->up();
        $this->assertSame('650.00', app(StudentFinancialOverviewService::class)->forEnrollment($enrollment->fresh())['outstanding_due']);
    }

    public function test_new_enrollment_tuition_receipts_do_not_collide_with_legacy_tuition_ids(): void
    {
        [$first, $second] = $this->enrollments();
        foreach ([$first, $second] as $enrollment) {
            $enrollment->update(['tuition_number' => 'TUITION-'.$enrollment->id,
                'tuition_original_amount' => '1000.00', 'tuition_issued_on' => '2025-01-01', 'tuition_status' => 'issued']);
        }
        DB::table('enrollments')->where('id', $second->id)->update(['tuition_legacy_id' => $first->id]);
        $service = app(StudentTuitionPaymentService::class);
        $data = ['amount' => '100.00', 'financial_account_id' => $this->financialAccount->id, 'idempotency_key' => 'same-key'];
        $service->recordCashPayment($first, $data, $this->user);
        $service->recordCashPayment($second->fresh(), $data, $this->user);
        $this->assertSame(2, StudentTuitionPayment::count());
        $this->assertSame(2, StudentTuitionPayment::distinct()->count('enrollment_id'));
    }
}
