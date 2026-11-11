<?php

namespace Tests\Unit\SingleClass\Finance;

use PHPUnit\Framework\TestCase;

class WaiverCalculatorTest extends TestCase
{
    public function test_waiver_cannot_exceed_original_tuition(): void
    {
        $originalAmount = 12000.00;
        $requestedWaiver = 15000.00;

        $waiverAmount = min($requestedWaiver, $originalAmount);
        $payableAmount = $originalAmount - $waiverAmount;

        $this->assertSame(12000.00, $waiverAmount);
        $this->assertSame(0.00, $payableAmount);
    }

    public function test_partial_waiver_leaves_the_remaining_amount_payable(): void
    {
        $originalAmount = 12000.00;
        $waiverAmount = 2500.00;

        $this->assertSame(9500.00, $originalAmount - $waiverAmount);
    }
}
