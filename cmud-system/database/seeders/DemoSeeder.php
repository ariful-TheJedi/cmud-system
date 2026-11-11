<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            Institute\InstituteDemoSeeder::class,
            Academy\AcademyDemoSeeder::class,
            Finance\FinanceDemoSeeder::class,
            Stock\StockDemoSeeder::class,
        ]);
    }
}
