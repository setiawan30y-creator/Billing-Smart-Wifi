<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\Odc;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OdcController extends Controller
{
    public function index()
    {
        return view('isp.odcs.index', ['odcs' => Odc::with('olt')->withCount('odps')->orderBy('code')->get()]);
    }

    public function create()
    {
        return view('isp.odcs.form', ['odc' => new Odc(['is_active' => true]), 'olts' => NetworkDevice::whereIn('type', ['olt', 'mikrotik'])->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $odc = Odc::create($this->validated($request));
        AuditLogger::log('odc.created', $odc, null, $odc->only(['code', 'name']));

        return redirect()->route('app.odcs.index')->with('success', 'ODC ditambahkan.');
    }

    public function edit(Odc $odc)
    {
        return view('isp.odcs.form', ['odc' => $odc, 'olts' => NetworkDevice::whereIn('type', ['olt', 'mikrotik'])->orderBy('name')->get()]);
    }

    public function update(Request $request, Odc $odc)
    {
        $original = $odc->getOriginal();
        $odc->update($this->validated($request, $odc));
        AuditLogger::changes('odc.updated', $odc, $original);

        return redirect()->route('app.odcs.index')->with('success', 'ODC diperbarui.');
    }

    protected function validated(Request $request, ?Odc $odc = null): array
    {
        $tenantId = $this->tenant()->id;
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/\.]+$/', Rule::unique('odcs', 'code')->where('tenant_id', $tenantId)->ignore($odc?->id)],
            'name' => ['required', 'string', 'max:120'],
            'network_device_id' => ['nullable', Rule::exists('network_devices', 'id')->where('tenant_id', $tenantId)],
            'capacity' => ['nullable', 'integer', 'between:1,1024'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['code' => 'kode ODC']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
