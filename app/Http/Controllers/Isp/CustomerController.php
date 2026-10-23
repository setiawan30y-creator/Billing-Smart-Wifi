<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ServicePackage;
use App\Services\AuditLogger;
use App\Services\SequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Data pelanggan & instalasi. KTP (sisi depan saja) & foto rumah di disk privat;
 * NIK terenkripsi dan ditampilkan ter-masking.
 * @2026-Alifiandra
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $customers = Customer::query()
            ->with(['services.package'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('customer_code', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('address', 'like', "%{$q}%")))
            ->when($request->query('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($request->query('package'), fn ($w, $p) => $w->whereHas('services', fn ($s) => $s->where('service_package_id', $p)->where('status', 'active')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('isp.customers.index', ['customers' => $customers, 'packages' => ServicePackage::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('isp.customers.form', [
            'customer' => new Customer(['status' => 'active', 'ownership' => 'own']),
            'packages' => ServicePackage::where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $tenant = $this->tenant();

        $customer = DB::transaction(function () use ($data, $request, $tenant) {
            $customer = new Customer(collect($data)->only((new Customer)->getFillable())->all());
            $customer->customer_code = $data['customer_code'] ?: sprintf('PLG-%05d', SequenceService::next($tenant->id, 'customer'));
            $customer->setNik($data['nik'] ?? null);
            $customer->save();
            $this->storeFiles($request, $customer);

            if (! empty($data['service_package_id'])) {
                app(CustomerServiceController::class)->createService($customer, [
                    'service_package_id' => $data['service_package_id'],
                    'billing_day' => $data['billing_day'] ?? 1,
                    'due_days' => 10,
                    'started_at' => now($tenant->timezone)->toDateString(),
                ]);
            }

            AuditLogger::log('customer.created', $customer, null, $customer->only(['customer_code', 'name', 'phone']));

            return $customer;
        });

        return redirect()->route('app.customers.show', $customer)->with('success', 'Pelanggan ditambahkan.')
            ->with('warnings', session('quota_warning') ? [session('quota_warning')] : []);
    }

    public function show(Customer $customer)
    {
        return view('isp.customers.show', [
            'customer' => $customer->load(['services.package']),
            'invoices' => $customer->invoices()->latest('issue_date')->limit(12)->get(),
            'payments' => $customer->payments()->latest()->limit(10)->get(),
            'packages' => ServicePackage::where('is_active', true)->orderBy('price')->get(),
            'outstanding' => $customer->outstanding(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('isp.customers.form', ['customer' => $customer, 'packages' => collect()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request, $customer);
        $original = $customer->getOriginal();

        DB::transaction(function () use ($customer, $data, $request, $original) {
            $customer->fill(collect($data)->only((new Customer)->getFillable())->all());
            if ($data['customer_code']) {
                $customer->customer_code = $data['customer_code'];
            }
            if ($request->filled('nik')) {
                $customer->setNik($data['nik']);
            }
            $customer->save();
            $this->storeFiles($request, $customer);
            AuditLogger::changes('customer.updated', $customer, $original);
        });

        return redirect()->route('app.customers.show', $customer)->with('success', 'Data pelanggan diperbarui.');
    }

    /** Akses berkas privat (tidak ada URL publik). Setiap akses KTP diaudit. */
    public function file(Customer $customer, string $type)
    {
        $path = $type === 'ktp' ? $customer->ktp_path : $customer->house_photo_path;
        if ($type === 'ktp') {
            Gate::authorize('customers.sensitive.view');
            AuditLogger::log('customer.ktp_viewed', $customer);
        }
        $disk = Storage::disk(config('almara.private_disk'));
        abort_unless($path && $disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path),
            'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'inline',
        ]);
    }

    protected function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge([
            'phone' => preg_replace('/[^0-9+]/', '', (string) $request->input('phone')),
            'nik' => $request->filled('nik') ? preg_replace('/\D/', '', $request->input('nik')) : null,
            'customer_code' => $request->filled('customer_code') ? strtoupper(trim($request->input('customer_code'))) : null,
        ]);

        return $request->validate([
            'customer_code' => ['nullable', 'alpha_dash', 'max:30', Rule::unique('customers', 'customer_code')->where('tenant_id', $this->tenant()->id)->ignore($customer?->id)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'address' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:190'],
            'maps_url' => ['nullable', 'url:http,https', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ownership' => ['required', 'in:own,rent'],
            'owner_name' => ['required_if:ownership,rent', 'nullable', 'string', 'max:150'],
            'nik' => ['nullable', 'digits:16'],
            'ktp' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'house_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'service_package_id' => ['nullable', Rule::exists('service_packages', 'id')->where('tenant_id', $this->tenant()->id)->where('is_active', true)],
            'billing_day' => ['nullable', 'integer', 'between:1,28'],
        ], [
            'owner_name.required_if' => 'Nama pemilik rumah wajib diisi bila rumah berstatus kontrak.',
            'phone.regex' => 'Format nomor WhatsApp tidak valid (contoh: 08xxxxxxxxxx).',
        ], ['name' => 'nama', 'phone' => 'nomor WhatsApp', 'address' => 'alamat pemasangan', 'nik' => 'NIK']);
    }

    protected function storeFiles(Request $request, Customer $customer): void
    {
        $disk = config('almara.private_disk');
        $dir = 'tenants/'.$customer->tenant_id.'/customers/'.$customer->id;
        $changed = false;
        if ($request->hasFile('ktp')) {
            $customer->ktp_path = $request->file('ktp')->store($dir, $disk);
            $changed = true;
        }
        if ($request->hasFile('house_photo')) {
            $customer->house_photo_path = $request->file('house_photo')->store($dir, $disk);
            $changed = true;
        }
        if ($changed) {
            $customer->save();
        }
    }
}
