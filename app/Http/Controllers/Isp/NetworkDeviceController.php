<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Services\AuditLogger;
use App\Services\UsageMeter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registry perangkat jaringan. Monitoring read-only (polling MikroTik/SNMP/ICMP)
 * adalah fase berikutnya; halaman ini tidak menampilkan metrik yang belum terukur.
 */
class NetworkDeviceController extends Controller
{
    public function index()
    {
        return view('isp.devices.index', [
            'devices' => NetworkDevice::orderBy('type')->orderBy('name')->get(),
            'quota' => UsageMeter::quotaStatus($this->tenant())['routers'],
        ]);
    }

    public function create()
    {
        return view('isp.devices.form', ['device' => new NetworkDevice(['type' => 'mikrotik', 'api_port' => 8728, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $tenant = $this->tenant();
        $quota = UsageMeter::quotaStatus($tenant)['routers'];
        $warning = null;
        if ($data['type'] === 'mikrotik' && $quota['quota'] !== null && $quota['used'] >= $quota['quota']) {
            if ($tenant->setting('quota_policy', 'warn') === 'block_new') {
                return back()->withInput()->with('error', "Kuota MikroTik paket ({$quota['quota']}) sudah penuh.");
            }
            $warning = "Kuota MikroTik paket ({$quota['quota']}) terlampaui. Biaya tambahan per router berlaku sesuai paket.";
        }

        $device = new NetworkDevice(collect($data)->except('password')->all());
        if (! empty($data['password'])) {
            $device->password = $data['password'];
        }
        $device->save();
        AuditLogger::log('device.created', $device, null, $device->only(['name', 'type', 'host']));

        return redirect()->route('app.devices.index')->with('success', 'Perangkat ditambahkan.')->with('warnings', $warning ? [$warning] : []);
    }

    public function edit(NetworkDevice $device)
    {
        return view('isp.devices.form', ['device' => $device]);
    }

    public function update(Request $request, NetworkDevice $device)
    {
        $data = $this->validated($request);
        $original = $device->getOriginal();
        $device->fill(collect($data)->except('password')->all());
        if (! empty($data['password'])) {
            $device->password = $data['password'];
        }
        $device->save();
        AuditLogger::changes('device.updated', $device, $original);

        return redirect()->route('app.devices.index')->with('success', 'Perangkat diperbarui.');
    }

    public function destroy(NetworkDevice $device)
    {
        $device->delete(); // soft delete (arsip)
        AuditLogger::log('device.archived', $device, null, null);

        return back()->with('success', 'Perangkat diarsipkan.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(NetworkDevice::TYPES))],
            'host' => ['required', 'string', 'max:190', 'regex:/^[a-zA-Z0-9.\-:]+$/'],
            'api_port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:190'],
            'location' => ['nullable', 'string', 'max:190'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
