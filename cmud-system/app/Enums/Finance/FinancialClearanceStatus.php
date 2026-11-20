<?php

namespace App\Enums\Finance;

enum FinancialClearanceStatus: string
{
    case NotCleared = 'not_cleared';
    case Cleared = 'cleared';
    case UnderReview = 'under_review';
}
