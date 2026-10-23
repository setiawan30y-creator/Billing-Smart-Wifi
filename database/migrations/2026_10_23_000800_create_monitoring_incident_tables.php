<?php

/**
 * ALMARA Billing WiFi SaaS — Monitoring jaringan (read-only), gangguan massal & pengumuman.
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

        Schema::create('monitoring_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('host');
            $table->string('method', 12)->default('tcp'); // icmp | tcp | routeros
            $table->unsignedInteger('port')->nullable();
            $table->string('interface_name', 60)->nullable(); // untuk throughput RouterOS
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('interval_minutes')->default(5);
            $table->unsignedInteger('latency_warn_ms')->default(150);
            $table->unsignedSmallInteger('loss_warn_pct')->default(20);
            $table->unsignedSmallInteger('down_after_failures')->default(2);
            $table->string('last_status', 12)->default('unknown'); // up | degraded | down | unknown
            $table->timestamp('last_checked_at')->nullable();
            $table->decimal('last_latency_ms', 10, 2)->nullable();
            $table->decimal('last_loss_pct', 5, 2)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('monitoring_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitoring_target_id')->constrained()->cascadeOnDelete();
            $table->timestamp('measured_at')->index();
            $table->string('source', 12); // icmp | tcp | routeros
            $table->string('status', 12); // up | down | error
            $table->decimal('latency_ms', 10, 2)->nullable();
            $table->decimal('packet_loss_pct', 5, 2)->nullable();
            $table->decimal('cpu_load_pct', 5, 2)->nullable();
            $table->decimal('memory_used_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->unsignedBigInteger('rx_bps')->nullable();
            $table->unsignedBigInteger('tx_bps')->nullable();
            $table->unsignedInteger('pppoe_active')->nullable();
            $table->string('error')->nullable(); // kegagalan collector (bukan berarti perangkat down)
            $table->index(['monitoring_target_id', 'measured_at']);
        });

        Schema::create('monitoring_collection_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('targets')->default(0);
            $table->unsignedInteger('up')->default(0);
            $table->unsignedInteger('down')->default(0);
            $table->unsignedInteger('collector_errors')->default(0);
            $table->text('error')->nullable();
        });

        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitoring_target_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 12); // down | latency | loss | cpu
            $table->string('severity', 10); // critical | warning
            $table->string('status', 14)->default('open'); // open | acknowledged | resolved
            $table->string('message');
            $table->decimal('last_value', 12, 2)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('ack_note')->nullable();
            $table->timestamps();
            $table->index(['monitoring_target_id', 'metric', 'status']);
        });

        Schema::create('network_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity', 10)->default('major'); // minor | major | critical
            $table->string('status', 14)->default('investigating'); // investigating | identified | monitoring | resolved
            $table->string('confirmed_cause')->nullable(); // diisi hanya bila penyebab sudah terbukti
            $table->boolean('auto_detected')->default(false);
            $table->string('detection_reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('estimated_recovery_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('incident_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_incident_id')->constrained()->cascadeOnDelete();
            $table->string('affected_type', 20); // device | odc | odp
            $table->unsignedBigInteger('affected_id');
            $table->timestamps();
            $table->unique(['network_incident_id', 'affected_type', 'affected_id'], 'incident_devices_unique');
        });

        Schema::create('incident_affected_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('via', 40); // mis. "ODP ODP-A01"
            $table->timestamps();
            $table->unique(['network_incident_id', 'customer_id'], 'incident_customers_unique');
        });

        Schema::create('incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_incident_id')->constrained()->cascadeOnDelete();
            $table->string('status', 14);
            $table->text('note');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('network_status_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('check', 30); // ticket_cluster | device_down
            $table->string('scope_type', 20)->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedInteger('observed');
            $table->unsignedInteger('threshold');
            $table->boolean('triggered');
            $table->foreignId('network_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_at')->useCurrent();
        });

        Schema::create('service_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('target_mode', 12)->default('all'); // all | incident | odp | odc | device | package
            $table->boolean('send_whatsapp')->default(true);
            $table->boolean('show_in_portal')->default(true);
            $table->timestamp('visible_until')->nullable();
            $table->string('status', 10)->default('draft'); // draft | sending | sent
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_announcement_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
        });

        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('queued'); // queued | skipped | portal_only
            $table->timestamps();
            $table->unique(['service_announcement_id', 'customer_id'], 'announcement_deliveries_unique');
        });

        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        foreach (['announcement_deliveries', 'announcement_targets', 'service_announcements', 'network_status_checks', 'incident_updates',
            'incident_affected_customers', 'incident_devices', 'network_incidents', 'monitoring_alerts', 'monitoring_collection_runs',
            'monitoring_samples', 'monitoring_targets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
