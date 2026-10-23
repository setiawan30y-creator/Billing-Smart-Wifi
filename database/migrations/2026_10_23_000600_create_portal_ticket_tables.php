<?php

/**
 * ALMARA Billing WiFi SaaS — Portal pelanggan & tiket bantuan.
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
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('activation_token_hash', 64)->nullable()->unique();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('subject');
            $table->string('category', 30)->default('gangguan'); // gangguan | tagihan | pemasangan | lainnya
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->string('status', 20)->default('open')->index(); // open | in_progress | waiting_customer | resolved | closed
            $table->string('source', 10)->default('staff'); // portal | staff | whatsapp
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 10); // user | customer | system
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body');
            $table->boolean('is_internal')->default(false); // catatan internal tidak terlihat pelanggan
            $table->string('attachment_path')->nullable(); // disk privat
            $table->timestamps();
        });

        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('customer_accounts');
    }
};
