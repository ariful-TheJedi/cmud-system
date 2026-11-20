<?php

namespace App\Enums\Academy;

enum StudentStatus: string
{
    case Registered = 'registered';
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Suspended = 'suspended';
}
