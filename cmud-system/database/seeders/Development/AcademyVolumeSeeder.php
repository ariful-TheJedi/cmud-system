<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Adds the repeatable Academy volume set after CoreAcademySeeder's anchors. */
class AcademyVolumeSeeder extends Seeder
{
    use UsesCoreSeedData;

    private const NEW_COURSES = [
        ['code' => 'DEV-ACA-COURSE-CLINICAL-01', 'name' => 'Fictional Clinical Skills Certificate', 'duration' => 6, 'completion' => 9, 'mode' => 'offline', 'branch' => 'DEV-BR-DHAKA'],
        ['code' => 'DEV-ACA-COURSE-EMERGENCY-01', 'name' => 'Fictional Emergency Response Certificate', 'duration' => 8, 'completion' => 12, 'mode' => 'dual', 'branch' => 'DEV-BR-CHITTAGONG'],
        ['code' => 'DEV-ACA-COURSE-PATIENT-01', 'name' => 'Fictional Patient Care Certificate', 'duration' => 5, 'completion' => 8, 'mode' => 'online', 'branch' => 'DEV-BR-DHAKA'],
        ['code' => 'DEV-ACA-COURSE-HEALTH-01', 'name' => 'Fictional Community Health Certificate', 'duration' => 7, 'completion' => 10, 'mode' => 'offline', 'branch' => 'DEV-BR-DHAKA'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Academy volume data may only be seeded in local or testing environments.');
        }

        $instituteId = $this->findId('institutes', ['code' => 'CMUD-DEV-CORE']);
        $operatorId = $this->findId('users', ['email' => 'core.operator@example.invalid']);
        $branchIds = [];
        foreach (['DEV-BR-DHAKA', 'DEV-BR-CHITTAGONG', 'DEV-BR-SYLHET'] as $code) {
            $branchIds[$code] = $this->findId('branches', ['institute_id' => $instituteId, 'code' => $code]);
        }

        $courseIds = $this->seedCourses($instituteId);
        $stageIds = $this->seedStages($courseIds);
        $batchIds = $this->seedBatches($courseIds, $branchIds);
        $this->seedCoursePrices($courseIds, $operatorId);
        $collegeIds = $this->seedColleges();
        $subjectIds = $this->seedSubjects($courseIds);
        $teacherIds = $this->seedTeachers($instituteId, $branchIds);
        $this->seedTeachingLinks($courseIds, $subjectIds, $teacherIds);
        $studentIds = $this->seedStudents($instituteId, $branchIds, $collegeIds);
        $enrollmentRows = $this->seedEnrollments($studentIds, $courseIds, $batchIds);
        $stageRecordIds = $this->seedStageRecords($enrollmentRows, $stageIds);
        $this->seedExams($stageRecordIds);
        $this->seedCertificates($enrollmentRows, $instituteId);
        $this->seedGroupsAndMemberships($instituteId, $courseIds, $batchIds, $studentIds);
    }

    /** @return array<string, int> */
    private function seedCourses(int $instituteId): array
    {
        $codes = ['DEV-COURSE-DMU', 'DEV-COURSE-EMT', 'DEV-COURSE-BLS', 'DEV-COURSE-REFRESH'];
        $courses = [];
        foreach (self::NEW_COURSES as $course) {
            $this->saveRow('courses', ['institute_id' => $instituteId, 'code' => $course['code']], [
                'name' => $course['name'],
                'description' => 'Fictional development offering; application workflow is pending.',
                'duration_months' => $course['duration'],
                'completion_period_months' => $course['completion'],
                'course_mode' => $course['mode'],
                'is_active' => true,
            ]);
            $codes[] = $course['code'];
        }

        foreach ($codes as $code) {
            $courses[$code] = $this->findId('courses', ['institute_id' => $instituteId, 'code' => $code]);
        }

        return $courses;
    }

    /** @param array<string, int> $courses
     *  @return array<string, int>
     */
    private function seedStages(array $courses): array
    {
        $stages = [];
        foreach (DB::table('course_stages')->get(['id', 'course_id', 'code']) as $row) {
            $stages[$row->course_id.':'.$row->code] = (int) $row->id;
        }

        foreach (self::NEW_COURSES as $index => $course) {
            $code = 'DEV-ACA-STAGE-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $saved = $this->saveRow('course_stages', ['course_id' => $courses[$course['code']], 'code' => $code], [
                'name' => 'Core Certificate Stage',
                'sequence_number' => 1,
                'duration_months' => $course['duration'],
                'description' => 'Single-stage fictional course; progression rules are pending.',
                'is_active' => true,
            ]);
            $stages[$courses[$course['code']].':'.$code] = (int) $saved->id;
        }

        return $stages;
    }

    /** @param array<string, int> $courses @param array<string, int> $branches
     *  @return array<string, array{ id: int, course: string, branch: string, starts: string, ends: string }>
     */
    private function seedBatches(array $courses, array $branches): array
    {
        $specs = [
            ['code' => 'DEV-BATCH-DMU-02', 'course' => 'DEV-COURSE-DMU', 'branch' => 'DEV-BR-DHAKA'],
            ['code' => 'DEV-BATCH-EMT-02', 'course' => 'DEV-COURSE-EMT', 'branch' => 'DEV-BR-DHAKA'],
            ['code' => 'DEV-BATCH-BLS-02', 'course' => 'DEV-COURSE-BLS', 'branch' => 'DEV-BR-CHITTAGONG'],
            ['code' => 'DEV-BATCH-REFRESH-02', 'course' => 'DEV-COURSE-REFRESH', 'branch' => 'DEV-BR-DHAKA'],
        ];
        foreach (self::NEW_COURSES as $index => $course) {
            $specs[] = ['code' => 'DEV-ACA-BATCH-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'course' => $course['code'], 'branch' => $course['branch']];
        }
        foreach ([
            ['DEV-COURSE-DMU', 'DEV-BR-DHAKA'],
            ['DEV-COURSE-EMT', 'DEV-BR-DHAKA'],
            ['DEV-COURSE-BLS', 'DEV-BR-CHITTAGONG'],
        ] as $index => [$course, $branch]) {
            $specs[] = ['code' => 'DEV-ACA-BATCH-ALT-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'course' => $course, 'branch' => $branch];
        }

        // Open future cohorts for the student admission form. Stable codes keep reruns idempotent.
        foreach ([
            ['DEV-COURSE-DMU', 'DEV-BR-DHAKA'],
            ['DEV-COURSE-EMT', 'DEV-BR-DHAKA'],
            ['DEV-COURSE-BLS', 'DEV-BR-CHITTAGONG'],
            ['DEV-COURSE-REFRESH', 'DEV-BR-DHAKA'],
            ['DEV-ACA-COURSE-CLINICAL-01', 'DEV-BR-DHAKA'],
            ['DEV-ACA-COURSE-EMERGENCY-01', 'DEV-BR-CHITTAGONG'],
            ['DEV-ACA-COURSE-PATIENT-01', 'DEV-BR-DHAKA'],
            ['DEV-ACA-COURSE-HEALTH-01', 'DEV-BR-DHAKA'],
        ] as $index => [$course, $branch]) {
            $specs[] = [
                'code' => 'DEV-ACA-ADMISSION-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'course' => $course,
                'branch' => $branch,
                'admission_sample' => true,
            ];
        }

        foreach ($specs as $index => $batch) {
            $isAdmissionSample = $batch['admission_sample'] ?? false;
            $sampleNumber = $index - 10;
            $starts = $isAdmissionSample
                ? sprintf('2027-%02d-01', 3 + (($sampleNumber - 1) % 6))
                : sprintf('2025-%02d-01', 3 + ($index % 6));
            $ends = date('Y-m-d', strtotime($starts.' + 8 months'));
            $this->saveRow('batches', ['course_id' => $courses[$batch['course']], 'code' => $batch['code']], [
                'branch_id' => $branches[$batch['branch']],
                'name' => $isAdmissionSample
                    ? 'Open Admission Demo Cohort '.str_pad((string) $sampleNumber, 2, '0', STR_PAD_LEFT)
                    : 'Academy Development Cohort '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'admission_opens_on' => $isAdmissionSample ? '2026-11-13' : '2024-10-01',
                'admission_closes_on' => $isAdmissionSample ? '2027-02-28' : '2025-02-28',
                'starts_on' => $starts,
                'expected_ends_on' => $ends,
                'actual_ends_on' => null,
                'capacity' => 30,
                'status' => 'active',
                'notes' => $isAdmissionSample
                    ? 'Fictional open cohort for testing the student admission form.'
                    : 'Fictional development cohort; capacity workflow is pending.',
            ]);
        }

        $result = [];
        foreach (['DEV-BATCH-DMU-01' => 'DEV-COURSE-DMU', 'DEV-BATCH-EMT-01' => 'DEV-COURSE-EMT', 'DEV-BATCH-BLS-01' => 'DEV-COURSE-BLS', 'DEV-BATCH-REFRESH-01' => 'DEV-COURSE-REFRESH'] as $code => $course) {
            $result[$code] = $this->batchRow($courses[$course], $code);
        }
        foreach ($specs as $batch) {
            $result[$batch['code']] = $this->batchRow($courses[$batch['course']], $batch['code']);
        }

        return $result;
    }

    /** @param array<string, int> $branches */
    private function batchRow(int $courseId, string $code): object
    {
        $row = DB::table('batches')->where('course_id', $courseId)->where('code', $code)->first();
        if ($row === null) {
            throw new \RuntimeException("Missing persisted batch [{$code}].");
        }

        return (object) [
            'id' => (int) $row->id,
            'course_id' => (int) $row->course_id,
            'branch_id' => (int) $row->branch_id,
            'starts_on' => $row->starts_on,
            'expected_ends_on' => $row->expected_ends_on,
        ];
    }

    /** @param array<string, int> $courses */
    private function seedCoursePrices(array $courses, int $operatorId): void
    {
        foreach (self::NEW_COURSES as $index => $course) {
            $this->saveRow('course_price_versions', [
                'course_id' => $courses[$course['code']],
                'branch_id' => null,
                'version_number' => 1,
            ], [
                'amount' => (string) (28000 + $index * 2000).'.00',
                'effective_from' => '2025-01-01',
                'effective_to' => null,
                'status' => 'approved',
                'approved_by' => $operatorId,
                'approved_at' => self::SEEDED_AT,
                'notes' => $this->scenarioNote('SD-ACA-009-'.($index + 2), 'Fictional course price; date selection is pending.'),
            ]);
        }
    }

    /** @return array<int, int> */
    private function seedColleges(): array
    {
        $ids = [$this->findId('medical_colleges', ['short_name' => 'CMUD-DEV-MC-01'])];
        for ($index = 2; $index <= 20; $index++) {
            $short = 'DEV-ACA-MC-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $college = $this->saveRow('medical_colleges', ['short_name' => $short], [
                'name' => 'Fictional Medical College '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'country' => 'Bangladesh',
                'city' => ['Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna'][$index % 5],
                'address' => 'Fictional academic road, Bangladesh 0000',
                'is_active' => true,
            ]);
            $ids[] = (int) $college->id;
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedSubjects(array $courses): array
    {
        $subjects = [];
        foreach (DB::table('subjects')->get(['id', 'code']) as $row) {
            $subjects[$row->code] = (int) $row->id;
        }
        $names = [
            'Clinical Communication', 'Patient Safety', 'Basic Anatomy', 'Emergency Response',
            'Infection Prevention', 'First Aid Practice', 'Community Health', 'Clinical Ethics',
            'Vital Signs', 'Health Assessment', 'Medical Documentation', 'Care Coordination',
            'Life Support Principles', 'Professional Practice', 'Public Health Awareness',
        ];
        $courseIds = array_values($courses);
        foreach ($names as $index => $name) {
            $code = 'DEV-ACA-SUBJ-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $saved = $this->saveRow('subjects', ['code' => $code], [
                'name' => $name,
                'description' => 'Fictional Academy reference subject for development data.',
                'is_active' => true,
            ]);
            $subjects[$code] = (int) $saved->id;
            DB::table('course_subject')->insertOrIgnore([
                'course_id' => $courseIds[$index % count($courseIds)],
                'subject_id' => $subjects[$code],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $subjects;
    }

    /** @param array<string, int> $branches
     *  @return array<int, int>
     */
    private function seedTeachers(int $instituteId, array $branches): array
    {
        $teachers = [$this->findId('teachers', ['teacher_code' => 'DEV-TEACHER-001'])];
        $profiles = [
            ['Mariam Sultana', 'Clinical Practice'],
            ['Kamal Uddin', 'Emergency Care'],
            ['Nabila Rahman', 'Patient Safety'],
            ['Sajid Hasan', 'Community Health'],
            ['Farzana Akter', 'Life Support'],
        ];
        $branchCodes = array_keys($branches);
        foreach ($profiles as $index => [$name, $specialty]) {
            $number = $index + 2;
            $code = 'DEV-ACA-TEACHER-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $saved = $this->saveRow('teachers', ['teacher_code' => $code], [
                'institute_id' => $instituteId,
                'stuff_id' => null,
                'full_name' => $name,
                'phone' => '+880000000'.str_pad((string) (300 + $number), 3, '0', STR_PAD_LEFT),
                'email' => 'academy.teacher.'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'@example.invalid',
                'qualification' => 'Fictional postgraduate health qualification',
                'specialization' => $specialty,
                'designation' => 'Development Instructor',
                'organization' => 'CMUD Core Development Institute',
                'bmdc_number' => null,
                'address' => 'Fictional educator address, Bangladesh 0000',
                'photo_path' => null,
                'is_active' => true,
                'notes' => 'Fictional Academy instructor; assignment policy is pending.',
            ]);
            $teachers[] = (int) $saved->id;
            $this->saveRow('branch_teacher', ['branch_id' => $branches[$branchCodes[($number - 1) % count($branchCodes)]], 'teacher_id' => $saved->id], []);
        }

        return $teachers;
    }

    /** @param array<string, int> $courses @param array<string, int> $subjects @param array<int, int> $teachers */
    private function seedTeachingLinks(array $courses, array $subjects, array $teachers): void
    {
        // Assign modules directly to educators; no course-teacher links are created.
        $teacherIds = array_values($teachers);
        foreach (array_values($subjects) as $index => $subjectId) {
            foreach ([$index % count($teacherIds), ($index + 1) % count($teacherIds)] as $offset => $teacherIndex) {
                $this->saveRow('subject_teacher', [
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherIds[$teacherIndex],
                ], [
                    'teaching_role' => 'Development instructor',
                    'is_active' => true,
                    'notes' => $this->scenarioNote('SD-ACA-008-MODULE-'.$subjectId.'-'.$offset, 'Fictional module teaching assignment.'),
                ]);
            }
        }
    }

    /** @param array<string, int> $branches @param array<int, int> $colleges
     *  @return array<int, int>
     */
    private function seedStudents(int $instituteId, array $branches, array $colleges): array
    {
        $students = [];
        for ($index = 1; $index <= 6; $index++) {
            $students[$index] = $this->findId('students', ['student_code' => 'DEV-CORE-STU-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)]);
        }

        $givenNames = ['Afsana', 'Arif', 'Asma', 'Biplob', 'Dipa', 'Farid', 'Hasina', 'Imtiaz', 'Jannat', 'Mahmud', 'Nusrat', 'Omar', 'Papia', 'Rashed', 'Sadia', 'Tariq', 'Urmi', 'Wasim', 'Yasmin', 'Zubair'];
        $familyNames = ['Akter', 'Ahmed', 'Begum', 'Chowdhury', 'Das', 'Hossain', 'Islam', 'Karim', 'Khatun', 'Mahmud', 'Rahman', 'Sultana'];
        $branchCodes = array_keys($branches);
        for ($number = 7; $number <= 200; $number++) {
            $offset = $number - 7;
            $name = $givenNames[$offset % count($givenNames)].' '.$familyNames[intdiv($offset, count($givenNames)) % count($familyNames)];
            $branchCode = $branchCodes[$offset % count($branchCodes)];
            $collegeId = $colleges[$offset % count($colleges)];
            $code = 'DEV-ACA-STU-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $saved = $this->saveRow('students', ['student_code' => $code], [
                'institute_id' => $instituteId,
                'admission_branch_id' => $branches[$branchCode],
                'admission_date' => '2025-01-05',
                'full_name' => $name,
                'father_name' => 'Demo Guardian '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'mother_name' => 'Demo Parent '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'date_of_birth' => sprintf('%04d-%02d-%02d', 1994 + ($offset % 8), 1 + ($offset % 12), 1 + ($offset % 27)),
                'gender' => $offset % 2 === 0 ? 'female' : 'male',
                'phone' => '+880000000'.str_pad((string) (1000 + $number), 4, '0', STR_PAD_LEFT),
                'alternative_phone' => null,
                'email' => strtolower($code).'@example.invalid',
                'present_address' => 'Fictional '.['Mirpur', 'Agrabad', 'Zindabazar'][$offset % 3].' area, Bangladesh 0000',
                'permanent_address' => 'Fictional district address, Bangladesh 0000',
                'medical_college_id' => $collegeId,
                'qualification' => 'Fictional medical qualification',
                'graduation_year' => 2017 + ($offset % 10),
                'bmdc_number' => null,
                'photo_path' => null,
                'documents' => json_encode([], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'notes' => 'Fictional Academy development student; workflow status is pending.',
            ]);
            $students[$number] = (int) $saved->id;
        }

        return $students;
    }

    /** @param array<int, int> $students @param array<string, int> $courses @param array<string, object> $batches @param array<string, int> $branches
     *  @return list<array{code:string, student_id:int, course_id:int, batch_id:int, course_code:string, complete:bool, status:string}>
     */
    private function seedEnrollments(array $students, array $courses, array $batches): array
    {
        $courseCodes = array_keys($courses);
        $singleStageCourses = array_values(array_filter($courseCodes, fn (string $code): bool => ! str_contains($code, 'DMU')));
        $primary = [];
        $enrollments = [];
        for ($number = 7; $number <= 200; $number++) {
            $courseCode = $number <= 34
                ? $singleStageCourses[($number - 7) % count($singleStageCourses)]
                : $courseCodes[($number - 7) % count($courseCodes)];
            $batch = $this->primaryBatchForCourse($courseCode, $batches);
            $completed = $number <= 34;
            $status = $completed ? 'completed' : ($number <= 44 ? 'enrolled' : 'ongoing');
            $record = $this->storeEnrollment($number, 1, $students[$number], $courses[$courseCode], $courseCode, $batch, $completed, $status);
            $primary[$number] = $record;
            $enrollments[] = $record;
        }

        for ($number = 7; $number <= 55; $number++) {
            $firstCourse = $primary[$number]['course_code'];
            $sameBranchCourses = array_values(array_filter($courseCodes, function (string $code) use ($firstCourse, $batches): bool {
                return $code !== $firstCourse && $this->primaryBatchForCourse($code, $batches)->branch_id === $this->primaryBatchForCourse($firstCourse, $batches)->branch_id;
            }));
            if ($sameBranchCourses === []) {
                continue;
            }
            $courseCode = $sameBranchCourses[($number - 7) % count($sameBranchCourses)];
            $batch = $this->primaryBatchForCourse($courseCode, $batches);
            $record = $this->storeEnrollment($number, 2, $students[$number], $courses[$courseCode], $courseCode, $batch, false, 'ongoing');
            $enrollments[] = $record;
        }

        return $enrollments;
    }

    /** @param array<string, object> $batches */
    private function primaryBatchForCourse(string $courseCode, array $batches): object
    {
        $primaryCodes = [
            'DEV-COURSE-DMU' => 'DEV-BATCH-DMU-01', 'DEV-COURSE-EMT' => 'DEV-BATCH-EMT-01',
            'DEV-COURSE-BLS' => 'DEV-BATCH-BLS-01', 'DEV-COURSE-REFRESH' => 'DEV-BATCH-REFRESH-01',
            'DEV-ACA-COURSE-CLINICAL-01' => 'DEV-ACA-BATCH-01', 'DEV-ACA-COURSE-EMERGENCY-01' => 'DEV-ACA-BATCH-02',
            'DEV-ACA-COURSE-PATIENT-01' => 'DEV-ACA-BATCH-03', 'DEV-ACA-COURSE-HEALTH-01' => 'DEV-ACA-BATCH-04',
        ];

        return $batches[$primaryCodes[$courseCode]];
    }

    /** @param array{ id:int, course_id:int, branch_id:int, starts_on:string, expected_ends_on:string } $batch
     *  @return array{code:string, student_id:int, course_id:int, batch_id:int, course_code:string, complete:bool, status:string}
     */
    private function storeEnrollment(int $studentNumber, int $sequence, int $studentId, int $courseId, string $courseCode, object $batch, bool $completed, string $status): array
    {
        $code = 'DEV-ACA-ENR-'.str_pad((string) $studentNumber, 3, '0', STR_PAD_LEFT).'-'.(string) $sequence;
        $completedOn = $completed ? $batch->expected_ends_on : null;
        $saved = $this->saveRow('enrollments', ['enrollment_code' => $code], [
            'student_id' => $studentId,
            'course_id' => $courseId,
            'branch_id' => $batch->branch_id,
            'batch_id' => $batch->id,
            'enrollment_date' => '2025-01-10',
            'starts_on' => $batch->starts_on,
            'expected_completion_on' => $batch->expected_ends_on,
            'completion_deadline_on' => date('Y-m-d', strtotime($batch->expected_ends_on.' + 6 months')),
            'completed_on' => $completedOn,
            'status' => $status,
            'is_active' => $status !== 'completed',
            'notes' => "Fictional Academy enrollment; schema-consistent sample. [CMUD-ACADEMY:{$code}]",
        ]);

        return ['id' => (int) $saved->id, 'code' => $code, 'student_id' => $studentId, 'course_id' => $courseId, 'batch_id' => $batch->id, 'course_code' => $courseCode, 'complete' => $completed, 'status' => $status];
    }

    /** @param list<array{code:string, student_id:int, course_id:int, batch_id:int, course_code:string, complete:bool, status:string}> $enrollments @param array<string, int> $stages @param array<string, object> $batches
     *  @return list<array{enrollment:array, id:int}>
     */
    private function seedStageRecords(array $enrollments, array $stages): array
    {
        $result = [];
        foreach (array_slice($enrollments, 0, 116) as $index => $enrollment) {
            $stageCode = $this->firstStageCode($enrollment['course_code']);
            $stageId = $stages[$enrollment['course_id'].':'.$stageCode];
            $batch = DB::table('batches')->where('id', $enrollment['batch_id'])->first();
            $note = $this->scenarioNote('SD-ACA-004-VOLUME-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'Stage and enrollment course are aligned; progression behavior remains pending.');
            $saved = $this->saveRow('student_stage_records', ['notes' => $note], [
                'enrollment_id' => $enrollment['id'],
                'course_stage_id' => $stageId,
                'batch_id' => $enrollment['batch_id'],
                'started_on' => $batch->starts_on,
                'expected_completion_on' => $batch->expected_ends_on,
                'completed_on' => $enrollment['complete'] ? $batch->expected_ends_on : null,
                'status' => $enrollment['complete'] ? 'completed' : ($enrollment['status'] === 'enrolled' ? 'pending' : 'ongoing'),
            ]);
            $result[] = ['enrollment' => $enrollment, 'id' => (int) $saved->id];
        }

        return $result;
    }

    private function firstStageCode(string $courseCode): string
    {
        return match ($courseCode) {
            'DEV-COURSE-DMU' => 'DEV-DMU-STAGE-1',
            'DEV-COURSE-EMT' => 'DEV-EMT-STAGE-1',
            'DEV-COURSE-BLS' => 'DEV-BLS-STAGE-1',
            'DEV-COURSE-REFRESH' => 'DEV-REFRESH-STAGE-1',
            default => 'DEV-ACA-STAGE-'.str_pad((string) (array_search($courseCode, array_column(self::NEW_COURSES, 'code'), true) + 1), 2, '0', STR_PAD_LEFT),
        };
    }

    /** @param list<array{enrollment:array, id:int}> $stageRecords */
    private function seedExams(array $stageRecords): void
    {
        foreach (array_slice($stageRecords, 0, 49) as $index => $stageRecord) {
            if ($stageRecord['enrollment']['status'] === 'enrolled') {
                continue;
            }
            $stage = DB::table('student_stage_records')->where('id', $stageRecord['id'])->first();
            $batch = DB::table('batches')->where('id', $stage->batch_id)->first();
            foreach ([1 => ['failed', '54.00', 'F'], 2 => ['passed', '78.00', 'B']] as $attempt => [$status, $mark, $grade]) {
                $examDate = date('Y-m-d', strtotime($batch->starts_on.' +'.($attempt * 30).' days'));
                $this->saveRow('exam_records', [
                    'student_stage_record_id' => $stageRecord['id'],
                    'exam_name' => 'Academy Development Assessment',
                    'attempt_number' => $attempt,
                ], [
                    'exam_date' => $examDate,
                    'attempt_status' => 'appeared',
                    'roll_number' => 'DEV-ACA-ROLL-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'total_marks' => '100.00',
                    'obtained_marks' => $mark,
                    'grade' => $grade,
                    'result_status' => $status,
                    'result_published_on' => date('Y-m-d', strtotime($examDate.' + 7 days')),
                    'result_file_path' => null,
                    'remarks' => 'Fictional assessment record; scoring rules remain pending.',
                ]);
            }
        }
    }

    /** @param list<array{code:string, student_id:int, course_id:int, batch_id:int, course_code:string, complete:bool, status:string}> $enrollments */
    private function seedCertificates(array $enrollments, int $instituteId): void
    {
        $template = $this->saveRow('certificate_templates', [
            'institute_id' => $instituteId,
            'name' => 'CMUD Academy Development Certificate Template',
            'version_number' => 1,
        ], [
            'course_id' => null,
            'template_file_path' => 'development/templates/academy-demo-certificate.docx',
            'description' => 'Fictional template reference for development data.',
            'is_active' => true,
        ]);

        foreach (array_slice(array_values(array_filter($enrollments, fn (array $row): bool => $row['complete'])), 0, 28) as $index => $enrollment) {
            $number = $index + 1;
            $this->saveRow('certificates', ['certificate_number' => 'DEV-ACA-CERT-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)], [
                'enrollment_id' => $this->findId('enrollments', ['enrollment_code' => $enrollment['code']]),
                'certificate_template_id' => $template->id,
                'recipient_name' => DB::table('students')->where('id', $enrollment['student_id'])->value('full_name'),
                'course_title' => DB::table('courses')->where('id', $enrollment['course_id'])->value('name'),
                'issued_on' => '2026-11-01',
                'certificate_file_path' => null,
                'printed_at' => null,
                'delivered_at' => null,
                'received_by' => null,
                'delivery_method' => null,
                'delivery_reference' => null,
                'status' => 'issued',
                'void_reason' => null,
                'notes' => $this->scenarioNote('SD-ACA-006-VOLUME-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'Issued record tied to a completed schema enrollment; eligibility workflow remains pending.'),
            ]);
        }
    }

    /** @param array<string, int> $courses @param array<string, object> $batches @param array<int, int> $students */
    private function seedGroupsAndMemberships(int $instituteId, array $courses, array $batches, array $students): void
    {
        $groupSpecs = [
            ['name' => 'Academy DMU Development Cohort', 'course' => 'DEV-COURSE-DMU', 'batch' => 'DEV-BATCH-DMU-01'],
            ['name' => 'Academy BLS Development Cohort', 'course' => 'DEV-COURSE-BLS', 'batch' => 'DEV-BATCH-BLS-01'],
            ['name' => 'Academy Refresher Development Cohort', 'course' => 'DEV-COURSE-REFRESH', 'batch' => 'DEV-BATCH-REFRESH-01'],
            ['name' => 'Academy Clinical Skills Development Cohort', 'course' => 'DEV-ACA-COURSE-CLINICAL-01', 'batch' => 'DEV-ACA-BATCH-01'],
            ['name' => 'Academy Emergency Response Development Cohort', 'course' => 'DEV-ACA-COURSE-EMERGENCY-01', 'batch' => 'DEV-ACA-BATCH-02'],
            ['name' => 'Academy Patient Care Development Cohort', 'course' => 'DEV-ACA-COURSE-PATIENT-01', 'batch' => 'DEV-ACA-BATCH-03'],
            ['name' => 'Academy Community Health Development Cohort', 'course' => 'DEV-ACA-COURSE-HEALTH-01', 'batch' => 'DEV-ACA-BATCH-04'],
            ['name' => 'Academy DMU Evening Development Cohort', 'course' => 'DEV-COURSE-DMU', 'batch' => 'DEV-BATCH-DMU-02'],
            ['name' => 'Academy EMT Evening Development Cohort', 'course' => 'DEV-COURSE-EMT', 'batch' => 'DEV-BATCH-EMT-02'],
        ];
        $groupIds = [];
        foreach ($groupSpecs as $spec) {
            $batch = $batches[$spec['batch']];
            $group = $this->saveRow('communication_groups', [
                'institute_id' => $instituteId,
                'name' => $spec['name'],
            ], [
                'branch_id' => $batch->branch_id,
                'course_id' => $courses[$spec['course']],
                'batch_id' => $batch->id,
                'platform' => 'development_demo',
                'group_url' => null,
                'description' => 'Fictional group reference; platform integration is pending.',
                'is_active' => true,
            ]);
            $groupIds[$spec['course']] = $groupIds[$spec['course']] ?? (int) $group->id;
        }

        $group = DB::table('communication_groups')->where('institute_id', $instituteId)->where('name', 'Core Demo Emergency Care Group')->first();
        if ($group !== null) {
            $groupIds['DEV-COURSE-EMT'] = (int) $group->id;
        }
        for ($number = 7; $number <= 124; $number++) {
            $enrollment = DB::table('enrollments')->where('student_id', $students[$number])->orderBy('id')->first();
            $courseCode = array_search((int) $enrollment->course_id, $courses, true);
            $groupId = $groupIds[$courseCode] ?? null;
            if ($groupId === null) {
                continue;
            }
            $note = $this->scenarioNote('SD-ACA-007-VOLUME-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'Membership is linked to a course cohort; eligibility behavior remains pending.');
            $this->saveRow('student_group_memberships', ['notes' => $note], [
                'student_id' => $students[$number],
                'communication_group_id' => $groupId,
                'invited_at' => '2025-01-12 09:00:00',
                'joined_at' => '2025-01-13 09:00:00',
                'left_at' => null,
                'status' => 'joined',
            ]);
        }
    }
}
