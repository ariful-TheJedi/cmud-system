<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;

class CoreInstituteSeeder extends Seeder
{
    use UsesCoreSeedData;

    public function run(): void
    {
        $operatorId = $this->findId('users', ['email' => 'core.operator@example.invalid']);
        $institute = $this->saveRow('institutes', ['code' => 'CMUD-DEV-CORE'], [
            'name' => 'CMUD Core Development Institute',
            'phone' => '+880000000010',
            'email' => 'institute@example.invalid',
            'tin_number' => null,
            'bin_number' => 'DEV-CORE-BIN-001',
            'website' => 'https://example.invalid/cmud-core',
            'address' => 'Demo Campus, Sample Road, Dhaka 0000',
            'logo_path' => null,
            'is_active' => true,
        ]);
        $instituteId = (int) $institute->id;

        $branchIds = [];
        foreach ([
            ['code' => 'DEV-BR-DHAKA', 'name' => 'Dhaka Demo Branch', 'phone' => '+880000000011', 'address' => 'Demo Road, Dhaka 0000'],
            ['code' => 'DEV-BR-CHITTAGONG', 'name' => 'Chattogram Demo Branch', 'phone' => '+880000000012', 'address' => 'Sample Avenue, Chattogram 0000'],
            ['code' => 'DEV-BR-SYLHET', 'name' => 'Sylhet Demo Branch', 'phone' => '+880000000013', 'address' => 'Fictional Lane, Sylhet 0000'],
        ] as $branch) {
            $saved = $this->saveRow('branches', [
                'institute_id' => $instituteId,
                'code' => $branch['code'],
            ], [
                'name' => $branch['name'],
                'phone' => $branch['phone'],
                'email' => strtolower($branch['code']).'@example.invalid',
                'address' => $branch['address'],
                'is_active' => true,
            ]);
            $branchIds[$branch['code']] = (int) $saved->id;
        }

        $departmentIds = [];
        foreach ([
            ['code' => 'DEV-DEPT-ACADEMY', 'name' => 'Demo Academy'],
            ['code' => 'DEV-DEPT-ADMIN', 'name' => 'Demo Administration'],
            ['code' => 'DEV-DEPT-FINANCE', 'name' => 'Demo Finance'],
            ['code' => 'DEV-DEPT-OPERATIONS', 'name' => 'Demo Operations'],
        ] as $department) {
            $saved = $this->saveRow('departments', [
                'institute_id' => $instituteId,
                'code' => $department['code'],
            ], [
                'name' => $department['name'],
                'description' => 'Fictional department for the CMUD core development dataset.',
                'is_active' => true,
            ]);
            $departmentIds[$department['code']] = (int) $saved->id;
        }

        $roomIds = [];
        foreach ([
            ['code' => 'DEV-RM-DHAKA-01', 'branch' => 'DEV-BR-DHAKA', 'name' => 'Demo Lecture Room', 'type' => 'classroom', 'floor' => '1', 'capacity' => 30],
            ['code' => 'DEV-RM-DHAKA-02', 'branch' => 'DEV-BR-DHAKA', 'name' => 'Demo Practical Room', 'type' => 'laboratory', 'floor' => '2', 'capacity' => 18],
            ['code' => 'DEV-RM-CTG-01', 'branch' => 'DEV-BR-CHITTAGONG', 'name' => 'Chattogram Demo Room', 'type' => 'classroom', 'floor' => '1', 'capacity' => 24],
        ] as $room) {
            $saved = $this->saveRow('rooms', [
                'branch_id' => $branchIds[$room['branch']],
                'code' => $room['code'],
            ], [
                'name' => $room['name'],
                'room_type' => $room['type'],
                'floor' => $room['floor'],
                'capacity' => $room['capacity'],
                'description' => 'Fictional room for development demonstrations.',
                'is_active' => true,
            ]);
            $roomIds[$room['code']] = (int) $saved->id;
        }

        $stuffRows = [
            ['code' => 'DEV-STUFF-001', 'name' => 'Ayesha Rahman', 'designation' => 'Development Coordinator', 'user' => $operatorId],
            ['code' => 'DEV-STUFF-002', 'name' => 'Farhan Ahmed', 'designation' => 'Office Assistant', 'user' => null],
        ];
        $stuffIds = [];
        foreach ($stuffRows as $stuff) {
            $saved = $this->saveRow('stuff', ['stuff_code' => $stuff['code']], [
                'institute_id' => $instituteId,
                'user_id' => $stuff['user'],
                'full_name' => $stuff['name'],
                'phone' => '+880000000020',
                'email' => strtolower($stuff['code']).'@example.invalid',
                'nid_number' => null,
                'address' => 'Fictional address, Dhaka 0000',
                'date_of_birth' => '1990-01-01',
                'photo_path' => null,
                'designation' => $stuff['designation'],
                'joining_date' => '2024-01-01',
                'leaving_date' => null,
                'employment_status' => 'active',
                'notes' => 'Fictional stuff record for development scenarios.',
            ]);
            $stuffIds[$stuff['code']] = (int) $saved->id;
        }

        foreach ([
            ['stuff' => 'DEV-STUFF-001', 'branch' => 'DEV-BR-DHAKA', 'department' => 'DEV-DEPT-ADMIN'],
            ['stuff' => 'DEV-STUFF-002', 'branch' => 'DEV-BR-CHITTAGONG', 'department' => 'DEV-DEPT-OPERATIONS'],
        ] as $assignment) {
            $this->saveRow('stuff_assignments', [
                'stuff_id' => $stuffIds[$assignment['stuff']],
                'branch_id' => $branchIds[$assignment['branch']],
                'department_id' => $departmentIds[$assignment['department']],
            ], []);
        }

        $assetCategory = $this->saveRow('asset_categories', [
            'institute_id' => $instituteId,
            'code' => 'DEV-ASSET-CAT-EQUIPMENT',
        ], [
            'name' => 'Demo Training Equipment',
            'description' => 'Fictional equipment category for seed scenarios.',
            'is_active' => true,
        ]);

        $this->saveRow('assets', ['asset_code' => 'DEV-ASSET-001'], [
            'asset_category_id' => $assetCategory->id,
            'branch_id' => $branchIds['DEV-BR-DHAKA'],
            'room_id' => $roomIds['DEV-RM-DHAKA-02'],
            'name' => 'Demo Training Manikin',
            'brand' => 'Fictional Demo Brand',
            'model' => 'CORE-1',
            'serial_number' => 'DEV-SERIAL-001',
            'purchase_date' => '2025-02-01',
            'purchase_cost' => 18000,
            'unit' => 'piece',
            'warranty_expires_on' => '2027-02-01',
            'condition' => 'good',
            'status' => 'active',
            'notes' => $this->scenarioNote('SD-INS-003', 'Fictional training asset.'),
        ]);

        $stockCategory = $this->saveRow('stock_categories', ['name' => 'CMUD Core Demo Supplies'], [
            'description' => 'Fictional stock category for development scenarios.',
            'is_active' => true,
        ]);
        $stock = $this->saveRow('stocks', ['item_code' => 'DEV-STOCK-001'], [
            'branch_id' => $branchIds['DEV-BR-DHAKA'],
            'item_name' => 'Demo Examination Paper Ream',
            'unit' => 'ream',
            'quantity_on_hand' => '120.000',
            'reorder_level' => '10.000',
            'notes' => $this->scenarioNote('SD-INS-004', 'Illustrative current quantity; movement rules are pending.'),
        ]);
        $stockId = (int) $stock->id;

        foreach ([
            ['version' => 1, 'cost' => '100.00', 'from' => '2025-01-01', 'to' => '2025-12-31', 'scenario' => 'SD-INS-006'],
            ['version' => 2, 'cost' => '125.00', 'from' => '2026-01-01', 'to' => null, 'scenario' => 'SD-INS-006'],
        ] as $price) {
            $this->saveRow('stock_price_versions', [
                'stock_id' => $stockId,
                'version_number' => $price['version'],
            ], [
                'stock_category_id' => $stockCategory->id,
                'unit_cost' => $price['cost'],
                'effective_from' => $price['from'],
                'effective_to' => $price['to'],
                'status' => 'approved',
                'approved_by' => $operatorId,
                'approved_at' => self::SEEDED_AT,
                'notes' => $this->scenarioNote($price['scenario'], 'Non-overlapping illustrative stock price period.'),
            ]);
        }

        $stockPriceOne = $this->findId('stock_price_versions', ['stock_id' => $stockId, 'version_number' => 1]);
        $stockPriceTwo = $this->findId('stock_price_versions', ['stock_id' => $stockId, 'version_number' => 2]);
        foreach ([
            ['key' => 'SD-INS-004-OPENING', 'type' => 'opening_balance', 'quantity' => '100.000', 'price' => $stockPriceOne, 'date' => '2025-12-01 09:00:00', 'cost' => '95.00', 'reason' => 'Illustrative opening quantity'],
            ['key' => 'SD-INS-004-RECEIPT', 'type' => 'receipt', 'quantity' => '50.000', 'price' => $stockPriceOne, 'date' => '2026-02-01 09:00:00', 'cost' => '100.00', 'reason' => 'Fictional supplier receipt'],
            ['key' => 'SD-INS-005-ISSUE', 'type' => 'issue', 'quantity' => '30.000', 'price' => $stockPriceTwo, 'date' => '2026-03-01 09:00:00', 'cost' => '125.00', 'reason' => 'Fictional class issue'],
        ] as $movement) {
            $note = $this->scenarioNote($movement['key'], 'Core stock movement; semantics remain application-pending.');
            $this->saveRow('stock_movements', ['notes' => $note], [
                'stock_id' => $stockId,
                'movement_type' => $movement['type'],
                'quantity' => $movement['quantity'],
                'stock_price_version_id' => $movement['price'],
                'unit_cost' => $movement['cost'],
                'reason' => $movement['reason'],
                'movement_date' => $movement['date'],
            ]);
        }
    }
}
