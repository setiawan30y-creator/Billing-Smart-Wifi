<?php

/**
 * ALMARA Billing WiFi SaaS — Import Excel/CSV (preview → validasi → eksekusi).
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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('customers');
            $table->string('original_name');
            $table->string('file_path'); // disk privat
            $table->string('status', 20)->default('validated'); // validated | processing | completed | failed | cancelled
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');        // nilai mentah dari file
            $table->json('normalized')->nullable();
            $table->string('status', 20); // valid | error | imported | failed
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
            $table->index(['import_batch_id', 'status']);
        });

        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
