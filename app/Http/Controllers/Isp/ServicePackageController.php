<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicePackageController extends Controller
{
    public function index()
    {
        return view('isp.packages.index', ['packages' => ServicePackage::withCount(['services' => fn ($q) => $q->where('status', 'active')])->orderBy('price')->get()]);
    }

    public function create()
    {
        return view('isp.packages.form', ['package' => new ServicePackage(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $package = ServicePackage::create($this->validated($request));
        AuditLogger::log('package.created', $package, null, $package->only(['code', 'name', 'price']));

        return redirect()->route('app.packages.index')->with('success', 'Paket layanan ditambahkan.');
    }

    public function edit(ServicePackage $package)
    {
        return view('isp.packages.form', ['package' => $package]);
    }

    public function update(Request $request, ServicePackage $package)
    {
        $original = $package->getOriginal();
        $package->update($this->validated($request, $package));
        AuditLogger::changes('package.updated', $package, $original);

        return redirect()->route('app.packages.index')->with('success', 'Paket diperbarui. Invoice yang sudah terbit tidak berubah.');
    }

    protected function validated(Request $request, ?ServicePackage $package = null): array
    {
        $request->merge(['price' => Money::parse($request->input('price')), 'code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('service_packages', 'code')->where('tenant_id', $this->tenant()->id)->ignore($package?->id)],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'download_mbps' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'upload_mbps' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
