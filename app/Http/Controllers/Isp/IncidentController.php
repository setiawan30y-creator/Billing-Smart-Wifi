<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\NetworkIncident;
use App\Models\Odc;
use App\Models\Odp;
use App\Services\IncidentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        return view('isp.incidents.index', [
            'incidents' => NetworkIncident::withCount('affectedCustomers')
                ->when($request->query('status', 'aktif') === 'aktif', fn ($q) => $q->where('status', '!=', 'resolved'))
                ->latest('started_at')->paginate(20)->withQueryString(),
            'tenant' => $this->tenant(),
        ]);
    }

    public function create()
    {
        return view('isp.incidents.create', $this->options());
    }

    public function store(Request $request, IncidentService $service)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'severity' => ['required', Rule::in(array_keys(NetworkIncident::SEVERITIES))],
            'started_at' => ['required', 'date'],
            'estimated_recovery_at' => ['nullable', 'date', 'after:started_at'],
            'affected' => ['required', 'array', 'min:1'],
            'affected.*' => ['string', 'regex:/^(odp|odc|device):\d+$/'],
        ], ['affected.required' => 'Pilih minimal satu ODP/ODC/perangkat yang terdampak.']);

        $affected = $this->parseAffected($data['affected']);
        unset($data['affected']);
        $inc = $service->create($this->tenant(), $data, $affected);

        return redirect()->route('app.incidents.show', $inc)->with('success', "Insiden {$inc->number} dibuat · {$inc->affectedCustomers()->count()} pelanggan terdampak (dari topologi).");
    }

    public function show(NetworkIncident $incident)
    {
        return view('isp.incidents.show', [
            'incident' => $incident->load(['devices', 'updates.user']),
            'customers' => $incident->affectedCustomers()->with('customer')->get(),
        ] + $this->options());
    }

    public function update(Request $request, NetworkIncident $incident, IncidentService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(NetworkIncident::STATUSES))],
            'note' => ['required', 'string', 'max:2000'],
            'confirmed_cause' => ['nullable', 'string', 'max:190'],
            'estimated_recovery_at' => ['nullable', 'date'],
        ]);
        $service->addUpdate($incident, $data['status'], $data['note'], $request->has('confirmed_cause') ? (string) $data['confirmed_cause'] : null, $request->has('estimated_recovery_at') ? (string) $data['estimated_recovery_at'] : null);

        return back()->with('success', 'Pembaruan insiden disimpan.');
    }

    public function affected(Request $request, NetworkIncident $incident, IncidentService $service)
    {
        $data = $request->validate(['affected' => ['required', 'array', 'min:1'], 'affected.*' => ['string', 'regex:/^(odp|odc|device):\d+$/']]);
        $service->setAffected($incident, $this->parseAffected($data['affected']));

        return back()->with('success', 'Cakupan insiden diperbarui · '.$incident->affectedCustomers()->count().' pelanggan terdampak.');
    }

    protected function parseAffected(array $items): array
    {
        $valid = ['odp' => Odp::pluck('id')->all(), 'odc' => Odc::pluck('id')->all(), 'device' => NetworkDevice::pluck('id')->all()];
        $out = [];
        foreach ($items as $it) {
            [$t, $id] = explode(':', $it);
            if (in_array((int) $id, $valid[$t], true)) {
                $out[] = [$t, (int) $id];
            }
        }
        abort_if(! $out, 422, 'Cakupan tidak valid.');

        return $out;
    }

    protected function options(): array
    {
        return [
            'odps' => Odp::orderBy('code')->get(['id', 'code', 'name']),
            'odcs' => Odc::orderBy('code')->get(['id', 'code', 'name']),
            'devices' => NetworkDevice::orderBy('name')->get(['id', 'name', 'type']),
        ];
    }
}
