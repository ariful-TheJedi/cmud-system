<?php

namespace App\Enums\Academy;

enum CertificateStatus: string
{
    case NotEligible = 'not_eligible';
    case EligibilityPending = 'eligibility_pending';
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Issued = 'issued';
    case Delivered = 'delivered';
}
