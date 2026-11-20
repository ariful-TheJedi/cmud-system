<?php

namespace App\Enums\Academy;

enum ExamAttemptStatus: string
{
    case Scheduled = 'scheduled';
    case Appeared = 'appeared';
    case Absent = 'absent';
    case Cancelled = 'cancelled';
}
