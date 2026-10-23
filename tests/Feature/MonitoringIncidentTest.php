<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MonitoringAlert;
use App\Models\MonitoringTarget;
use App\Models\NetworkIncident;
use App\Models\NotificationLog;
use App\Models\Odp;
use App\Models\ServiceAnnouncement;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\IncidentService;
use App\Services\MonitoringService;
use App\Services\OdpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    public function test_tcp_monitoring_up_down_alert_and_resolve(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->post(route('app.monitoring.store'), [
            'name' => 'Router uji', 'host' => '127.0.0.1', 'method' => 'tcp', 'port' => $port, 'interval_minutes' => 1,
            'latency_warn_ms' => 1000, 'loss_warn_pct' => 50, 'down_after_failures' => 2, 'enabled' => 1,
        ])->assertRedirect();
        $t = $this->inTenant($this->tenant, fn () => MonitoringTarget::first());

        $this->actingAs($admin)->post(route('app.monitoring.check', $t))->assertSessionHas('success');
        $this->assertSame('up', $t->fresh()->last_status);
        $this->assertNotNull($t->fresh()->last_latency_ms);

        fclose($server); // port ditutup → down
        $svc = app(MonitoringService::class);
        $this->inTenant($this->tenant, fn () => $svc->check($t->fresh()));
        $this->assertSame('degraded', $t->fresh()->last_status, 'Satu kegagalan belum dianggap down');
        $this->inTenant($this->tenant, fn () => $svc->check($t->fresh()));
        $this->assertSame('down', $t->fresh()->last_status);
        $alert = $this->inTenant($this->tenant, fn () => MonitoringAlert::where('metric', 'down')->first());
        $this->assertSame('open', $alert->status);

        // Kegagalan collector tidak mengubah status jaringan
        $this->inTenant($this->tenant, fn () => $svc->record($t->fresh(), new \App\Integrations\Monitoring\ProbeResult('tcp', 'error', error: 'uji')));
        $this->assertSame('down', $t->fresh()->last_status);

        $this->actingAs($admin)->post(route('app.monitoring.ack', $alert))->assertSessionHas('success');
        $this->inTenant($this->tenant, fn () => $svc->record($t->fresh(), new \App\Integrations\Monitoring\ProbeResult('tcp', 'up', 5.0, 0.0)));
        $this->assertSame('resolved', $alert->fresh()->status);

        foreach ([route('app.monitoring.index'), route('app.monitoring.show', $t), route('app.monitoring.alerts'), route('app.monitoring.create'), route('app.monitoring.edit', $t)] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->artisan('monitoring:collect', ['--force' => true])->assertSuccessful();
    }

    public function test_incident_detection_affected_customers_and_announcement(): void
    {
        [$odp, $customers] = $this->inTenant($this->tenant, function () {
            $pkg = ServicePackage::create(['code' => 'H', 'name' => 'Home', 'price' => 100000, 'is_active' => true]);
            $odp = Odp::create(['code' => 'ODP-X', 'name' => 'X', 'capacity' => 8, 'status' => 'active']);
            $odp->syncPorts();
            $cs = [];
            for ($i = 1; $i <= 4; $i++) {
                $c = new Customer(['name' => 'Pelanggan '.$i, 'phone' => '08123456789'.$i, 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
                $c->customer_code = 'C'.$i;
                $c->save();
                $s = $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);
                app(OdpService::class)->assign($odp->ports()->where('port_number', $i)->first(), $s);
                $cs[] = $c;
            }

            return [$odp, $cs];
        });

        // 3 tiket gangguan dari ODP yang sama (ambang default 3)
        $this->inTenant($this->tenant, function () use ($customers) {
            foreach (array_slice($customers, 0, 3) as $i => $c) {
                Ticket::create(['customer_id' => $c->id, 'number' => 'T'.$i, 'subject' => 'Mati', 'category' => 'gangguan', 'status' => 'open']);
            }
        });
        $this->assertSame(1, app(IncidentService::class)->detect($this->tenant));
        $this->assertSame(0, app(IncidentService::class)->detect($this->tenant), 'Tidak membuat insiden ganda');
        $inc = $this->inTenant($this->tenant, fn () => NetworkIncident::first());
        $this->assertTrue($inc->auto_detected);
        $this->assertNull($inc->confirmed_cause);
        $this->assertSame(4, $inc->affectedCustomers()->count());

        $noc = $this->tenantUser($this->tenant, 'noc');
        $this->actingAs($noc)->get(route('app.incidents.show', $inc))->assertOk();
        $this->actingAs($noc)->post(route('app.announcements.store'), [
            'title' => 'Info gangguan', 'body' => 'Sedang ditangani', 'target_mode' => 'incident', 'network_incident_id' => $inc->id,
            'send_whatsapp' => 1, 'show_in_portal' => 1,
        ])->assertRedirect();
        $a = $this->inTenant($this->tenant, fn () => ServiceAnnouncement::first());
        $this->actingAs($noc)->get(route('app.announcements.show', $a))->assertOk()->assertSee('Pelanggan 4');
        $this->actingAs($noc)->post(route('app.announcements.send', $a))->assertSessionHas('success');
        $this->actingAs($noc)->post(route('app.announcements.send', $a))->assertSessionHas('error');
        $this->assertSame(4, $this->inTenant($this->tenant, fn () => NotificationLog::where('event', 'mass_announcement')->count()));

        $this->actingAs($noc)->post(route('app.incidents.update', $inc), ['status' => 'resolved', 'note' => 'Pulih setelah sambung ulang', 'confirmed_cause' => 'Konektor ODP longgar'])->assertSessionHas('success');
        $this->assertSame('resolved', $inc->fresh()->status);

        $this->assertTrue($this->inTenant($this->tenant, fn () => ServiceAnnouncement::visibleToCustomer($customers[0]->id)->exists()));
        $this->actingAs($this->tenantUser($this->tenant, 'billing'))->get(route('app.incidents.index'))->assertForbidden();
    }
}
