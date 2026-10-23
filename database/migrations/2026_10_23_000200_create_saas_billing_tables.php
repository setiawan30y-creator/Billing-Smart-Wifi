<?php

/**
 * ALMARA Billing WiFi SaaS — SaaS Billing (sewa platform ALMARA kepada ISP).
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedInteger('active_clients'); // pelanggan unik dengan >= 1 layanan aktif
            $table->unsignedInteger('active_services');
            $table->unsignedInteger('routers');
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->index(['tenant_id', 'period']);
        });

        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('saas_usage_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('period', 7);
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid')->index(); // unpaid | partial | paid | void
            $table->boolean('is_simulation')->default(false); // true bila harga paket belum disahkan
            $table->text('notes')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'period']); // cegah invoice SaaS ganda per periode
        });

        Schema::create('saas_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // base | overage_client | overage_router | adjustment
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('saas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('verified'); // verified | reversed
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_payments');
        Schema::dropIfExists('saas_invoice_items');
        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('saas_usage_records');
    }
};
