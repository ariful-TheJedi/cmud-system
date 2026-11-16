<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;

class CoreAcademySeeder extends Seeder
{
    use UsesCoreSeedData;

    public function run(): void
    {
        $instituteId = $this->findId('institutes', ['code' => 'CMUD-DEV-CORE']);
        $dhakaBranchId = $this->findId('branches', ['institute_id' => $instituteId, 'code' => 'DEV-BR-DHAKA']);
        $chattogramBranchId = $this->findId('branches', ['institute_id' => $instituteId, 'code' => 'DEV-BR-CHITTAGONG']);
        $operatorId = $this->findId('users', ['email' => 'core.operator@example.invalid']);
        $staffId = $this->findId('staff', ['staff_code' => 'DEV-STAFF-002']);

        $college = $this->saveRow('medical_colleges', ['short_name' => 'CMUD-DEV-MC-01'], [
            'name' => 'Central Demo Medical College',
            'country' => 'Bangladesh',
            'city' => 'Dhaka',
            'address' => 'Fictional Campus, Dhaka 0000',
            'is_active' => true,
        ]);

        $courses = [];
        foreach ([
            ['code' => 'DEV-COURSE-DMU', 'name' => 'Core Demo DMU Programme', 'duration' => 18, 'completion' => 24, 'mode' => 'offline', 'description' => 'Three-stage fictional programme for progression examples.'],
            ['code' => 'DEV-COURSE-EMT', 'name' => 'Core Demo Emergency Care', 'duration' => 9, 'completion' => 12, 'mode' => 'dual', 'description' => 'Fictional emergency care course for enrollment and tuition examples.'],
            ['code' => 'DEV-COURSE-BLS', 'name' => 'Core Demo Life Support', 'duration' => 6, 'completion' => 9, 'mode' => 'online', 'description' => 'Fictional short course for a second enrollment.'],
            ['code' => 'DEV-COURSE-REFRESH', 'name' => 'Core Demo Clinical Refresher', 'duration' => 4, 'completion' => 6, 'mode' => 'offline', 'description' => 'Fictional refresher course for certificate examples.'],
        ] as $course) {
            $saved = $this->saveRow('courses', [
                'institute_id' => $instituteId,
                'code' => $course['code'],
            ], [
                'name' => $course['name'],
                'description' => $course['description'],
                'duration_months' => $course['duration'],
                'completion_period_months' => $course['completion'],
                'course_mode' => $course['mode'],
                'is_active' => true,
            ]);
            $courses[$course['code']] = (int) $saved->id;
        }

        $stages = [];
        foreach ([
            ['course' => 'DEV-COURSE-DMU', 'code' => 'DEV-DMU-STAGE-1', 'name' => 'Foundation Stage', 'sequence' => 1, 'months' => 6],
            ['course' => 'DEV-COURSE-DMU', 'code' => 'DEV-DMU-STAGE-2', 'name' => 'Intermediate Stage', 'sequence' => 2, 'months' => 6],
            ['course' => 'DEV-COURSE-DMU', 'code' => 'DEV-DMU-STAGE-3', 'name' => 'Advanced Stage', 'sequence' => 3, 'months' => 6],
            ['course' => 'DEV-COURSE-EMT', 'code' => 'DEV-EMT-STAGE-1', 'name' => 'Emergency Care Stage', 'sequence' => 1, 'months' => 9],
            ['course' => 'DEV-COURSE-BLS', 'code' => 'DEV-BLS-STAGE-1', 'name' => 'Life Support Stage', 'sequence' => 1, 'months' => 6],
            ['course' => 'DEV-COURSE-REFRESH', 'code' => 'DEV-REFRESH-STAGE-1', 'name' => 'Refresher Stage', 'sequence' => 1, 'months' => 4],
        ] as $stage) {
            $saved = $this->saveRow('course_stages', [
                'course_id' => $courses[$stage['course']],
                'code' => $stage['code'],
            ], [
                'name' => $stage['name'],
                'sequence_number' => $stage['sequence'],
                'duration_months' => $stage['months'],
                'description' => 'Fictional course stage; progression policy is pending.',
                'is_active' => true,
            ]);
            $stages[$stage['code']] = (int) $saved->id;
        }

        $batches = [];
        foreach ([
            ['code' => 'DEV-BATCH-DMU-01', 'course' => 'DEV-COURSE-DMU', 'branch' => $dhakaBranchId, 'name' => 'DMU Demo Cohort 01', 'starts' => '2025-01-15', 'ends' => '2026-07-15'],
            ['code' => 'DEV-BATCH-EMT-01', 'course' => 'DEV-COURSE-EMT', 'branch' => $dhakaBranchId, 'name' => 'Emergency Care Demo Cohort 01', 'starts' => '2025-03-01', 'ends' => '2025-12-01'],
            ['code' => 'DEV-BATCH-BLS-01', 'course' => 'DEV-COURSE-BLS', 'branch' => $chattogramBranchId, 'name' => 'Life Support Demo Cohort 01', 'starts' => '2025-04-01', 'ends' => '2025-10-01'],
            ['code' => 'DEV-BATCH-REFRESH-01', 'course' => 'DEV-COURSE-REFRESH', 'branch' => $dhakaBranchId, 'name' => 'Refresher Demo Cohort 01', 'starts' => '2025-05-01', 'ends' => '2025-09-01'],
        ] as $batch) {
            $saved = $this->saveRow('batches', [
                'course_id' => $courses[$batch['course']],
                'code' => $batch['code'],
            ], [
                'branch_id' => $batch['branch'],
                'name' => $batch['name'],
                'admission_opens_on' => '2024-11-01',
                'admission_closes_on' => '2025-04-30',
                'starts_on' => $batch['starts'],
                'expected_ends_on' => $batch['ends'],
                'actual_ends_on' => null,
                'capacity' => 30,
                'status' => 'active',
                'notes' => 'Fictional development cohort; capacity enforcement is pending.',
            ]);
            $batches[$batch['code']] = (int) $saved->id;
        }

        $teacher = $this->saveRow('teachers', ['teacher_code' => 'DEV-TEACHER-001'], [
            'institute_id' => $instituteId,
            'staff_id' => $staffId,
            'full_name' => 'Nusrat Jahan',
            'phone' => '+880000000030',
            'email' => 'core.teacher@example.invalid',
            'qualification' => 'MBBS (fictional development profile)',
            'specialization' => 'Emergency care',
            'designation' => 'Demo Instructor',
            'organization' => 'CMUD Core Development Institute',
            'bmdc_number' => null,
            'address' => 'Fictional address, Dhaka 0000',
            'photo_path' => null,
            'is_active' => true,
            'notes' => 'Fictional teacher for development scenarios.',
        ]);
        $teacherId = (int) $teacher->id;

        foreach ([
            ['course' => 'DEV-COURSE-DMU', 'role' => 'Lead instructor'],
            ['course' => 'DEV-COURSE-EMT', 'role' => 'Guest instructor'],
        ] as $assignment) {
            $note = $this->scenarioNote('SD-ACA-008-COURSE-'.str_replace('DEV-COURSE-', '', $assignment['course']), 'Seed-owned course teacher assignment.');
            $this->saveRow('course_teacher', ['notes' => $note], [
                'course_id' => $courses[$assignment['course']],
                'teacher_id' => $teacherId,
                'teaching_role' => $assignment['role'],
                'is_active' => true,
            ]);
        }
        $this->saveRow('branch_teacher', [
            'branch_id' => $dhakaBranchId,
            'teacher_id' => $teacherId,
        ], []);

        $subjects = [];
        foreach ([
            ['code' => 'DEV-SUBJ-EMERGENCY', 'name' => 'Emergency Care Fundamentals'],
            ['code' => 'DEV-SUBJ-LIFESUPPORT', 'name' => 'Life Support Fundamentals'],
        ] as $subject) {
            $saved = $this->saveRow('subjects', ['code' => $subject['code']], [
                'name' => $subject['name'],
                'description' => 'Fictional subject for development demonstrations.',
                'is_active' => true,
            ]);
            $subjects[$subject['code']] = (int) $saved->id;
        }
        foreach ([
            ['course' => 'DEV-COURSE-DMU', 'subject' => 'DEV-SUBJ-EMERGENCY'],
            ['course' => 'DEV-COURSE-EMT', 'subject' => 'DEV-SUBJ-EMERGENCY'],
            ['course' => 'DEV-COURSE-BLS', 'subject' => 'DEV-SUBJ-LIFESUPPORT'],
        ] as $link) {
            $this->saveRow('course_subject', [
                'course_id' => $courses[$link['course']],
                'subject_id' => $subjects[$link['subject']],
            ], []);
        }

        $coursePrices = [];
        foreach ([
            ['course' => 'DEV-COURSE-DMU', 'version' => 1, 'amount' => '60000.00', 'from' => '2025-01-01', 'to' => '2025-12-31'],
            ['course' => 'DEV-COURSE-DMU', 'version' => 2, 'amount' => '65000.00', 'from' => '2026-01-01', 'to' => null],
            ['course' => 'DEV-COURSE-EMT', 'version' => 1, 'amount' => '40000.00', 'from' => '2025-01-01', 'to' => null],
            ['course' => 'DEV-COURSE-BLS', 'version' => 1, 'amount' => '50000.00', 'from' => '2025-01-01', 'to' => null],
            ['course' => 'DEV-COURSE-REFRESH', 'version' => 1, 'amount' => '35000.00', 'from' => '2025-01-01', 'to' => null],
        ] as $price) {
            $courseId = $courses[$price['course']];
            $saved = $this->saveRow('course_price_versions', [
                'course_id' => $courseId,
                'branch_id' => null,
                'version_number' => $price['version'],
            ], [
                'amount' => $price['amount'],
                'effective_from' => $price['from'],
                'effective_to' => $price['to'],
                'status' => 'approved',
                'approved_by' => $operatorId,
                'approved_at' => self::SEEDED_AT,
                'notes' => $this->scenarioNote('SD-ACA-009-'.$price['course'].'-V'.$price['version'], 'Illustrative course price period; selector behavior is pending.'),
            ]);
            $coursePrices[$price['course'].'-'.$price['version']] = (int) $saved->id;
        }

        $students = [];
        foreach ([
            ['code' => 'DEV-CORE-STU-001', 'name' => 'Samira Akter', 'branch' => $dhakaBranchId, 'status' => 'registered'],
            ['code' => 'DEV-CORE-STU-002', 'name' => 'Imran Hossain', 'branch' => $dhakaBranchId, 'status' => 'active'],
            ['code' => 'DEV-CORE-STU-003', 'name' => 'Maliha Sultana', 'branch' => $chattogramBranchId, 'status' => 'active'],
            ['code' => 'DEV-CORE-STU-004', 'name' => 'Rafiq Hasan', 'branch' => $dhakaBranchId, 'status' => 'active'],
            ['code' => 'DEV-CORE-STU-005', 'name' => 'Farzana Karim', 'branch' => $dhakaBranchId, 'status' => 'completed'],
            ['code' => 'DEV-CORE-STU-006', 'name' => 'Tanvir Islam', 'branch' => $chattogramBranchId, 'status' => 'active'],
        ] as $index => $student) {
            $saved = $this->saveRow('students', ['student_code' => $student['code']], [
                'institute_id' => $instituteId,
                'admission_branch_id' => $student['branch'],
                'admission_date' => '2025-01-05',
                'full_name' => $student['name'],
                'father_name' => 'Demo Guardian '.($index + 1),
                'mother_name' => 'Demo Parent '.($index + 1),
                'date_of_birth' => '1998-01-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'gender' => $index % 2 === 0 ? 'female' : 'male',
                'phone' => '+880000000'.str_pad((string) (100 + $index), 3, '0', STR_PAD_LEFT),
                'alternative_phone' => null,
                'email' => strtolower($student['code']).'@example.invalid',
                'present_address' => 'Fictional Demo Road, Dhaka 0000',
                'permanent_address' => 'Fictional Sample Village, Bangladesh 0000',
                'medical_college_id' => $college->id,
                'qualification' => 'MBBS (fictional sample)',
                'graduation_year' => 2022,
                'bmdc_number' => null,
                'photo_path' => null,
                'documents' => json_encode([]),
                'status' => $student['status'],
                'notes' => $this->scenarioNote('SD-ACA-001-'.$student['code'], 'Fictional development student.'),
            ]);
            $students[$student['code']] = (int) $saved->id;
        }

        $enrollments = [];
        foreach ([
            ['code' => 'DEV-CORE-ENR-002', 'student' => 'DEV-CORE-STU-002', 'course' => 'DEV-COURSE-EMT', 'batch' => 'DEV-BATCH-EMT-01', 'status' => 'active', 'complete' => null],
            ['code' => 'DEV-CORE-ENR-003-A', 'student' => 'DEV-CORE-STU-003', 'course' => 'DEV-COURSE-BLS', 'batch' => 'DEV-BATCH-BLS-01', 'status' => 'active', 'complete' => null],
            ['code' => 'DEV-CORE-ENR-003-B', 'student' => 'DEV-CORE-STU-003', 'course' => 'DEV-COURSE-EMT', 'batch' => 'DEV-BATCH-EMT-01', 'status' => 'active', 'complete' => null],
            ['code' => 'DEV-CORE-ENR-004', 'student' => 'DEV-CORE-STU-004', 'course' => 'DEV-COURSE-DMU', 'batch' => 'DEV-BATCH-DMU-01', 'status' => 'active', 'complete' => null],
            ['code' => 'DEV-CORE-ENR-005', 'student' => 'DEV-CORE-STU-005', 'course' => 'DEV-COURSE-REFRESH', 'batch' => 'DEV-BATCH-REFRESH-01', 'status' => 'completed', 'complete' => '2025-09-01'],
            ['code' => 'DEV-CORE-ENR-005-B', 'student' => 'DEV-CORE-STU-005', 'course' => 'DEV-COURSE-BLS', 'batch' => 'DEV-BATCH-BLS-01', 'status' => 'completed', 'complete' => '2025-10-01'],
            ['code' => 'DEV-CORE-ENR-006', 'student' => 'DEV-CORE-STU-006', 'course' => 'DEV-COURSE-REFRESH', 'batch' => 'DEV-BATCH-REFRESH-01', 'status' => 'active', 'complete' => null],
        ] as $enrollment) {
            $courseId = $courses[$enrollment['course']];
            $saved = $this->saveRow('enrollments', ['enrollment_code' => $enrollment['code']], [
                'student_id' => $students[$enrollment['student']],
                'course_id' => $courseId,
                'branch_id' => $enrollment['course'] === 'DEV-COURSE-BLS' ? $chattogramBranchId : $dhakaBranchId,
                'batch_id' => $batches[$enrollment['batch']],
                'enrollment_date' => '2025-01-05',
                'starts_on' => '2025-01-15',
                'expected_completion_on' => $enrollment['complete'] ?? '2025-12-01',
                'completion_deadline_on' => $enrollment['complete'] ?? '2026-06-01',
                'completed_on' => $enrollment['complete'],
                'status' => $enrollment['status'],
                'is_active' => $enrollment['status'] !== 'completed',
                'notes' => $this->scenarioNote('SD-ACA-002-'.$enrollment['code'], 'Fictional enrollment; business lifecycle is pending.'),
            ]);
            $enrollments[$enrollment['code']] = (int) $saved->id;
        }

        $stageRecordIds = [];
        foreach ([
            ['key' => 'DMU-1', 'enrollment' => 'DEV-CORE-ENR-004', 'stage' => 'DEV-DMU-STAGE-1', 'batch' => 'DEV-BATCH-DMU-01', 'status' => 'completed', 'start' => '2025-01-15', 'end' => '2025-07-15'],
            ['key' => 'DMU-2', 'enrollment' => 'DEV-CORE-ENR-004', 'stage' => 'DEV-DMU-STAGE-2', 'batch' => 'DEV-BATCH-DMU-01', 'status' => 'completed', 'start' => '2025-07-16', 'end' => '2026-01-15'],
            ['key' => 'DMU-3', 'enrollment' => 'DEV-CORE-ENR-004', 'stage' => 'DEV-DMU-STAGE-3', 'batch' => 'DEV-BATCH-DMU-01', 'status' => 'in_progress', 'start' => '2026-01-16', 'end' => null],
            ['key' => 'REFRESH-5', 'enrollment' => 'DEV-CORE-ENR-005', 'stage' => 'DEV-REFRESH-STAGE-1', 'batch' => 'DEV-BATCH-REFRESH-01', 'status' => 'completed', 'start' => '2025-05-01', 'end' => '2025-09-01'],
        ] as $stageRecord) {
            $note = $this->scenarioNote('SD-ACA-004-'.$stageRecord['key'], 'Fictional stage record; progression validation is pending.');
            $saved = $this->saveRow('student_stage_records', ['notes' => $note], [
                'enrollment_id' => $enrollments[$stageRecord['enrollment']],
                'course_stage_id' => $stages[$stageRecord['stage']],
                'batch_id' => $batches[$stageRecord['batch']],
                'started_on' => $stageRecord['start'],
                'expected_completion_on' => $stageRecord['end'] ?? '2026-07-15',
                'completed_on' => $stageRecord['end'],
                'status' => $stageRecord['status'],
            ]);
            $stageRecordIds[$stageRecord['key']] = (int) $saved->id;
        }

        foreach ([
            ['attempt' => 1, 'result' => 'failed', 'obtained' => '55.00', 'grade' => 'F'],
            ['attempt' => 2, 'result' => 'passed', 'obtained' => '82.00', 'grade' => 'B'],
        ] as $exam) {
            $this->saveRow('exam_records', [
                'student_stage_record_id' => $stageRecordIds['DMU-3'],
                'exam_name' => 'Core Demonstration Exam',
                'attempt_number' => $exam['attempt'],
            ], [
                'exam_date' => $exam['attempt'] === 1 ? '2026-04-01' : '2026-05-01',
                'roll_number' => 'DEV-ROLL-004',
                'total_marks' => '100.00',
                'obtained_marks' => $exam['obtained'],
                'grade' => $exam['grade'],
                'result_status' => $exam['result'],
                'result_published_on' => $exam['attempt'] === 1 ? '2026-04-10' : '2026-05-10',
                'result_file_path' => null,
                'remarks' => 'Fictional exam row; pass/fail policy remains pending.',
            ]);
        }

        $template = $this->saveRow('certificate_templates', [
            'institute_id' => $instituteId,
            'name' => 'CMUD Core Demo Certificate Template',
            'version_number' => 1,
        ], [
            'course_id' => null,
            'template_file_path' => 'development/templates/core-demo-certificate.docx',
            'description' => 'Fictional template reference; no template file is included.',
            'is_active' => true,
        ]);
        foreach ([
            ['key' => 'PENDING', 'enrollment' => 'DEV-CORE-ENR-005', 'status' => 'pending', 'number' => null, 'issued' => null],
            ['key' => 'ISSUED', 'enrollment' => 'DEV-CORE-ENR-005-B', 'status' => 'issued', 'number' => 'DEV-CORE-CERT-005-B', 'issued' => '2025-10-10'],
        ] as $certificate) {
            $this->saveRow('certificates', [
                'notes' => $this->scenarioNote('SD-ACA-006-'.$certificate['key'], 'Fictional certificate state; eligibility rules are pending.'),
            ], [
                'enrollment_id' => $enrollments[$certificate['enrollment']],
                'certificate_template_id' => $template->id,
                'certificate_number' => $certificate['number'],
                'recipient_name' => 'Farzana Karim',
                'course_title' => 'Core Demo Clinical Refresher',
                'issued_on' => $certificate['issued'],
                'certificate_file_path' => null,
                'printed_at' => null,
                'delivered_at' => null,
                'received_by' => null,
                'delivery_method' => null,
                'delivery_reference' => null,
                'status' => $certificate['status'],
                'void_reason' => null,
            ]);
        }

        $group = $this->saveRow('communication_groups', [
            'institute_id' => $instituteId,
            'name' => 'Core Demo Emergency Care Group',
        ], [
            'branch_id' => $dhakaBranchId,
            'course_id' => $courses['DEV-COURSE-EMT'],
            'batch_id' => $batches['DEV-BATCH-EMT-01'],
            'platform' => 'demo',
            'group_url' => null,
            'description' => 'Fictional communication group; platform integration is not implemented.',
            'is_active' => true,
        ]);
        foreach (['DEV-CORE-STU-002', 'DEV-CORE-STU-003'] as $studentCode) {
            $note = $this->scenarioNote('SD-ACA-007-'.$studentCode, 'Fictional group membership; eligibility rules are pending.');
            $this->saveRow('student_group_memberships', ['notes' => $note], [
                'student_id' => $students[$studentCode],
                'communication_group_id' => $group->id,
                'invited_at' => '2025-01-10 09:00:00',
                'joined_at' => '2025-01-11 09:00:00',
                'left_at' => null,
                'status' => 'joined',
            ]);
        }
    }
}
