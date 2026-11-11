<?php

namespace Tests\Unit\CollaboratingClasses\Finance;

use PHPUnit\Framework\TestCase;

class TuitionCalculationTest extends TestCase
{
    public function test_payable_tuition_is_original_amount_less_waiver(): void
    {
        $originalAmount = 18000.00;
        $waiverAmount = 3000.00;
        $payments = [4000.00, 2500.00];

        $payableAmount = $originalAmount - $waiverAmount;
        $balance = $payableAmount - array_sum($payments);

        $this->assertSame(15000.00, $payableAmount);
        $this->assertSame(8500.00, $balance);
    }

    public function test_balance_is_zero_when_payments_cover_payable_tuition(): void
    {
        $payableAmount = 9000.00;
        $payments = [4000.00, 5000.00];

        $balance = max(0.00, $payableAmount - array_sum($payments));

        $this->assertSame(0.00, $balance);
    }
}
