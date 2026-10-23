<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Services\AuditLogger;
use App\Services\UsageMeter;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CustomerServiceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $data = $this->validated($request);
        try {
            $this->createService($customer, $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Layanan ditambahkan.')
            ->with('warnings', session('quota_warning') ? [session('quota_warning')] : []);
    }

    public function update(Request $request, CustomerService $service)
    {
        $data = $this->validated($request, true);
        $original = $service->getOriginal();

        if ($data['status'] === 'terminated' && $service->status !== 'terminated') {
            $data['ended_at'] = now($this->tenant()->timezone)->toDateString();
        }
        $service->update($data);
        AuditLogger::changes('service.updated', $service, $original, $request->input('reason'));

        return back()->with('success', 'Layanan diperbarui.');
    }

    /**
     * Kebijakan kuota tenant: "warn" (default) tetap menyimpan dan memberi peringatan;
     * "block_new" menolak penambahan pelanggan aktif baru di atas kuota. Tidak ada pemutusan otomatis.
     */
    public function createService(Customer $customer, array $data): CustomerService
    {
        $tenant = $this->tenant();
        $quota = UsageMeter::quotaStatus($tenant)['clients'];
        $isNewClient = ! $customer->services()->whereIn('status', UsageMeter::COUNTED_SERVICE_STATUSES)->exists();

        if ($isNewClient && $quota['quota'] !== null && $quota['used'] >= $quota['quota']) {
            if ($tenant->setting('quota_policy', 'warn') === 'block_new') {
                throw new InvalidArgumentException("Kuota client aktif paket ({$quota['quota']}) sudah penuh. Hubungi ALMARA untuk upgrade paket.");
            }
            session()->flash('quota_warning', "Kuota client aktif paket ({$quota['quota']}) terlampaui. Biaya tambahan berlaku sesuai paket.");
        }

        $service = $customer->services()->create([
            'service_package_id' => $data['service_package_id'],
            'status' => 'active',
            'price_override' => $data['price_override'] ?? null,
            'billing_day' => $data['billing_day'] ?? 1,
            'due_days' => $data['due_days'] ?? 10,
            'pppoe_username' => $data['pppoe_username'] ?? null,
            'started_at' => $data['started_at'],
        ]);
        AuditLogger::log('service.created', $service, null, $service->only(['customer_id', 'service_package_id', 'started_at']));

        return $service;
    }

    protected function validated(Request $request, bool $update = false): array
    {
        if ($request->filled('price_override')) {
            $request->merge(['price_override' => Money::parse($request->input('price_override'))]);
        }
        $tenantId = $this->tenant()->id;

        return $request->validate([
            'service_package_id' => ['required', Rule::exists('service_packages', 'id')->where('tenant_id', $tenantId)],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'due_days' => ['required', 'integer', 'between:0,60'],
            'pppoe_username' => ['nullable', 'string', 'max:100'],
            'started_at' => [$update ? 'sometimes' : 'required', 'date'],
            'status' => [$update ? 'required' : 'nullable', Rule::in(array_keys(CustomerService::STATUSES))],
        ]);
    }
}
