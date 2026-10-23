<?php

namespace Tests;

use App\Models\Role;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SaasPlanSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedPlatform(): void
    {
        $this->seed([RolePermissionSeeder::class, SaasPlanSeeder::class]);
    }

    protected function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->is_super_admin = true;
        $u->save();
        $u->roles()->sync([Role::where('slug', 'super_admin')->value('id')]);

        return $u;
    }

    protected function makeTenant(string $code = 'ISP-A', string $plan = 'starter'): Tenant
    {
        $tenant = app(TenantProvisioningService::class)->create([
            'name' => 'ISP '.$code, 'code' => $code, 'pic_name' => 'PIC', 'whatsapp' => '081234567890',
            'email' => strtolower($code).'@example.com', 'address' => 'Alamat', 'province' => 'Prov', 'city' => 'Kota',
            'saas_plan_id' => SaasPlan::where('code', $plan)->value('id'), 'period' => 'monthly', 'initial_status' => 'trial',
            'admin_name' => 'Admin '.$code, 'admin_email' => 'admin-'.strtolower($code).'@example.com',
        ]);

        return $tenant->fresh();
    }

    protected function tenantUser(Tenant $tenant, string $role = 'isp_admin'): User
    {
        $u = User::factory()->create();
        $u->tenant_id = $tenant->id;
        $u->save();
        $u->roles()->sync([Role::whereNull('tenant_id')->where('slug', $role)->value('id')]);

        return $u;
    }

    protected function inTenant(Tenant $tenant, callable $cb): mixed
    {
        return app(TenantContext::class)->run($tenant, $cb);
    }
}
