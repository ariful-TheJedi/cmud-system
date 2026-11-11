<?php

namespace Database\Seeders\Finance;

use App\Models\Finance\PaymentMethod;
use App\Models\Institute\Institute;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Cash', 'requires_transaction_reference' => false, 'requires_sender_phone' => false],
            ['code' => 'bank_transfer', 'name' => 'Bank Transfer', 'requires_transaction_reference' => true, 'requires_sender_phone' => false],
            ['code' => 'mobile_banking', 'name' => 'Mobile Banking', 'requires_transaction_reference' => true, 'requires_sender_phone' => true],
        ];

        Institute::query()->each(function (Institute $institute) use ($methods): void {
            foreach ($methods as $method) {
                PaymentMethod::updateOrCreate(
                    ['code' => $institute->id.'_'.$method['code']],
                    [
                        ...$method,
                        'institute_id' => $institute->id,
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
