<?php

namespace Tests\Feature;

use App\Integrations\MikroTik\PppoeReader;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Services\OdpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Peta topologi. @2026-Alifiandra */
class TopologyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    protected function seedTopology(): array
    {
        return $this->inTenant($this->tenant, function () {
            $olt = new NetworkDevice(['name' => 'OLT pusat', 'type' => 'olt', 'host' => '10.0.0.2', 'latitude' => -6.2000, 'longitude' => 106.8160, 'is_active' => true]);
            $olt->save();
            $rt = new NetworkDevice(['name' => 'Router inti', 'type' => 'mikrotik', 'host' => '10.0.0.1', 'username' => 'api', 'latitude' => -6.1995, 'longitude' => 106.8150, 'is_active' => true]);
            $rt->save();
            $odc = Odc::create(['code' => 'ODC-01', 'name' => 'ODC utara', 'network_device_id' => $olt->id, 'latitude' => -6.1980, 'longitude' => 106.8180, 'is_active' => true]);
            $odp = Odp::create(['code' => 'ODP-01', 'name' => 'ODP gang satu', 'odc_id' => $odc->id, 'capacity' => 8, 'status' => 'active', 'latitude' => -6.1970, 'longitude' => 106.8200]);
            $odp->syncPorts();
            Odp::create(['code' => 'ODP-NOLOC', 'name' => 'Tanpa koordinat', 'capacity' => 8, 'status' => 'active']);
            $pkg = ServicePackage::create(['code' => 'H', 'name' => 'Home 20M', 'price' => 100000, 'is_active' => true]);
            $svcs = [];
            foreach ([['Pelanggan satu', 'u-1', -6.1965, 106.8205], ['Pelanggan dua', 'u-2', -6.1972, 106.8212]] as $i => [$n, $u, $lat, $lng]) {
                $c = new Customer(['name' => $n, 'phone' => '0812000000'.$i, 'address' => 'x', 'ownership' => 'own', 'status' => 'active', 'latitude' => $lat, 'longitude' => $lng]);
                $c->customer_code = 'C'.$i;
                $c->save();
                $s = $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'pppoe_username' => $u, 'started_at' => '2026-01-01']);
                app(OdpService::class)->assign($odp->ports()->where('port_number', $i + 1)->first(), $s);
                $svcs[] = $s;
            }

            return $svcs;
        });
    }

    public function test_topology_page_renders_nodes_links_and_missing_hints(): void
    {
        $this->seedTopology();
        $res = $this->actingAs($this->tenantUser($this->tenant))->get(route('app.topology.index'))->assertOk()
            ->assertSee('Peta Topologi')->assertSee('ODC-01')->assertSee('ODP gang satu')->assertSee('Pelanggan satu')
            ->assertSee('1 ODP belum punya koordinat');
        $this->assertStringNotContainsString('Tanpa koordinat', $res->getContent());
    }

    public function test_status_endpoint_returns_online_offline_per_service(): void
    {
        [$a, $b] = $this->seedTopology();
        $this->app->instance(PppoeReader::class, new class extends PppoeReader
        {
            public function read(NetworkDevice $device): array
            {
                return ['secrets' => [], 'active' => [['id' => null, 'name' => 'u-1', 'service' => 'pppoe', 'address' => '10.1.1.2', 'caller_id' => null, 'uptime' => '1m', 'encoding' => null, 'comment' => null]],
                    'fetched_at' => now()->toIso8601String()];
            }
        });
        $this->actingAs($this->tenantUser($this->tenant))->getJson(route('app.topology.status'))->assertOk()
            ->assertJsonPath('ok', true)->assertJsonPath('online', 1)->assertJsonPath('offline', 1)
            ->assertJsonPath('services.'.$a->id, 'online')->assertJsonPath('services.'.$b->id, 'offline');
    }

    public function test_collector_cannot_open_topology(): void
    {
        $this->actingAs($this->tenantUser($this->tenant, 'collector'))->get(route('app.topology.index'))->assertForbidden();
    }
}
