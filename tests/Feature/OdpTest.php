<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\ServicePackage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdpTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    protected function service(string $name = 'Pelanggan ODP')
    {
        return $this->inTenant($this->tenant, function () use ($name) {
            $pkg = ServicePackage::firstOrCreate(['code' => 'H10'], ['name' => 'Home 10', 'price' => 150000, 'is_active' => true]);
            $c = new Customer(['name' => $name, 'phone' => '081234567890', 'address' => 'x', 'ownership' => 'own', 'status' => 'active', 'latitude' => -6.2, 'longitude' => 106.8]);
            $c->customer_code = 'C-'.uniqid();
            $c->save();

            return $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);
        });
    }

    public function test_create_odp_generates_ports_and_assign_flow(): void
    {
        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->post(route('app.odps.store'), [
            'code' => 'odp-a01', 'name' => 'Blok A', 'capacity' => 8, 'splitter' => '1:8', 'status' => 'active',
            'latitude' => '-6.2001', 'longitude' => '106.8001',
        ])->assertRedirect();

        $odp = $this->inTenant($this->tenant, fn () => Odp::where('code', 'ODP-A01')->firstOrFail());
        $this->assertSame(8, $this->inTenant($this->tenant, fn () => $odp->ports()->count()));

        $svc = $this->service();
        $port1 = $this->inTenant($this->tenant, fn () => $odp->ports()->where('port_number', 1)->first());
        $port2 = $this->inTenant($this->tenant, fn () => $odp->ports()->where('port_number', 2)->first());

        $this->actingAs($admin)->post(route('app.odps.ports.assign', $port1), ['customer_service_id' => $svc->id])->assertSessionHas('success');
        $this->assertSame('used', $port1->fresh()->status);

        // Port terpakai tidak bisa dipakai layanan lain
        $svc2 = $this->service('Pelanggan Dua');
        $this->actingAs($admin)->post(route('app.odps.ports.assign', $port1), ['customer_service_id' => $svc2->id])->assertSessionHas('error');

        // Pindah port lewat form layanan
        $this->actingAs($admin)->put(route('app.services.update', $svc), [
            'service_package_id' => $svc->service_package_id, 'billing_day' => 1, 'due_days' => 10, 'status' => 'active', 'odp_port_id' => $port2->id,
        ])->assertSessionHas('success');
        $this->assertSame('available', $port1->fresh()->status);
        $this->assertSame($svc->id, $port2->fresh()->customer_service_id);

        // Kapasitas tidak bisa dikurangi di bawah port terpakai
        $this->actingAs($admin)->put(route('app.odps.update', $odp), ['code' => 'ODP-A01', 'name' => 'Blok A', 'capacity' => 1, 'status' => 'active'])->assertSessionHas('error');

        // Layanan berhenti → port dilepas
        $this->actingAs($admin)->put(route('app.services.update', $svc), [
            'service_package_id' => $svc->service_package_id, 'billing_day' => 1, 'due_days' => 10, 'status' => 'terminated',
        ])->assertSessionHas('success');
        $this->assertNull($port2->fresh()->customer_service_id);

        // Halaman render
        foreach ([route('app.odps.index'), route('app.odps.show', $odp), route('app.odps.create'), route('app.odps.edit', $odp), route('app.odcs.index'), route('app.odcs.create'), route('app.customers.show', $svc->customer_id), route('app.customers.create')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('app.odps.index'))->assertSee('ODP-A01');
    }

    public function test_other_tenant_cannot_touch_odp_and_billing_role_is_read_only(): void
    {
        $odp = $this->inTenant($this->tenant, function () {
            $o = Odp::create(['code' => 'X1', 'name' => 'X', 'capacity' => 4, 'status' => 'active']);
            $o->syncPorts();

            return $o;
        });
        $port = $this->inTenant($this->tenant, fn () => OdpPort::first());

        $other = $this->makeTenant('ISP-B');
        $this->actingAs($this->tenantUser($other))->get(route('app.odps.show', $odp))->assertNotFound();

        $billing = $this->tenantUser($this->tenant, 'billing');
        $this->actingAs($billing)->get(route('app.odps.show', $odp))->assertOk();
        $this->actingAs($billing)->post(route('app.odps.ports.release', $port))->assertForbidden();
    }
}
