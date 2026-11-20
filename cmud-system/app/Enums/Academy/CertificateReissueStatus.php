<?php

namespace App\Enums\Academy;

enum CertificateReissueStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Issued = 'issued';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
