<?php

/**
 * ALMARA Billing WiFi SaaS — Komisi kolektor, rekonsiliasi, audit keuangan, backup & restore.
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
        // Bersihkan sisa percobaan migrasi yang gagal di tengah jalan (DDL MySQL tidak transaksional).
        Schema::disableForeignKeyConstraints();
        $this->down();
        Schema::enableForeignKeyConstraints();

        // ---------- Komisi kolektor ----------
        Schema::create('collection_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // null = berlaku umum untuk kolektor
            $table->string('type', 10)->default('percent'); // percent | fixed
            $table->decimal('value', 15, 2);
            $table->string('method', 10)->default('any'); // any | cash | transfer
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->timestamps();
        });

        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total', 15, 2);
            $table->unsignedInteger('entries_count');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10)->default('earn'); // earn | reversal
            $table->decimal('base_amount', 15, 2);
            $table->decimal('amount', 15, 2); // negatif untuk reversal
            $table->string('status', 10)->default('pending'); // pending | settled | void
            $table->foreignId('commission_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'kind']); // cegah komisi ganda
        });

        // ---------- Rekonsiliasi ----------
        Schema::create('reconciliation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('source', 20)->default('bank_statement');
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('status', 12)->default('open'); // open | completed
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->date('txn_date')->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('status', 12)->default('unmatched'); // matched | unmatched | ignored
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_type', 10)->nullable(); // auto | manual | created
            $table->string('note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ---------- Audit keuangan ----------
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('status', 10)->default('closed'); // closed | open (dibuka ulang)
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'period']);
        });

        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // credit_note | write_off
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('adjusted_amount', 15, 2)->default(0)->after('amount_paid'); // credit note/write-off disetujui
        });

        // ---------- Backup & restore ----------
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 12)->default('manual'); // manual | scheduled | pre_restore
            $table->string('status', 12)->default('running'); // running | completed | failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('backup_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64); // sha256 dari file terenkripsi
            $table->json('table_counts');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('restore_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backup_artifact_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 10); // test | restore
            $table->string('status', 12)->default('running'); // running | passed | failed | completed
            $table->json('report')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
        });

        Schema::create('backup_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'adjusted_amount')) {
            Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('adjusted_amount'));
        }
        foreach (['backup_audit_logs', 'restore_jobs', 'backup_artifacts', 'backup_jobs', 'financial_adjustments', 'accounting_periods',
            'reconciliation_items', 'reconciliation_batches', 'commission_entries', 'commission_settlements', 'commission_rules', 'collection_assignments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
