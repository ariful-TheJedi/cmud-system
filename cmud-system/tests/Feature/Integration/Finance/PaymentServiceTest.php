<?php

namespace Tests\Feature\Integration\Finance;

use App\Models\Finance\StudentTuitionPayment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\TestCase;

class PaymentServiceTest extends TestCase
{
    public function test_tuition_payment_model_exposes_its_tuition_and_payment_method_relationships(): void
    {
        $payment = new StudentTuitionPayment;

        $this->assertInstanceOf(BelongsTo::class, $payment->tuition());
        $this->assertInstanceOf(BelongsTo::class, $payment->paymentMethod());
        $this->assertInstanceOf(BelongsTo::class, $payment->financialAccount());
    }

    public function test_payment_amount_is_cast_to_a_decimal(): void
    {
        $payment = new StudentTuitionPayment(['amount' => 1250]);

        $this->assertSame('1250.00', $payment->amount);
    }
}
