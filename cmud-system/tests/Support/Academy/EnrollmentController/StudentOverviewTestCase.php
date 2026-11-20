<?php

namespace Tests\Support\Academy\EnrollmentController;

use App\Models\Academy\Batch;
use App\Models\Academy\Certificate;
use App\Models\Academy\CertificateTemplate;
use App\Models\Academy\Course;
use App\Models\Academy\CourseStage;
use App\Models\Academy\Enrollment;
use App\Models\Academy\ExamRecord;
use App\Models\Academy\Student;
use App\Models\Academy\StudentStageRecord;
use App\Models\Finance\FinancialAccount;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\StudentExpense;
use App\Models\Finance\StudentExpensePayment;
use App\Models\Finance\StudentExpensePriceVersion;
use App\Models\Finance\StudentExpenseType;
use App\Models\Finance\StudentTuition;
use App\Models\Finance\StudentTuitionPayment;
use App\Models\Institute\Branch;
use App\Models\Institute\Institute;
use App\Models\Security\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class StudentOverviewTestCase extends TestCase
{
    use RefreshDatabase;

    protected Institute $institute;

    protected Branch $branch;

    protected Student $student;

    protected User $user;

    protected PaymentMethod $paymentMethod;

    protected FinancialAccount $financialAccount;

    protected StudentExpenseType $expenseType;

    protected StudentExpensePriceVersion $expensePrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::query()->create([
            'code' => 'OVERVIEW-INSTITUTE',
            'name' => 'Overview Test Institute',
            'phone' => '01700000041',
            'email' => 'overview-institute@example.test',
            'address' => 'Test institute address',
            'is_active' => true,
        ]);
        $this->branch = Branch::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'OVERVIEW-BRANCH',
            'name' => 'Overview Test Branch',
            'phone' => '01700000042',
            'address' => 'Test branch address',
            'is_active' => true,
        ]);
        $this->student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'admission_branch_id' => $this->branch->id,
            'student_code' => 'STU-OVERVIEW-001',
            'admission_date' => '2025-01-01',
            'full_name' => 'Overview Test Student',
            'phone' => '01700000043',
            'status' => 'active',
        ]);
        $this->user = User::query()->create([
            'name' => 'Overview Test User',
            'email' => 'overview-user@example.test',
            'password' => 'test-password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $this->paymentMethod = PaymentMethod::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'OVERVIEW-CASH',
            'name' => 'Cash',
            'is_active' => true,
        ]);
        $this->financialAccount = FinancialAccount::query()->create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'code' => 'OVERVIEW-CASH-ACCOUNT',
            'name' => 'Cash Account',
            'account_type' => 'cash',
            'currency_code' => 'BDT',
            'opening_balance_as_of' => '2025-01-01 00:00:00',
            'is_active' => true,
        ]);
        $this->expenseType = StudentExpenseType::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'OVERVIEW-EXPENSE',
            'name' => 'Student Materials',
            'is_active' => true,
        ]);
        $this->expensePrice = StudentExpensePriceVersion::query()->create([
            'student_expense_type_id' => $this->expenseType->id,
            'branch_id' => $this->branch->id,
            'version_number' => 1,
            'amount' => '500.00',
            'effective_from' => '2025-01-01',
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'approved_at' => '2025-01-01 00:00:00',
        ]);
    }

    protected function createCourse(string $suffix): Course
    {
        return Course::query()->create([
            'institute_id' => $this->institute->id,
            'code' => 'OVERVIEW-COURSE-'.$suffix,
            'name' => 'Overview Course '.$suffix,
            'duration_months' => 12,
            'completion_period_months' => 15,
            'is_active' => true,
        ]);
    }

    protected function createBatch(Course $course, string $suffix): Batch
    {
        return Batch::query()->create([
            'course_id' => $course->id,
            'branch_id' => $this->branch->id,
            'code' => 'OVERVIEW-BATCH-'.$suffix,
            'name' => 'Overview Batch '.$suffix,
            'starts_on' => '2025-02-01',
            'expected_ends_on' => '2026-02-01',
            'status' => 'active',
        ]);
    }

    protected function createEnrollment(
        Course $course,
        Batch $batch,
        string $suffix,
        string $status = 'ongoing',
    ): Enrollment {
        return $this->student->enrollments()->create([
            'enrollment_code' => 'ENR-OVERVIEW-'.$suffix,
            'course_id' => $course->id,
            'branch_id' => $this->branch->id,
            'batch_id' => $batch->id,
            'enrollment_date' => '2025-01-01',
            'starts_on' => '2025-02-01',
            'expected_completion_on' => '2026-05-01',
            'completion_deadline_on' => '2026-08-01',
            'status' => $status,
            'is_active' => in_array($status, ['enrolled', 'ongoing', 'on_hold'], true),
        ]);
    }

    protected function createCourseStage(Course $course, string $suffix, int $sequence): CourseStage
    {
        return CourseStage::query()->create([
            'course_id' => $course->id,
            'code' => 'OVERVIEW-STAGE-'.$suffix,
            'name' => 'Semester '.$sequence,
            'sequence_number' => $sequence,
            'duration_months' => 6,
            'is_active' => true,
        ]);
    }

    protected function createStageRecord(
        Enrollment $enrollment,
        CourseStage $stage,
        Batch $batch,
        string $suffix,
        string $status,
    ): StudentStageRecord {
        return StudentStageRecord::query()->create([
            'enrollment_id' => $enrollment->id,
            'course_stage_id' => $stage->id,
            'batch_id' => $batch->id,
            'started_on' => '2025-02-01',
            'expected_completion_on' => '2025-08-01',
            'completed_on' => $status === 'completed' ? '2025-08-01' : null,
            'status' => $status,
            'notes' => $suffix,
        ]);
    }

    protected function createExam(
        StudentStageRecord $stageRecord,
        string $name,
        int $attempt,
        string $attemptStatus,
        string $resultStatus,
    ): ExamRecord {
        return ExamRecord::query()->create([
            'student_stage_record_id' => $stageRecord->id,
            'exam_name' => $name,
            'attempt_number' => $attempt,
            'exam_date' => '2025-09-01',
            'attempt_status' => $attemptStatus,
            'result_status' => $resultStatus,
            'total_marks' => '100.00',
            'obtained_marks' => $resultStatus === 'passed' ? '80.00' : null,
        ]);
    }

    protected function createCertificateTemplate(Course $course, string $suffix): CertificateTemplate
    {
        return CertificateTemplate::query()->create([
            'institute_id' => $this->institute->id,
            'course_id' => $course->id,
            'name' => 'Certificate '.$suffix,
            'version_number' => 1,
            'template_file_path' => 'certificates/template-'.$suffix.'.pdf',
            'is_active' => true,
        ]);
    }

    protected function createCertificate(
        Enrollment $enrollment,
        CertificateTemplate $template,
        string $suffix,
        string $status,
    ): Certificate {
        return Certificate::query()->create([
            'enrollment_id' => $enrollment->id,
            'certificate_template_id' => $template->id,
            'certificate_number' => 'CERT-OVERVIEW-'.$suffix,
            'recipient_name' => $this->student->full_name,
            'course_title' => $template->course?->name ?? 'Overview Course',
            'issued_on' => $status === 'issued' || $status === 'delivered' ? '2025-10-01' : null,
            'certificate_file_path' => 'certificates/'.$suffix.'.pdf',
            'delivered_at' => $status === 'delivered' ? '2025-10-02 12:00:00' : null,
            'status' => $status,
        ]);
    }

    protected function createTuition(
        Enrollment $enrollment,
        string $suffix,
        string $amount = '10000.00',
        string $waiver = '0.00',
        bool $waiverApproved = false,
        string $dueOn = '2099-01-01',
        string $status = 'issued',
    ): StudentTuition {
        return StudentTuition::query()->create([
            'enrollment_id' => $enrollment->id,
            'tuition_number' => 'TUI-OVERVIEW-'.$suffix,
            'original_amount' => $amount,
            'waiver_amount' => $waiver,
            'waiver_approved_by' => $waiverApproved ? $this->user->id : null,
            'waiver_approved_at' => $waiverApproved ? '2025-01-02 00:00:00' : null,
            'issued_on' => '2025-01-01',
            'due_on' => $dueOn,
            'status' => $status,
        ]);
    }

    protected function createTuitionPayment(
        StudentTuition $tuition,
        string $suffix,
        string $amount,
        string $status = 'confirmed',
        string $paidAt = '2025-03-01 10:00:00',
        ?string $receipt = null,
    ): StudentTuitionPayment {
        return StudentTuitionPayment::query()->create([
            'student_tuition_id' => $tuition->id,
            'payment_number' => 'TUIP-OVERVIEW-'.$suffix,
            'payment_method_id' => $this->paymentMethod->id,
            'financial_account_id' => $this->financialAccount->id,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'proof_file_path' => $receipt,
            'status' => $status,
            'verified_at' => $status === 'confirmed' ? '2025-03-01 11:00:00' : null,
            'verified_by' => $status === 'confirmed' ? $this->user->id : null,
            'recorded_by' => $this->user->id,
        ]);
    }

    protected function createStudentExpense(
        string $suffix,
        Enrollment $enrollment,
        string $status = 'issued',
        ?string $cancelledAt = null,
    ): StudentExpense {
        return StudentExpense::query()->create([
            'expense_number' => 'EXP-OVERVIEW-'.$suffix,
            'student_id' => $this->student->id,
            'enrollment_id' => $enrollment->id,
            'branch_id' => $this->branch->id,
            'student_expense_type_id' => $this->expenseType->id,
            'student_expense_price_version_id' => $this->expensePrice->id,
            'description' => 'Student materials',
            'quantity' => '1.000',
            'unit_amount' => '500.00',
            'issued_on' => '2025-01-01',
            'due_on' => '2099-01-01',
            'status' => $status,
            'cancelled_at' => $cancelledAt,
            'recorded_by' => $this->user->id,
        ]);
    }

    protected function createExpensePayment(
        StudentExpense $expense,
        string $suffix,
        string $amount,
        string $status = 'confirmed',
        string $paidAt = '2025-03-02 10:00:00',
        ?string $receipt = null,
        ?string $transactionReference = null,
    ): StudentExpensePayment {
        return StudentExpensePayment::query()->create([
            'student_expense_id' => $expense->id,
            'payment_number' => 'SEXP-OVERVIEW-'.$suffix,
            'payment_method_id' => $this->paymentMethod->id,
            'financial_account_id' => $this->financialAccount->id,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'transaction_reference' => $transactionReference,
            'proof_file_path' => $receipt,
            'status' => $status,
            'verified_at' => $status === 'confirmed' ? '2025-03-02 11:00:00' : null,
            'verified_by' => $status === 'confirmed' ? $this->user->id : null,
            'recorded_by' => $this->user->id,
        ]);
    }
}
