<?php

/**
 * ALMARA Billing WiFi SaaS — Billing ISP ke pelanggan internet.
 * Semua tabel memiliki tenant_id dan diakses lewat tenant scope.
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('download_mbps')->nullable(); // batas paket, bukan kecepatan terukur
            $table->unsignedInteger('upload_mbps')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_code', 30);
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->text('address');
            $table->string('landmark')->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ownership', 10)->default('own'); // own | rent
            $table->string('owner_name')->nullable();
            $table->text('nik')->nullable(); // terenkripsi (cast encrypted)
            $table->string('nik_last4', 4)->nullable(); // untuk tampilan ter-masking
            $table->string('ktp_path')->nullable();   // disk privat, sisi depan saja
            $table->string('house_photo_path')->nullable(); // disk privat
            $table->string('status', 20)->default('active')->index(); // prospect | active | inactive
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'customer_code']);
        });

        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_package_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active')->index(); // active | isolated | terminated
            $table->decimal('price_override', 15, 2)->nullable();
            $table->unsignedTinyInteger('billing_day')->default(1); // 1-28
            $table->unsignedTinyInteger('due_days')->default(10);
            $table->string('pppoe_username')->nullable();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('period', 7)->nullable(); // YYYY-MM untuk invoice berulang
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid')->index(); // unpaid | partial | paid | void
            $table->text('notes')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            // Scheduler tidak boleh membuat invoice ganda untuk layanan & periode yang sama.
            $table->unique(['tenant_id', 'customer_service_id', 'period'], 'invoices_service_period_unique');
            $table->index(['tenant_id', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number', 40);
            $table->decimal('amount', 15, 2);
            $table->decimal('unallocated_amount', 15, 2)->default(0); // kelebihan bayar
            $table->string('method', 20); // cash | transfer | gateway | other
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | verified | rejected | reversed
            $table->string('proof_path')->nullable(); // disk privat
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->json('planned_invoice_ids')->nullable(); // invoice tujuan pilihan petugas
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('event_id', 120);
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 20)->default('received'); // received | processed | unmatched | rejected | duplicate
            $table->json('payload');
            $table->string('message')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'event_id']); // idempotensi event
        });

        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('mikrotik'); // mikrotik | olt | switch | other
            $table->string('host');
            $table->unsignedInteger('api_port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // terenkripsi
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('channel', 20)->default('whatsapp');
            $table->text('body');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'event', 'channel']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('channel', 20)->default('whatsapp');
            $table->string('recipient', 40);
            $table->text('message');
            $table->string('dedupe_key', 150);
            $table->string('status', 20)->default('queued'); // queued | sent | delivered | read | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('notifiable');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        foreach (['notification_logs', 'notification_templates', 'network_devices', 'payment_webhook_events', 'payment_allocations',
            'payments', 'invoice_items', 'invoices', 'customer_services', 'customers', 'service_packages'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
