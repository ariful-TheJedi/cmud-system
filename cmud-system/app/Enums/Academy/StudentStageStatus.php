<?php

namespace App\Enums\Academy;

enum StudentStageStatus: string
{
    case Pending = 'pending';
    case Ongoing = 'ongoing';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Failed = 'failed';
    case Incomplete = 'incomplete';
}
