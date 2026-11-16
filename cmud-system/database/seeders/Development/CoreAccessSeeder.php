<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class CoreAccessSeeder extends Seeder
{
    use UsesCoreSeedData;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Core access development data may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $this->seedAccessFoundation();
        });
    }

    private function seedAccessFoundation(): void
    {
        $operatorGroup = $this->saveRow('permission_groups', ['name' => 'CMUD Core Demo Operator'], [
            'description' => 'Development-only group for the core seed scenarios.',
        ]);
        $restrictedGroup = $this->saveRow('permission_groups', ['name' => 'CMUD Core Demo Restricted'], [
            'description' => 'Development-only restricted profile for pending access scenarios.',
        ]);

        $this->seedUser('core.operator@example.invalid', [
            'name' => 'Demo Operator',
            'phone' => '+880000000001',
            'designation' => 'Development Operator',
            'email_verified_at' => null,
            'is_active' => true,
            'is_super_admin' => false,
            'permission_group_id' => $operatorGroup->id,
        ]);

        $this->seedUser('core.restricted@example.invalid', [
            'name' => 'Demo Restricted User',
            'phone' => '+880000000002',
            'designation' => 'Development User',
            'email_verified_at' => null,
            'is_active' => true,
            'is_super_admin' => false,
            'permission_group_id' => $restrictedGroup->id,
        ]);

        // There are no implemented canonical permissions or protected module routes.
        // Keep the permission table empty rather than inventing permission names.
        // Sessions, password-reset tokens and audit logs are runtime/event-owned tables;
        // leave them empty until the owning features exist.
    }
}
