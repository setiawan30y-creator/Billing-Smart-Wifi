<?php

namespace App\Services;

use App\Jobs\RunTenantProvisioning;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\TenantProvisioningJob;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\AdminInvitation;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Onboarding ISP baru (Super Admin → Manajemen Supplier ISP).
 * Data inti dibuat dalam satu transaksi; pekerjaan lanjutan lewat record provisioning + queue.
 * @2026-Alifiandra
 */
class TenantProvisioningService
{
    public function create(array $data, ?\Illuminate\Http\UploadedFile $logo = null): Tenant
    {
        $plan = SaasPlan::where('is_active', true)->findOrFail($data['saas_plan_id']);

        $tenant = DB::transaction(function () use ($data, $plan, $logo) {
            $tenant = new Tenant([
                'code' => $data['code'],
                'name' => $data['name'],
                'subdomain' => $data['subdomain'] ?? null,
                'brand_name' => $data['brand_name'] ?? null,
                'theme' => $data['theme'] ?? 'default',
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'locale' => 'id',
                'settings' => [
                    'quota_policy' => 'warn',
                    'reminder_offsets' => [-7, -3, -1, 0, 1, 3, 7],
                    'invoice_prefix' => 'INV',
                ],
            ]);
            $tenant->status = 'provisioning';
            if ($logo) {
                $tenant->logo_path = $logo->store('tenant-logos', 'public');
            }
            $tenant->save();

            $tenant->profile()->create([
                'pic_name' => $data['pic_name'],
                'whatsapp' => $data['whatsapp'],
                'email' => $data['email'],
                'office_phone' => $data['office_phone'] ?? null,
                'address' => $data['address'],
                'province' => $data['province'],
                'city' => $data['city'],
                'country' => $data['country'] ?? 'Indonesia',
                'website' => $data['website'] ?? null,
            ]);

            $start = now()->startOfDay();
            $status = $data['initial_status'] ?? 'trial';
            $trialDays = (int) ($data['trial_days'] ?? 14);

            $tenant->subscriptions()->create(TenantSubscription::snapshotFromPlan($plan, $data['period'] ?? 'monthly') + [
                'status' => $status === 'active' ? 'active' : 'trial',
                'starts_at' => $start,
                'trial_ends_at' => $status === 'trial' ? $start->copy()->addDays($trialDays) : null,
                'ends_at' => ($data['period'] ?? 'monthly') === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth(),
                'grace_days' => 7,
            ]);

            if (! empty($data['custom_domain'])) {
                $tenant->domains()->create([
                    'domain' => strtolower($data['custom_domain']),
                    'type' => 'custom',
                    'status' => 'pending',
                    'verification_token' => 'almara-verify-'.Str::random(32),
                ]);
            }

            $admin = new User([
                'name' => $data['admin_name'],
                'email' => strtolower($data['admin_email']),
            ]);
            $admin->tenant_id = $tenant->id;
            $admin->is_super_admin = false;
            $admin->save();
            $admin->roles()->attach(Role::whereNull('tenant_id')->where('slug', 'isp_admin')->value('id'));

            foreach (array_keys(TenantProvisioningJob::STEPS) as $step) {
                $tenant->provisioningJobs()->create([
                    'step' => $step,
                    'idempotency_key' => "tenant:{$tenant->id}:{$step}",
                    'status' => 'pending',
                ]);
            }

            AuditLogger::log('tenant.created', $tenant, null, [
                'code' => $tenant->code, 'name' => $tenant->name, 'plan' => $plan->code, 'admin_email' => $admin->email,
                'initial_status' => $status,
            ], null, $tenant->id);

            return $tenant;
        });

        RunTenantProvisioning::dispatch($tenant->id, $data['initial_status'] ?? 'trial')->afterCommit();

        return $tenant;
    }

    /**
     * Jalankan langkah provisioning yang belum selesai. Aman diulang (idempotent).
     */
    public function run(Tenant $tenant, string $targetStatus = 'trial'): void
    {
        $failed = false;

        foreach ($tenant->provisioningJobs()->where('status', '!=', 'done')->get() as $job) {
            $job->update(['status' => 'running', 'attempts' => $job->attempts + 1]);

            try {
                match ($job->step) {
                    'config' => $this->stepConfig($tenant),
                    'domain' => $this->stepDomain($tenant),
                    'branding' => null, // logo & tema sudah tersimpan saat create
                    'admin_invitation' => $this->stepInvitation($tenant),
                };
                $job->update(['status' => 'done', 'completed_at' => now(), 'last_error' => null]);
            } catch (Throwable $e) {
                $failed = true;
                Log::warning('Provisioning gagal', ['tenant' => $tenant->id, 'step' => $job->step, 'error' => $e->getMessage()]);
                // Simpan pesan aman (tanpa kredensial/stack trace).
                $job->update(['status' => 'failed', 'last_error' => Str::limit($e->getMessage(), 500)]);
            }
        }

        $tenant->refresh();
        if ($failed) {
            if ($tenant->canTransitionTo('provisioning_failed')) {
                $tenant->transitionTo('provisioning_failed');
            }

            return;
        }

        if ($tenant->status === 'provisioning') {
            $tenant->transitionTo($targetStatus === 'active' ? 'active' : 'trial');
            AuditLogger::log('tenant.provisioned', $tenant, null, ['status' => $tenant->status], null, $tenant->id);
        }
    }

    public function retry(Tenant $tenant): void
    {
        if ($tenant->status === 'provisioning_failed') {
            $tenant->transitionTo('provisioning');
        }
        $target = $tenant->currentSubscription?->status === 'active' ? 'active' : 'trial';
        AuditLogger::log('tenant.provisioning_retry', $tenant, null, null, null, $tenant->id);
        RunTenantProvisioning::dispatch($tenant->id, $target);
    }

    protected function stepConfig(Tenant $tenant): void
    {
        app(TenantContext::class)->run($tenant, function () {
            foreach (NotificationTemplate::DEFAULTS as $event => $body) {
                NotificationTemplate::firstOrCreate(['event' => $event, 'channel' => 'whatsapp'], ['body' => $body, 'is_enabled' => true]);
            }
        });
    }

    protected function stepDomain(Tenant $tenant): void
    {
        // Domain custom tetap "pending" sampai verifikasi DNS/TLS dilakukan (lihat perintah domains:verify).
        if ($tenant->subdomain && ! $tenant->domains()->where('type', 'platform')->exists()) {
            $base = config('almara.platform_domain');
            if ($base) {
                $tenant->domains()->create([
                    'domain' => $tenant->subdomain.'.'.$base,
                    'type' => 'platform',
                    'status' => 'verified',
                    'verification_token' => Str::random(32),
                ])->forceFill(['verified_at' => now()])->save();
            }
        }
    }

    protected function stepInvitation(Tenant $tenant): void
    {
        $admin = $tenant->users()->whereNull('password')->orderBy('id')->first();
        if (! $admin) {
            return; // sudah aktif
        }
        [, $token] = UserInvitation::issue($admin, \Illuminate\Support\Facades\Auth::guard('web')->id());
        $admin->notify(new AdminInvitation($tenant, $token));
    }
}
