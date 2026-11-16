<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class CoreDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Core development data may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $this->call([
                CoreAccessSeeder::class,
                CoreInstituteSeeder::class,
                InstituteVolumeSeeder::class,
                AcademyFoundationSeeder::class,
                CoreFinanceSeeder::class,
                FinanceVolumeSeeder::class,
            ]);
        });
    }
}
