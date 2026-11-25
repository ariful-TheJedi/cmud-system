<?php

namespace Tests\Support;

use App\Models\Academy\Enrollment;

/** Build enrollment tuition fixtures directly, without a separate tuition model or factories. */
class EnrollmentTuition
{
    /** @param array<string, mixed> $attributes */
    public static function set(array $attributes): Enrollment
    {
        $enrollment = Enrollment::query()->findOrFail($attributes['enrollment_id']);
        unset($attributes['enrollment_id']);
        $values = [];
        foreach ($attributes as $key => $value) {
            $column = in_array($key, ['tuition_number', 'course_price_version_id'], true)
                ? $key : 'tuition_'.$key;
            $values[$column] = $value;
        }
        $enrollment->update($values);

        return $enrollment->refresh();
    }
}
