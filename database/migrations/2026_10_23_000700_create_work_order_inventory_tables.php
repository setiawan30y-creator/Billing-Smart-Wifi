<?php

/**
 * ALMARA Billing WiFi SaaS — Work order teknisi, kalender, & inventaris.
 * @2026-Alifiandra
 */

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bersihkan sisa tabel dari percobaan migrasi sebelumnya yang gagal di tengah jalan
        // (DDL MySQL tidak transaksional). Migrasi ini belum tercatat, jadi tabelnya belum dipakai.
        Schema::disableForeignKeyConstraints();
        $this->down();
        Schema::enableForeignKeyConstraints();

        // ---------- Inventaris ----------
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('name');
            $table->string('category', 20)->default('other'); // router | ont | cable | splitter | connector | odp | tool | other
            $table->string('unit', 10)->default('pcs'); // pcs | meter | roll | box
            $table->boolean('track_serial')->default(false);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 20)->default('warehouse'); // warehouse | vehicle | technician
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // teknisi pemegang stok
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();
            // Nama eksplisit: nama otomatis 65 karakter melebihi batas MySQL (64).
            $table->unique(['inventory_item_id', 'inventory_location_id'], 'inv_balances_item_location_unique');
        });

        Schema::create('inventory_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('serial', 80);
            $table->string('status', 20)->default('in_stock'); // in_stock | installed | faulty | returned
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'serial']);
        });

        // Mutasi stok bersifat permanen (tidak diedit/dihapus); koreksi lewat mutasi "adjust".
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('type', 20); // receive | transfer | issue | return | adjust
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->foreignId('inventory_serial_number_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        // ---------- Work order ----------
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('type', 20)->default('repair'); // installation | repair | visit | dismantle | other
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open')->index(); // open | scheduled | in_progress | on_hold | completed | cancelled
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->decimal('completion_latitude', 10, 7)->nullable();
            $table->decimal('completion_longitude', 10, 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('work_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member'); // lead | member
            $table->timestamps();
            $table->unique(['work_order_id', 'user_id']);
        });

        Schema::create('work_order_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('work_order_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // disk privat
            $table->string('kind', 10)->default('other'); // before | after | other
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->foreignId('inventory_serial_number_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('returned')->default(false);
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('technician_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10)->default('off'); // off | leave | sick
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        foreach (['technician_availability', 'work_order_status_history', 'work_order_materials', 'work_order_media', 'work_order_checklists',
            'work_order_assignments', 'work_orders', 'inventory_movements', 'inventory_serial_numbers', 'inventory_balances',
            'inventory_locations', 'inventory_items'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
