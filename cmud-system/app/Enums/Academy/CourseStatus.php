<?php

namespace App\Enums\Academy;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Discontinued = 'discontinued';
}
