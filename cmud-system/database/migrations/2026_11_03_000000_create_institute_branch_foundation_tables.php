<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the Institute & Branches foundation tables.
     *
     * Reference columns are created here without foreign-key constraints.
     * Those constraints will be added in separate relationship migrations.
     */
    public function up(): void
    {
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email');
            $table->string('tin_number')->nullable();
            $table->string('website')->nullable();
            $table->text('address');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code');
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('address');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->string('code');
            $table->string('name');
            $table->string('room_type');
            $table->string('floor')->nullable();
            $table->unsignedInteger('capacity');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('staff_code');
            $table->string('full_name');
            $table->string('phone', 30);
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
            $table->foreignId('staff_id');
            $table->foreignId('branch_id');
            $table->foreignId('department_id');
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id');
            $table->foreignId('branch_id');
            $table->foreignId('room_id')->nullable();
            $table->string('asset_code');
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2);
            $table->string('unit');
            $table->date('warranty_expires_on')->nullable();
            $table->string('condition');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->string('item_code');
            $table->string('item_name');
            $table->string('unit');
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id');
            $table->string('movement_type');
            $table->decimal('quantity', 12, 3);
            $table->foreignId('stock_price_version_id')->nullable();
            $table->decimal('unit_cost', 12, 2);
            $table->string('reason');
            $table->timestamp('movement_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_price_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id');
            $table->unsignedInteger('version_number');
            $table->decimal('unit_cost', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_price_versions');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('staff_assignments');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('institutes');
    }
};
