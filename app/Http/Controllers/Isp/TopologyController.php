<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\IncidentDevice;
use App\Models\MonitoringTarget;
use App\Models\NetworkDevice;
use App\Models\NetworkIncident;
use App\Models\Odc;
use App\Models\Odp;
use App\Services\PppoeStatusService;
use Throwable;

/**
 * Peta topologi jaringan: Router/OLT → ODC → ODP → pelanggan, dengan jalur animasi.
 * Jalur digambar sebagai garis lurus antar titik (perkiraan, bukan rute kabel sebenarnya).
 * @2026-Alifiandra
 */
class TopologyController extends Controller
{
    public function index()
    {
        return view('isp.topology.index', ['data' => $this->data()]);
    }

    /** Status online/offline PPPoE per layanan, dimuat async setelah peta tampil. */
    public function status(PppoeStatusService $pppoe)
    {
        try {
            $s = $pppoe->customerSummary();
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Status PPPoE tidak dapat dibaca.'])->header('Cache-Control', 'no-store');
        }
        return response()->json([
            'ok' => true,
            'routers' => $s['routers'],
            'errors' => $s['errors'],
            'online' => $s['online'],
            'offline' => $s['offline'],
            'services' => $s['states'],
        ])->header('Cache-Control', 'no-store');
    }

    protected function data(): array
    {
        $hasLoc = fn ($q) => $q->whereNotNull('latitude')->whereNotNull('longitude');

        // Perangkat yang sedang terdampak gangguan massal aktif.
        $incidentIds = NetworkIncident::where('status', '!=', 'resolved')->pluck('id');
        $affected = IncidentDevice::whereIn('network_incident_id', $incidentIds)->get()
            ->groupBy('affected_type')->map(fn ($g) => $g->pluck('affected_id')->flip());
        $isAffected = fn (string $type, int $id) => isset($affected[$type]) && $affected[$type]->has($id);

        $deviceStatus = MonitoringTarget::whereNotNull('network_device_id')->get(['network_device_id', 'last_status'])
            ->groupBy('network_device_id')->map(function ($g) {
                $st = $g->pluck('last_status')->filter();

                return $st->contains('down') ? 'down' : ($st->contains('degraded') ? 'degraded' : ($st->contains('up') ? 'up' : null));
            });

        $devices = NetworkDevice::where('is_active', true)->tap($hasLoc)->get()->map(fn ($d) => [
            'id' => $d->id, 'type' => $d->type, 'name' => $d->name, 'host' => $d->host,
            'lat' => (float) $d->latitude, 'lng' => (float) $d->longitude,
            'status' => $deviceStatus[$d->id] ?? null, 'incident' => $isAffected('device', $d->id),
        ])->values();

        $odcs = Odc::where('is_active', true)->tap($hasLoc)->get()->map(fn ($o) => [
            'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'device_id' => $o->network_device_id,
            'lat' => (float) $o->latitude, 'lng' => (float) $o->longitude, 'incident' => $isAffected('odc', $o->id),
        ])->values();

        $odps = Odp::tap($hasLoc)->withCount(['ports as used_count' => fn ($p) => $p->where('status', 'used')])->get()->map(fn ($o) => [
            'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'odc_id' => $o->odc_id, 'device_id' => $o->network_device_id,
            'lat' => (float) $o->latitude, 'lng' => (float) $o->longitude, 'used' => (int) $o->used_count,
            'capacity' => (int) $o->capacity, 'status' => $o->status, 'url' => route('app.odps.show', $o->id),
            'incident' => $isAffected('odp', $o->id),
        ])->values();

        $services = CustomerService::with(['customer:id,name,customer_code,address,latitude,longitude', 'package:id,name', 'odpPort:id,odp_id,port_number,customer_service_id'])
            ->whereIn('status', ['active', 'isolated'])
            ->whereHas('customer', $hasLoc)->get();
        $customers = $services->map(fn ($s) => [
            'id' => $s->id, 'customer_id' => $s->customer_id, 'name' => $s->customer->name, 'code' => $s->customer->customer_code,
            'package' => $s->package?->name, 'status' => $s->status, 'pppoe' => $s->pppoe_username,
            'odp_id' => $s->odpPort?->odp_id, 'port' => $s->odpPort?->port_number,
            'lat' => (float) $s->customer->latitude, 'lng' => (float) $s->customer->longitude,
            'url' => route('app.customers.show', $s->customer_id),
        ])->values();

        return [
            'devices' => $devices, 'odcs' => $odcs, 'odps' => $odps, 'customers' => $customers,
            'missing' => [
                'odp' => Odp::where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count(),
                'customers' => CustomerService::whereIn('status', ['active', 'isolated'])
                    ->whereHas('customer', fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count(),
                'unassigned' => $customers->whereNull('odp_id')->count(),
            ],
        ];
    }
}
