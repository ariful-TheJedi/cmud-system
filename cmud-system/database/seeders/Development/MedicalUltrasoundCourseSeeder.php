<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Seed a realistic, repeatable course structure for course management UI work. */
class MedicalUltrasoundCourseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Medical ultrasound demo data may only be seeded in local or testing environments.');
        }

        $instituteId = DB::table('institutes')->where('code', 'CMUD-DEV-CORE')->value('id');
        if ($instituteId === null) {
            throw new LogicException('Seed CMUD-DEV-CORE institute data before running this course seeder.');
        }

        $courseId = $this->saveCourse((int) $instituteId);
        $stageIds = $this->saveStages($courseId);
        $this->saveModules($courseId, $stageIds);
    }

    private function saveCourse(int $instituteId): int
    {
        $now = now();
        DB::table('courses')->updateOrInsert(
            ['code' => 'DEV-DMU-COURSE-001'],
            [
                'institute_id' => $instituteId,
                'name' => 'Diploma in Medical Ultrasound',
                'description' => 'Fictional three-semester diploma programme for Academy course setup and management development.',
                'duration_months' => 23,
                'completion_period_months' => 30,
                'course_mode' => 'offline',
                'is_active' => true,
                'status' => 'active',
                'is_available_for_enrollment' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return (int) DB::table('courses')->where('code', 'DEV-DMU-COURSE-001')->value('id');
    }

    /** @return array<int, int> Semester number keyed to persisted stage ID. */
    private function saveStages(int $courseId): array
    {
        $specifications = [
            1 => ['name' => 'Semester 1', 'code' => 'DEV-DMU-SEM-01', 'duration' => 12, 'certificate' => true],
            2 => ['name' => 'Semester 2', 'code' => 'DEV-DMU-SEM-02', 'duration' => 5, 'certificate' => false],
            3 => ['name' => 'Semester 3', 'code' => 'DEV-DMU-SEM-03', 'duration' => 6, 'certificate' => true],
        ];

        $stageIds = [];
        foreach ($specifications as $sequence => $stage) {
            $now = now();
            DB::table('course_stages')->updateOrInsert(
                ['course_id' => $courseId, 'code' => $stage['code']],
                [
                    'name' => $stage['name'],
                    'sequence_number' => $sequence,
                    'duration_months' => $stage['duration'],
                    'description' => 'Fictional semester stage for the Diploma in Medical Ultrasound.',
                    'is_active' => true,
                    'is_mandatory' => true,
                    'requires_exam' => true,
                    'certificate_enabled' => $stage['certificate'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $stageIds[$sequence] = (int) DB::table('course_stages')
                ->where('course_id', $courseId)
                ->where('code', $stage['code'])
                ->value('id');
        }

        return $stageIds;
    }

    /** @param array<int, int> $stageIds */
    private function saveModules(int $courseId, array $stageIds): void
    {
        $moduleNames = [
            1 => [
                'Ultrasound Physics and Instrumentation', 'Anatomy for Medical Ultrasound',
                'Patient Care and Professional Practice', 'Introduction to Diagnostic Imaging',
                'Abdominal Ultrasound I', 'Obstetric Ultrasound I', 'Ultrasound Equipment and Safety',
                'Cross-Sectional Anatomy', 'Clinical Communication', 'Image Acquisition Fundamentals',
                'Infection Prevention in Imaging', 'Semester 1 Clinical Skills',
            ],
            2 => [
                'Abdominal Ultrasound II', 'Obstetric Ultrasound II', 'Gynaecological Ultrasound',
                'Vascular Ultrasound Fundamentals', 'Semester 2 Clinical Practicum',
            ],
            3 => [
                'Advanced Obstetric Ultrasound', 'Advanced Vascular Ultrasound',
                'Small Parts and Musculoskeletal Ultrasound', 'Ultrasound Pathology and Case Review',
                'Research Methods and Evidence-Based Imaging', 'Advanced Clinical Practicum',
            ],
        ];

        foreach ($moduleNames as $semester => $names) {
            foreach ($names as $index => $name) {
                $code = sprintf('DEV-DMU-MOD-%02d', $this->moduleNumber($semester, $index));
                $now = now();
                DB::table('subjects')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'description' => 'Fictional development module assigned to '.$this->semesterName($semester).'.',
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
                $subjectId = (int) DB::table('subjects')->where('code', $code)->value('id');

                // Insert only missing assignments so rerunning the seeder preserves timestamps.
                DB::table('course_stage_subject')->insertOrIgnore([
                    'course_stage_id' => $stageIds[$semester],
                    'subject_id' => $subjectId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function moduleNumber(int $semester, int $index): int
    {
        return match ($semester) {
            1 => $index + 1,
            2 => $index + 13,
            default => $index + 18,
        };
    }

    private function semesterName(int $semester): string
    {
        return 'Semester '.$semester;
    }
}
