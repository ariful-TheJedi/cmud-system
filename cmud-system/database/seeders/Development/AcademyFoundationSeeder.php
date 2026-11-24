<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class AcademyFoundationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Academy development data may only be seeded in local or testing environments.');
        }

        $missing = [];
        $instituteId = DB::table('institutes')->where('code', 'CMUD-DEV-CORE')->value('id');
        if ($instituteId === null) {
            $missing[] = 'institute code CMUD-DEV-CORE (run the Institute foundation seeder first)';
        }

        foreach (['DEV-BR-DHAKA', 'DEV-BR-CHITTAGONG', 'DEV-BR-SYLHET'] as $branchCode) {
            if ($instituteId !== null && ! DB::table('branches')->where('institute_id', $instituteId)->where('code', $branchCode)->exists()) {
                $missing[] = "branch code {$branchCode}";
            }
        }

        if (! DB::table('users')->where('email', 'core.operator@example.invalid')->exists()) {
            $missing[] = 'development approver core.operator@example.invalid (run the Access foundation seeder first)';
        }

        if (! DB::table('stuff')->where('stuff_code', 'DEV-STUFF-002')->exists()) {
            $missing[] = 'stuff code DEV-STUFF-002 (run the Institute foundation seeder first)';
        }

        if ($missing !== []) {
            throw new RuntimeException('Academy foundation prerequisites are missing: '.implode('; ', $missing).'.');
        }

        DB::transaction(function (): void {
            $this->call([
                CoreAcademySeeder::class,
                AcademyVolumeSeeder::class,
            ]);
        });
    }
}
