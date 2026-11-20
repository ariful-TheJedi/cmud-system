<?php

namespace App\Enums\Academy;

enum ExamResultStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Incomplete = 'incomplete';
}
