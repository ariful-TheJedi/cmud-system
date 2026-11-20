<?php

namespace App\Enums\Academy;

enum EnrollmentStatus: string
{
    case Enrolled = 'enrolled';
    case Ongoing = 'ongoing';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Discontinued = 'discontinued';
    case Cancelled = 'cancelled';
}
