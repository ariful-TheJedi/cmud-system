<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('code');
            $table->string('name');
            $table->string('room_type');
            $table->string('floor')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('staff_code');
            $table->string('full_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('designation');
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->string('employment_status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('department_id');
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_category_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('asset_code');
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->string('condition');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('item_code');
            $table->string('item_name');
            $table->string('unit');
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_id');
            $table->string('movement_type');
            $table->decimal('quantity', 12, 2);
            $table->string('reason');
            $table->dateTime('movement_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(
                'CREATE TRIGGER stocks_quantity_on_hand_nonnegative_insert '
                .'BEFORE INSERT ON stocks WHEN NEW.quantity_on_hand < 0 '
                .'BEGIN SELECT RAISE(ABORT, "quantity_on_hand cannot be negative"); END'
            );
            DB::unprepared(
                'CREATE TRIGGER stocks_quantity_on_hand_nonnegative_update '
                .'BEFORE UPDATE OF quantity_on_hand ON stocks WHEN NEW.quantity_on_hand < 0 '
                .'BEGIN SELECT RAISE(ABORT, "quantity_on_hand cannot be negative"); END'
            );
            DB::unprepared(
                'CREATE TRIGGER stock_movements_quantity_positive_insert '
                .'BEFORE INSERT ON stock_movements WHEN NEW.quantity <= 0 '
                .'BEGIN SELECT RAISE(ABORT, "quantity must be greater than zero"); END'
            );
            DB::unprepared(
                'CREATE TRIGGER stock_movements_quantity_positive_update '
                .'BEFORE UPDATE OF quantity ON stock_movements WHEN NEW.quantity <= 0 '
                .'BEGIN SELECT RAISE(ABORT, "quantity must be greater than zero"); END'
            );
        } else {
            DB::statement(
                'ALTER TABLE stocks ADD CONSTRAINT stocks_quantity_on_hand_nonnegative '
                .'CHECK (quantity_on_hand >= 0)'
            );
            DB::statement(
                'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_positive '
                .'CHECK (quantity > 0)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('departments');
    }
};
