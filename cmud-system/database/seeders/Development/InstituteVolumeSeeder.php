<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class InstituteVolumeSeeder extends Seeder
{
    use UsesCoreSeedData;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Institute volume data may only be seeded in local or testing environments.');
        }

        $instituteId = $this->findId('institutes', ['code' => 'CMUD-DEV-CORE']);
        $operatorId = $this->findId('users', ['email' => 'core.operator@example.invalid']);
        $branches = [];
        foreach (['DEV-BR-DHAKA', 'DEV-BR-CHITTAGONG', 'DEV-BR-SYLHET'] as $code) {
            $branches[$code] = $this->findId('branches', ['institute_id' => $instituteId, 'code' => $code]);
        }

        DB::transaction(function () use ($instituteId, $operatorId, $branches): void {
            $this->seedRooms($branches);
            $staff = $this->seedStaff($instituteId, $branches);
            $this->seedAssignments($staff, $branches, $instituteId);
            $categories = $this->seedAssetCategories($instituteId);
            $this->seedAssets($categories, $branches);
            $stockCategories = $this->seedStockCategories();
            $this->seedStocks($stockCategories, $branches, $operatorId);
        });
    }

    /** @param array<string, int> $branches */
    private function seedRooms(array $branches): void
    {
        $rooms = [
            ['code' => 'DEV-RM-DHAKA-03', 'branch' => 'DEV-BR-DHAKA', 'name' => 'Demo Seminar Room', 'type' => 'seminar_room', 'floor' => '3', 'capacity' => 24],
            ['code' => 'DEV-RM-CTG-02', 'branch' => 'DEV-BR-CHITTAGONG', 'name' => 'Demo Skills Room', 'type' => 'laboratory', 'floor' => '2', 'capacity' => 20],
            ['code' => 'DEV-RM-SYLHET-01', 'branch' => 'DEV-BR-SYLHET', 'name' => 'Demo Tutorial Room', 'type' => 'classroom', 'floor' => '1', 'capacity' => 26],
        ];

        foreach ($rooms as $room) {
            $this->saveRow('rooms', ['branch_id' => $branches[$room['branch']], 'code' => $room['code']], [
                'name' => $room['name'],
                'room_type' => $room['type'],
                'floor' => $room['floor'],
                'capacity' => $room['capacity'],
                'description' => 'Fictional additional training space for development demonstrations.',
                'is_active' => true,
            ]);
        }
    }

    /** @param array<string, int> $branches
     *  @return array<int, int>
     */
    private function seedStaff(int $instituteId, array $branches): array
    {
        $people = [
            ['Nadia Karim', 'Academic Coordinator'], ['Ruhul Amin', 'Office Administrator'],
            ['Sharmeen Akter', 'Student Services Officer'], ['Faisal Ahmed', 'Accounts Assistant'],
            ['Tania Sultana', 'Operations Officer'], ['Mahbub Hasan', 'Facilities Officer'],
            ['Rumana Islam', 'Admissions Officer'], ['Jubayer Hossain', 'Records Assistant'],
            ['Lamia Rahman', 'Programme Coordinator'], ['Sabbir Chowdhury', 'Support Officer'],
        ];
        $branchCodes = array_keys($branches);
        $staffIds = [];
        foreach ($people as $index => [$name, $designation]) {
            $number = $index + 3;
            $code = 'DEV-STAFF-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $saved = $this->saveRow('staff', ['staff_code' => $code], [
                'institute_id' => $instituteId,
                'user_id' => null,
                'full_name' => $name,
                'phone' => '+88000000'.str_pad((string) (2000 + $number), 5, '0', STR_PAD_LEFT),
                'email' => 'institute.staff.'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'@example.invalid',
                'nid_number' => null,
                'address' => 'Fictional '.['Dhaka', 'Chattogram', 'Sylhet'][$index % 3].' address, Bangladesh 0000',
                'date_of_birth' => sprintf('%04d-%02d-%02d', 1985 + ($index % 8), 1 + ($index % 12), 3 + ($index % 24)),
                'photo_path' => null,
                'designation' => $designation,
                'joining_date' => sprintf('2024-%02d-01', 1 + ($index % 12)),
                'leaving_date' => null,
                'employment_status' => 'active',
                'notes' => 'Fictional staff profile; assignment lifecycle rules are pending.',
            ]);
            $staffIds[$number] = (int) $saved->id;
        }

        return $staffIds;
    }

    /** @param array<int, int> $staff @param array<string, int> $branches */
    private function seedAssignments(array $staff, array $branches, int $instituteId): void
    {
        $departments = [];
        foreach (['DEV-DEPT-ACADEMY', 'DEV-DEPT-ADMIN', 'DEV-DEPT-FINANCE', 'DEV-DEPT-OPERATIONS'] as $code) {
            $departments[] = $this->findId('departments', ['institute_id' => $instituteId, 'code' => $code]);
        }
        $branchIds = array_values($branches);
        foreach ($staff as $offset => $staffId) {
            $this->saveRow('staff_assignments', [
                'staff_id' => $staffId,
                'branch_id' => $branchIds[$offset % count($branchIds)],
                'department_id' => $departments[$offset % count($departments)],
            ], []);
        }
    }

    /** @return array<int, int> */
    private function seedAssetCategories(int $instituteId): array
    {
        $categories = [$this->findId('asset_categories', ['institute_id' => $instituteId, 'code' => 'DEV-ASSET-CAT-EQUIPMENT'])];
        foreach ([
            ['DEV-ASSET-CAT-FURNITURE', 'Demo Furniture'],
            ['DEV-ASSET-CAT-IT', 'Demo Information Technology'],
            ['DEV-ASSET-CAT-CLINICAL', 'Demo Clinical Equipment'],
        ] as [$code, $name]) {
            $saved = $this->saveRow('asset_categories', ['institute_id' => $instituteId, 'code' => $code], [
                'name' => $name,
                'description' => 'Fictional asset category for the development dataset.',
                'is_active' => true,
            ]);
            $categories[] = (int) $saved->id;
        }

        return $categories;
    }

    /** @param array<int, int> $categories @param array<string, int> $branches */
    private function seedAssets(array $categories, array $branches): void
    {
        $rooms = [
            'DEV-BR-DHAKA' => $this->findId('rooms', ['branch_id' => $branches['DEV-BR-DHAKA'], 'code' => 'DEV-RM-DHAKA-01']),
            'DEV-BR-CHITTAGONG' => $this->findId('rooms', ['branch_id' => $branches['DEV-BR-CHITTAGONG'], 'code' => 'DEV-RM-CTG-01']),
            'DEV-BR-SYLHET' => $this->findId('rooms', ['branch_id' => $branches['DEV-BR-SYLHET'], 'code' => 'DEV-RM-SYLHET-01']),
        ];
        $branchCodes = array_keys($branches);
        for ($number = 2; $number <= 12; $number++) {
            $index = $number - 2;
            $branchCode = $branchCodes[$index % count($branchCodes)];
            $this->saveRow('assets', ['asset_code' => 'DEV-ASSET-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)], [
                'asset_category_id' => $categories[$index % count($categories)],
                'branch_id' => $branches[$branchCode],
                'room_id' => $rooms[$branchCode],
                'name' => ['Training Table', 'Desktop Computer', 'Examination Lamp', 'Projector', 'Clinical Monitor', 'Filing Cabinet', 'Training Chair', 'Portable Screen', 'Procedure Trolley', 'Network Printer', 'Practice Manikin'][$index],
                'brand' => 'Fictional Demo Equipment',
                'model' => 'ACADEMY-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'serial_number' => 'DEV-ASSET-SERIAL-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'purchase_date' => sprintf('2025-%02d-15', 1 + ($index % 12)),
                'purchase_cost' => (string) (5000 + $number * 1250).'.00',
                'unit' => 'piece',
                'warranty_expires_on' => sprintf('2027-%02d-15', 1 + ($index % 12)),
                'condition' => 'good',
                'status' => 'active',
                'notes' => 'Fictional asset for development inventory scenarios.',
            ]);
        }
    }

    /** @return array<int, int> */
    private function seedStockCategories(): array
    {
        $categories = [$this->findId('stock_categories', ['name' => 'CMUD Core Demo Supplies'])];
        foreach ([
            'CMUD Development Office Supplies',
            'CMUD Development Clinical Materials',
            'CMUD Development Teaching Materials',
        ] as $name) {
            $saved = $this->saveRow('stock_categories', ['name' => $name], [
                'description' => 'Fictional inventory category for development records.',
                'is_active' => true,
            ]);
            $categories[] = (int) $saved->id;
        }

        return $categories;
    }

    /** @param array<int, int> $categories @param array<string, int> $branches */
    private function seedStocks(array $categories, array $branches, int $operatorId): void
    {
        $branchCodes = array_keys($branches);
        $items = [
            'A4 Copy Paper', 'Exam Answer Booklets', 'Disposable Gloves', 'Practice Bandages',
            'Whiteboard Markers', 'Student File Folders', 'Sanitizer Bottles', 'Clinical Masks', 'Training Notebooks',
        ];
        foreach ($items as $index => $itemName) {
            $number = $index + 2;
            $stock = $this->saveRow('stocks', ['item_code' => 'DEV-STOCK-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)], [
                'branch_id' => $branches[$branchCodes[$index % count($branchCodes)]],
                'item_name' => $itemName,
                'unit' => $index % 2 === 0 ? 'pack' : 'piece',
                'quantity_on_hand' => '110.000',
                'reorder_level' => '10.000',
                'notes' => 'Fictional stock item; quantity agrees with the opening, receipt, and issue records.',
            ]);
            $stockId = (int) $stock->id;
            $priceIds = [];
            foreach ([
                ['version' => 1, 'cost' => (string) (90 + $index * 7).'.00', 'from' => '2025-01-01', 'to' => '2025-12-31'],
                ['version' => 2, 'cost' => (string) (100 + $index * 8).'.00', 'from' => '2026-01-01', 'to' => null],
            ] as $price) {
                $saved = $this->saveRow('stock_price_versions', ['stock_id' => $stockId, 'version_number' => $price['version']], [
                    'stock_category_id' => $categories[$index % count($categories)],
                    'unit_cost' => $price['cost'],
                    'effective_from' => $price['from'],
                    'effective_to' => $price['to'],
                    'status' => 'approved',
                    'approved_by' => $operatorId,
                    'approved_at' => self::SEEDED_AT,
                    'notes' => 'Non-overlapping fictional stock price version.',
                ]);
                $priceIds[$price['version']] = (int) $saved->id;
            }

            foreach ([
                ['key' => 'OPENING', 'type' => 'opening_balance', 'quantity' => '100.000', 'price' => 1, 'date' => '2025-01-10 09:00:00', 'cost' => (string) (85 + $index * 7).'.00'],
                ['key' => 'RECEIPT', 'type' => 'receipt', 'quantity' => '20.000', 'price' => 1, 'date' => '2025-06-10 09:00:00', 'cost' => (string) (90 + $index * 7).'.00'],
                ['key' => 'ISSUE', 'type' => 'issue', 'quantity' => '10.000', 'price' => 2, 'date' => '2026-02-10 09:00:00', 'cost' => (string) (100 + $index * 8).'.00'],
            ] as $movement) {
                $note = $this->scenarioNote('INS-VOL-STOCK-'.$number.'-'.$movement['key'], 'Fictional inventory event; movement types remain application-defined.');
                $this->saveRow('stock_movements', ['notes' => $note], [
                    'stock_id' => $stockId,
                    'movement_type' => $movement['type'],
                    'quantity' => $movement['quantity'],
                    'stock_price_version_id' => $priceIds[$movement['price']],
                    'unit_cost' => $movement['cost'],
                    'reason' => 'Fictional development stock event',
                    'movement_date' => $movement['date'],
                ]);
            }
        }
    }
}
