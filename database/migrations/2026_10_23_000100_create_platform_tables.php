<?php

/**
 * ALMARA Billing WiFi SaaS — Fondasi platform (tenant, user, RBAC, audit).
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 15, 2)->nullable(); // null = custom/kontrak
            $table->decimal('price_yearly', 15, 2)->nullable();
            $table->unsignedInteger('client_quota')->nullable();  // null = sesuai kontrak
            $table->unsignedInteger('router_quota')->nullable();
            $table->decimal('overage_client_price', 15, 2)->default(0);
            $table->decimal('overage_router_price', 15, 2)->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            // Harga blueprint masih usulan: tagihan nyata hanya boleh dari paket yang disahkan.
            $table->boolean('is_price_approved')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('status', 30)->default('draft')->index();
            $table->string('subdomain', 63)->nullable()->unique();
            $table->string('brand_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('theme', 20)->default('default');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('locale', 10)->default('id');
            $table->json('settings')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->timestamps();
            $table->softDeletes(); // arsip, bukan hapus permanen
        });

        Schema::create('tenant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('pic_name');
            $table->string('whatsapp', 20);
            $table->string('email');
            $table->string('office_phone', 20)->nullable();
            $table->text('address');
            $table->string('province');
            $table->string('city');
            $table->string('country', 60)->default('Indonesia');
            $table->string('website')->nullable();
            $table->timestamps();
            $table->index('email');
            $table->index('whatsapp');
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type', 20)->default('custom'); // platform | custom
            $table->string('status', 20)->default('pending'); // pending | verified | failed
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')->constrained()->restrictOnDelete();
            $table->string('period', 10)->default('monthly'); // monthly | yearly
            $table->string('status', 20)->default('trial')->index(); // trial | active | past_due | cancelled | ended
            // Snapshot dari katalog saat langganan dibuat; perubahan katalog tidak mengubah kontrak berjalan.
            $table->decimal('price', 15, 2)->nullable();
            $table->unsignedInteger('client_quota')->nullable();
            $table->unsignedInteger('router_quota')->nullable();
            $table->decimal('overage_client_price', 15, 2)->default(0);
            $table->decimal('overage_router_price', 15, 2)->default(0);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_super_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // null sampai aktivasi undangan
            $table->string('theme_preference', 10)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // sha256, token asli tidak disimpan
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('group', 40);
            $table->string('label');
            $table->string('scope', 10)->default('tenant'); // platform | tenant
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = role sistem
            $table->string('slug', 60);
            $table->string('name');
            $table->string('scope', 10)->default('tenant');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('tenant_provisioning_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40); // config | domain | branding | admin_invitation
            $table->string('idempotency_key', 120)->unique();
            $table->string('status', 20)->default('pending'); // pending | running | done | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0); // 0 = sequence platform
            $table->string('key', 60);
            $table->unsignedBigInteger('value')->default(0);
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['tenant_sequences', 'tenant_provisioning_jobs', 'audit_logs', 'role_user', 'permission_role', 'roles', 'permissions',
            'sessions', 'password_reset_tokens', 'user_invitations', 'users', 'tenant_subscriptions', 'tenant_domains',
            'tenant_profiles', 'tenants', 'saas_plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
