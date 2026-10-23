<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_user_cannot_access_other_tenant_records(): void
    {
        $a = $this->makeTenant('ISP-A');
        $b = $this->makeTenant('ISP-B');

        $custB = $this->inTenant($b, function () {
            $c = new Customer(['name' => 'Pelanggan B', 'phone' => '081111111111', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-B';
            $c->save();

            return $c;
        });
        $this->inTenant($a, function () {
            $c = new Customer(['name' => 'Pelanggan A', 'phone' => '081111111112', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-A';
            $c->save();
        });

        $userA = $this->tenantUser($a);

        $this->actingAs($userA)->get(route('app.customers.show', $custB->id))->assertNotFound();
        $this->actingAs($userA)->get(route('app.customers.index'))->assertOk()->assertSee('Pelanggan A')->assertDontSee('Pelanggan B');
    }

    public function test_tenant_id_from_client_is_ignored(): void
    {
        $a = $this->makeTenant('ISP-A');
        $b = $this->makeTenant('ISP-B');
        $userA = $this->tenantUser($a);

        $this->actingAs($userA)->post(route('app.customers.store'), [
            'tenant_id' => $b->id, 'name' => 'Nama Pelanggan', 'phone' => '081222333444', 'address' => 'Alamat',
            'ownership' => 'own', 'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'Nama Pelanggan', 'tenant_id' => $a->id]);
        $this->assertDatabaseMissing('customers', ['name' => 'Nama Pelanggan', 'tenant_id' => $b->id]);
    }

    public function test_queries_without_tenant_context_return_nothing(): void
    {
        $a = $this->makeTenant('ISP-A');
        $this->inTenant($a, fn () => Customer::forceCreate(['tenant_id' => $a->id, 'customer_code' => 'X', 'name' => 'X', 'phone' => '0812', 'address' => 'x']));

        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_tenant_user_cannot_open_super_admin_area(): void
    {
        $a = $this->makeTenant('ISP-A');
        $this->actingAs($this->tenantUser($a))->get(route('admin.tenants.index'))->assertForbidden();
    }

    public function test_suspended_tenant_is_blocked_but_data_kept(): void
    {
        $a = $this->makeTenant('ISP-A');
        $user = $this->tenantUser($a);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('admin.tenants.status', $a), ['status' => 'suspended', 'reason' => 'Tunggakan'])->assertRedirect();
        $this->assertSame('suspended', $a->fresh()->status);

        $this->actingAs($user)->get(route('app.dashboard'))->assertForbidden()->assertSee('ditangguhkan');
        $this->assertDatabaseHas('tenants', ['id' => $a->id, 'deleted_at' => null]);
    }
}
