<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            Security\PermissionSeeder::class,
            Institute\InstituteSetupSeeder::class,
            Academy\AcademySetupSeeder::class,
            Finance\PaymentMethodSeeder::class,
        ]);
    }
}
