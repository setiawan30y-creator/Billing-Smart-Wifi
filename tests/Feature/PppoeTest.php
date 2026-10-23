<?php

namespace Tests\Feature;

use App\Integrations\MikroTik\PppoeReader;
use App\Integrations\Monitoring\RouterOsClient;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\ServicePackage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** @2026-Alifiandra */
class PppoeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    protected function router(array $attrs = []): NetworkDevice
    {
        return $this->inTenant($this->tenant, function () use ($attrs) {
            $d = new NetworkDevice($attrs + ['name' => 'Router inti', 'type' => 'mikrotik', 'host' => '10.0.0.1', 'api_port' => 8728,
                'username' => 'api-read', 'is_active' => true]);
            $d->password = 'rahasia';
            $d->save();

            return $d;
        });
    }

    public function test_reader_requests_no_password_and_maps_rows(): void
    {
        $fake = new class extends RouterOsClient
        {
            public array $calls = [];

            public function connect(string $host, int $port, string $user, string $password, float $timeout = 4.0): void {}

            public function command(string $cmd, array $args = []): array
            {
                $this->calls[] = [$cmd, $args];

                return $cmd === '/ppp/secret/print'
                    ? ['done' => true, 'trap' => null, 'rows' => [['.id' => '*1', 'name' => 'user-01', 'profile' => '20M', 'service' => 'pppoe', 'disabled' => 'false', 'password' => 'bocor']]]
                    : ['done' => true, 'trap' => null, 'rows' => [['.id' => '*A', 'name' => 'user-01', 'address' => '10.10.0.2', 'caller-id' => 'AA:BB:CC:DD:EE:FF', 'uptime' => '1d02:03:04']]];
            }

            public function close(): void {}
        };
        $reader = new class($fake) extends PppoeReader
        {
            public function __construct(protected RouterOsClient $c) {}

            protected function client(): RouterOsClient
            {
                return $this->c;
            }
        };

        $data = $reader->read($this->router());
        foreach ($fake->calls as [, $args]) {
            $this->assertStringNotContainsString('password', $args['.proplist']);
        }
        $this->assertSame('user-01', $data['secrets'][0]['name']);
        $this->assertArrayNotHasKey('password', $data['secrets'][0]);
        $this->assertFalse($data['secrets'][0]['disabled']);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $data['active'][0]['caller_id']);
        $this->assertSame('1h 02j 03m', PppoeReader::humanUptime('1d02:03:04'));
    }

    public function test_page_shows_secrets_online_and_linked_customer(): void
    {
        $device = $this->router();
        $this->inTenant($this->tenant, function () {
            $pkg = ServicePackage::create(['code' => 'H', 'name' => 'Home 20M', 'price' => 100000, 'is_active' => true]);
            foreach ([['Pelanggan satu', 'user-01', 'active'], ['Pelanggan dua', 'user-02', 'isolated']] as $i => [$n, $u, $st]) {
                $c = new Customer(['name' => $n, 'phone' => '0812000000'.$i, 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
                $c->customer_code = 'C'.$i;
                $c->save();
                $c->services()->create(['service_package_id' => $pkg->id, 'status' => $st, 'pppoe_username' => $u, 'started_at' => '2026-01-01']);
            }
        });

        $this->app->instance(PppoeReader::class, new class extends PppoeReader
        {
            public function read(NetworkDevice $device): array
            {
                return [
                    'secrets' => [
                        ['id' => '*1', 'name' => 'user-01', 'service' => 'pppoe', 'profile' => '20M', 'local_address' => null, 'remote_address' => null, 'disabled' => false, 'comment' => null, 'last_logged_out' => null, 'last_caller_id' => null, 'last_disconnect_reason' => null],
                        ['id' => '*2', 'name' => 'user-02', 'service' => 'pppoe', 'profile' => '20M', 'local_address' => null, 'remote_address' => null, 'disabled' => false, 'comment' => null, 'last_logged_out' => null, 'last_caller_id' => null, 'last_disconnect_reason' => null],
                        ['id' => '*3', 'name' => 'user-lepas', 'service' => 'pppoe', 'profile' => 'default', 'local_address' => null, 'remote_address' => null, 'disabled' => true, 'comment' => 'tidak dipakai', 'last_logged_out' => 'never', 'last_caller_id' => null, 'last_disconnect_reason' => null],
                    ],
                    'active' => [
                        ['id' => '*A', 'name' => 'user-02', 'service' => 'pppoe', 'address' => '10.10.0.9', 'caller_id' => 'AA:BB:CC:00:00:02', 'uptime' => '2h3m', 'encoding' => null, 'comment' => null],
                    ],
                    'fetched_at' => now()->toIso8601String(),
                ];
            }
        });

        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->get(route('app.pppoe.index'))->assertOk()
            ->assertSee('user-lepas')->assertSee('Pelanggan satu')->assertSee('Dinonaktifkan')->assertSee('Offline')
            ->assertSee('1 terisolir tapi online')->assertDontSee('rahasia');

        $this->actingAs($admin)->get(route('app.pppoe.index', ['tab' => 'active']))->assertOk()
            ->assertSee('10.10.0.9')->assertSee('Pelanggan dua')->assertDontSee('user-lepas');

        $this->actingAs($admin)->get(route('app.pppoe.index', ['filter' => 'unlinked']))->assertOk()
            ->assertSee('user-lepas')->assertDontSee('Pelanggan satu');

        // Kolektor tidak punya akses perangkat jaringan.
        $this->actingAs($this->tenantUser($this->tenant, 'collector'))->get(route('app.pppoe.index'))->assertForbidden();
    }

    public function test_router_error_is_shown_without_crash(): void
    {
        $this->router();
        $this->app->instance(PppoeReader::class, new class extends PppoeReader
        {
            public function read(NetworkDevice $device): array
            {
                throw new RuntimeException('Tidak dapat terhubung ke RouterOS API 10.0.0.1:8728');
            }
        });
        $this->actingAs($this->tenantUser($this->tenant))->get(route('app.pppoe.index'))->assertOk()
            ->assertSee('Gagal membaca router')->assertSee('10.0.0.1:8728');
    }

    public function test_other_tenant_router_is_not_reachable(): void
    {
        $other = $this->makeTenant('ISP-B');
        $foreign = $this->inTenant($other, function () {
            $d = new NetworkDevice(['name' => 'Router lain', 'type' => 'mikrotik', 'host' => '10.9.9.9', 'username' => 'x', 'is_active' => true]);
            $d->save();

            return $d;
        });
        $this->actingAs($this->tenantUser($this->tenant))->get(route('app.pppoe.index', ['device' => $foreign->id]))->assertOk()
            ->assertDontSee('10.9.9.9')->assertSee('Belum ada router MikroTik aktif');
    }
}
