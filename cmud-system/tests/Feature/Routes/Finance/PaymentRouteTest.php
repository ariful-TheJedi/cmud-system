<?php

namespace Tests\Feature\Routes\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_payment_endpoints_are_not_registered_until_the_module_route_is_implemented(): void
    {
        $this->getJson('/api/v1/finance/payments')->assertNotFound();
    }
}
