<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\NetworkDevice;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Services\AuditLogger;
use App\Services\OdpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Titik ODP: daftar, peta (Leaflet/OpenStreetMap), port, dan penempatan pelanggan.
 * @2026-Alifiandra
 */
class OdpController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $odps = Odp::with(['odc', 'olt'])
            ->withCount(['ports as used_count' => fn ($p) => $p->where('status', 'used')])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('address', 'like', "%{$q}%")))
            ->when($request->query('odc'), fn ($w, $o) => $w->where('odc_id', $o))
            ->when($request->query('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($request->query('full') === '1', fn ($w) => $w->whereColumn('capacity', '<=', DB::raw('(select count(*) from odp_ports where odp_ports.odp_id = odps.id and odp_ports.status = \'used\')')))
            ->orderBy('code')->get();

        return view('isp.odps.index', [
            'odps' => $odps,
            'odcs' => Odc::orderBy('code')->get(),
            'mapData' => $this->mapData($odps),
            'totals' => [
                'odp' => $odps->count(),
                'ports' => $odps->sum('capacity'),
                'used' => $odps->sum('used_count'),
            ],
        ]);
    }

    public function create()
    {
        return view('isp.odps.form', ['odp' => new Odp(['capacity' => 8, 'splitter' => '1:8', 'status' => 'active'])] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $odp = DB::transaction(function () use ($data) {
            $odp = Odp::create($data);
            $odp->syncPorts();
            AuditLogger::log('odp.created', $odp, null, $odp->only(['code', 'name', 'capacity', 'latitude', 'longitude']));

            return $odp;
        });

        return redirect()->route('app.odps.show', $odp)->with('success', "ODP {$odp->code} dibuat dengan {$odp->capacity} port.");
    }

    public function show(Odp $odp)
    {
        $odp->load(['odc', 'olt', 'ports.service.customer', 'ports.service.package']);

        // Layanan aktif yang belum menempati port mana pun (kandidat dipasang).
        $unassigned = CustomerService::with(['customer', 'package'])
            ->whereIn('status', ['active', 'isolated'])
            ->whereDoesntHave('odpPort')
            ->whereHas('customer')
            ->get()
            ->sortBy(fn ($s) => $s->customer->name);

        return view('isp.odps.show', [
            'odp' => $odp,
            'unassigned' => $unassigned,
            'mapData' => $this->mapData(collect([$odp]), true),
        ]);
    }

    public function edit(Odp $odp)
    {
        return view('isp.odps.form', ['odp' => $odp] + $this->formData());
    }

    public function update(Request $request, Odp $odp)
    {
        $data = $this->validated($request, $odp);
        $original = $odp->getOriginal();

        try {
            DB::transaction(function () use ($odp, $data, $original) {
                if ($data['capacity'] < $odp->capacity) {
                    $blocked = $odp->ports()->where('port_number', '>', $data['capacity'])->where('status', 'used')->count();
                    if ($blocked) {
                        throw new InvalidArgumentException("Kapasitas tidak bisa dikurangi: {$blocked} port di atas nomor {$data['capacity']} masih terpakai.");
                    }
                    $odp->ports()->where('port_number', '>', $data['capacity'])->delete();
                }
                $odp->update($data);
                $odp->syncPorts();
                AuditLogger::changes('odp.updated', $odp, $original);
            });
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return redirect()->route('app.odps.show', $odp)->with('success', 'ODP diperbarui.');
    }

    public function destroy(Odp $odp)
    {
        if ($odp->ports()->where('status', 'used')->exists()) {
            return back()->with('error', 'ODP masih memiliki pelanggan terpasang. Pindahkan pelanggan terlebih dahulu.');
        }
        $odp->delete();
        AuditLogger::log('odp.archived', $odp, null, ['code' => $odp->code]);

        return redirect()->route('app.odps.index')->with('success', 'ODP diarsipkan.');
    }

    public function assign(Request $request, OdpPort $port, OdpService $service)
    {
        $data = $request->validate(['customer_service_id' => ['required', Rule::exists('customer_services', 'id')->where('tenant_id', $this->tenant()->id)]]);
        try {
            $service->assign($port, CustomerService::findOrFail($data['customer_service_id']));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', "Pelanggan dipasang ke port {$port->port_number}.");
    }

    public function release(Request $request, OdpPort $port, OdpService $service)
    {
        $service->release($port, $request->input('reason'));

        return back()->with('success', "Port {$port->port_number} dikosongkan.");
    }

    public function portStatus(Request $request, OdpPort $port, OdpService $service)
    {
        $data = $request->validate(['broken' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:190']]);
        try {
            $service->setBroken($port, (bool) $data['broken'], $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Status port diperbarui.');
    }

    protected function formData(): array
    {
        return [
            'odcs' => Odc::where('is_active', true)->orderBy('code')->get(),
            'olts' => NetworkDevice::whereIn('type', ['olt', 'mikrotik'])->orderBy('name')->get(),
            'others' => Odp::whereNotNull('latitude')->get(['id', 'code', 'name', 'latitude', 'longitude']),
        ];
    }

    protected function validated(Request $request, ?Odp $odp = null): array
    {
        $tenantId = $this->tenant()->id;
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/\.]+$/', Rule::unique('odps', 'code')->where('tenant_id', $tenantId)->ignore($odp?->id)],
            'name' => ['required', 'string', 'max:120'],
            'odc_id' => ['nullable', Rule::exists('odcs', 'id')->where('tenant_id', $tenantId)],
            'network_device_id' => ['nullable', Rule::exists('network_devices', 'id')->where('tenant_id', $tenantId)],
            'pon_port' => ['nullable', 'string', 'max:30'],
            'splitter' => ['nullable', Rule::in(Odp::SPLITTERS)],
            'capacity' => ['required', 'integer', 'between:1,64'],
            'address' => ['nullable', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:190'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'status' => ['required', Rule::in(array_keys(Odp::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['code' => 'kode ODP', 'capacity' => 'jumlah port', 'odc_id' => 'ODC', 'network_device_id' => 'OLT']);
    }

    /** Data peta: ODP + pelanggan terhubung yang punya koordinat. */
    protected function mapData($odps, bool $withCustomers = true): array
    {
        $odps = $odps->filter(fn ($o) => $o->latitude !== null);
        $ids = $odps->pluck('id');

        $customers = [];
        if ($withCustomers && $ids->isNotEmpty()) {
            $customers = OdpPort::with('service.customer')
                ->whereIn('odp_id', $ids)->where('status', 'used')->get()
                ->filter(fn ($p) => $p->service?->customer?->latitude !== null)
                ->map(fn ($p) => [
                    'odp_id' => $p->odp_id,
                    'name' => $p->service->customer->name,
                    'code' => $p->service->customer->customer_code,
                    'port' => $p->port_number,
                    'lat' => (float) $p->service->customer->latitude,
                    'lng' => (float) $p->service->customer->longitude,
                    'url' => route('app.customers.show', $p->service->customer_id),
                ])->values()->all();
        }

        return [
            'odps' => $odps->map(fn ($o) => [
                'id' => $o->id, 'code' => $o->code, 'name' => $o->name,
                'lat' => (float) $o->latitude, 'lng' => (float) $o->longitude,
                'used' => (int) ($o->used_count ?? $o->ports->where('status', 'used')->count()),
                'capacity' => $o->capacity, 'status' => $o->status,
                'url' => route('app.odps.show', $o),
            ])->values()->all(),
            'customers' => $customers,
        ];
    }
}
