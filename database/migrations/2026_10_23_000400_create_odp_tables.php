<?php

/**
 * ALMARA Billing WiFi SaaS — Topologi FTTH: ODC → ODP → port → layanan pelanggan.
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
        Schema::create('odcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete(); // OLT sumber
            $table->string('code', 30);
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->nullable(); // jumlah core/port keluaran
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('odc_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete(); // OLT
            $table->string('pon_port', 30)->nullable(); // mis. 0/1/3
            $table->string('code', 30);
            $table->string('name');
            $table->string('splitter', 10)->nullable(); // mis. 1:8
            $table->unsignedTinyInteger('capacity')->default(8); // jumlah port pelanggan
            $table->text('address')->nullable();
            $table->string('landmark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('active'); // active | maintenance | inactive
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('odp_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('odp_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('port_number');
            // Satu layanan hanya boleh menempati satu port.
            $table->foreignId('customer_service_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('available'); // available | used | broken
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['odp_id', 'port_number']);
        });

        // Sinkronkan permission baru (network.odp.*) ke role sistem.
        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('odp_ports');
        Schema::dropIfExists('odps');
        Schema::dropIfExists('odcs');
    }
};
