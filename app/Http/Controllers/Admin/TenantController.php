<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantProfile;
use App\Models\TenantSubscription;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use App\Services\TenantProvisioningService;
use App\Services\UsageMeter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super Admin → Manajemen Supplier ISP (perusahaan ISP penyewa platform).
 * @2026-Alifiandra
 */
class TenantController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $tenants = Tenant::query()
            ->with(['profile', 'currentSubscription.plan'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")
                ->orWhereHas('profile', fn ($p) => $p->where('email', 'like', "%{$q}%")->orWhere('pic_name', 'like', "%{$q}%")->orWhere('whatsapp', 'like', "%{$q}%"))))
            ->when($request->query('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($request->query('plan'), fn ($w, $p) => $w->whereHas('currentSubscription', fn ($s) => $s->where('saas_plan_id', $p)))
            ->when($request->query('expiring'), fn ($w) => $w->whereHas('currentSubscription', fn ($s) => $s->whereDate('ends_at', '<=', now()->addDays(14))))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.tenants.index', [
            'tenants' => $tenants,
            'plans' => SaasPlan::orderBy('sort_order')->get(),
            'counts' => Tenant::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create()
    {
        return view('admin.tenants.create', [
            'plans' => SaasPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'suggestedCode' => $this->suggestCode(),
        ]);
    }

    public function store(Request $request, TenantProvisioningService $service)
    {
        $request->merge(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $request->input()));
        $request->merge([
            'code' => strtoupper((string) $request->input('code')) ?: $this->suggestCode(),
            'subdomain' => $request->filled('subdomain') ? strtolower($request->input('subdomain')) : null,
            'email' => strtolower((string) $request->input('email')),
            'admin_email' => strtolower((string) $request->input('admin_email')),
            'whatsapp' => preg_replace('/[^0-9+]/', '', (string) $request->input('whatsapp')),
        ]);

        $data = $request->validate($this->rules() + [
            'saas_plan_id' => ['required', Rule::exists('saas_plans', 'id')->where('is_active', true)],
            'period' => ['required', 'in:monthly,yearly'],
            'initial_status' => ['required', 'in:trial,active'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'custom_domain' => ['nullable', 'string', 'max:190', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i', 'unique:tenant_domains,domain'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
        ], [], $this->attributes());

        if ($data['period'] === 'yearly' && ! SaasPlan::find($data['saas_plan_id'])?->price_yearly) {
            return back()->withInput()->withErrors(['period' => 'Paket ini tidak memiliki harga tahunan di katalog.']);
        }

        $tenant = $service->create($data, $request->file('logo'));

        $warnings = $this->duplicateWarnings($data, $tenant->id);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', "ISP {$tenant->name} berhasil dibuat. Provisioning berjalan di antrean.")
            ->with('warnings', $warnings);
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['profile', 'domains', 'subscriptions.plan', 'provisioningJobs', 'users.roles']);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'quota' => UsageMeter::quotaStatus($tenant),
            'plans' => SaasPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'logs' => AuditLog::where('tenant_id', $tenant->id)->with('user')->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.edit', ['tenant' => $tenant->load('profile')]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $request->merge([
            'subdomain' => $request->filled('subdomain') ? strtolower(trim($request->input('subdomain'))) : null,
            'email' => strtolower(trim((string) $request->input('email'))),
            'whatsapp' => preg_replace('/[^0-9+]/', '', (string) $request->input('whatsapp')),
        ]);
        $rules = $this->rules($tenant);
        unset($rules['code']);
        $data = $request->validate($rules + [
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
        ], [], $this->attributes());

        DB::transaction(function () use ($tenant, $data, $request) {
            $before = $tenant->only(['name', 'subdomain', 'brand_name', 'theme', 'timezone']) + ($tenant->profile?->only(['pic_name', 'whatsapp', 'email', 'address']) ?? []);
            $tenant->fill(collect($data)->only(['name', 'subdomain', 'brand_name', 'theme', 'timezone'])->all());
            if ($request->hasFile('logo')) {
                $tenant->logo_path = $request->file('logo')->store('tenant-logos', 'public');
            }
            $tenant->save();
            $tenant->profile()->updateOrCreate([], collect($data)->only((new TenantProfile)->getFillable())->all());
            AuditLogger::log('tenant.updated', $tenant, $before, collect($data)->except('logo')->all(), null, $tenant->id);
        });

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Data ISP diperbarui.');
    }

    public function status(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Tenant::STATUSES))],
            'reason' => ['required_if:status,suspended', 'nullable', 'string', 'max:250'],
        ]);

        if (! $tenant->canTransitionTo($data['status'])) {
            return back()->with('error', "Perubahan status dari {$tenant->statusLabel()} ke ".Tenant::STATUSES[$data['status']].' tidak diizinkan.');
        }

        $before = $tenant->status;
        $tenant->transitionTo($data['status']);
        if ($data['status'] === 'suspended') {
            $tenant->forceFill(['suspended_reason' => $data['reason']])->save();
        }
        AuditLogger::log('tenant.status_changed', $tenant, ['status' => $before], ['status' => $tenant->status], $data['reason'] ?? null, $tenant->id);

        return back()->with('success', 'Status ISP menjadi '.$tenant->statusLabel().'. Data tenant tidak dihapus.');
    }

    public function retryProvisioning(Tenant $tenant, TenantProvisioningService $service)
    {
        $service->retry($tenant);

        return back()->with('success', 'Provisioning dijadwalkan ulang.');
    }

    /** Proses manual terkontrol bila email undangan tidak sampai. Link hanya ditampilkan sekali. */
    public function activationLink(Request $request, Tenant $tenant)
    {
        $admin = $tenant->users()->whereNull('password')->findOrFail($request->integer('user_id'));
        [, $token] = UserInvitation::issue($admin, auth()->id());
        AuditLogger::log('tenant.activation_link_issued', $admin, null, ['user' => $admin->email], null, $tenant->id);

        return back()->with('activation_link', route('activation.show', $token));
    }

    /** Ganti paket: langganan lama diakhiri, langganan baru berlaku mulai tanggal efektif (snapshot katalog). */
    public function changePlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'saas_plan_id' => ['required', Rule::exists('saas_plans', 'id')->where('is_active', true)],
            'period' => ['required', 'in:monthly,yearly'],
            'status' => ['required', 'in:trial,active'],
            'effective_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:250'],
        ]);
        $plan = SaasPlan::findOrFail($data['saas_plan_id']);
        if ($data['period'] === 'yearly' && ! $plan->price_yearly) {
            return back()->with('error', 'Paket ini tidak memiliki harga tahunan.');
        }

        DB::transaction(function () use ($tenant, $plan, $data) {
            $old = $tenant->currentSubscription;
            $start = \Carbon\Carbon::parse($data['effective_date']);
            if ($old) {
                $old->update(['status' => 'ended', 'ends_at' => $start->copy()->subDay()]);
            }
            $new = $tenant->subscriptions()->create(TenantSubscription::snapshotFromPlan($plan, $data['period']) + [
                'status' => $data['status'],
                'starts_at' => $start,
                'ends_at' => $data['period'] === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth(),
                'trial_ends_at' => $data['status'] === 'trial' ? $start->copy()->addDays(14) : null,
            ]);
            AuditLogger::log('subscription.changed', $new, ['plan' => $old?->plan?->code], ['plan' => $plan->code, 'period' => $data['period'], 'effective' => $data['effective_date']], $data['reason'], $tenant->id);
        });

        return back()->with('success', 'Paket langganan diperbarui.');
    }

    public function addDomain(Request $request, Tenant $tenant)
    {
        $request->merge(['domain' => strtolower(trim((string) $request->input('domain')))]);
        $data = $request->validate(['domain' => ['required', 'max:190', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', 'unique:tenant_domains,domain']]);
        $domain = $tenant->domains()->create(['domain' => $data['domain'], 'type' => 'custom', 'status' => 'pending', 'verification_token' => 'almara-verify-'.Str::random(32)]);
        AuditLogger::log('tenant.domain_added', $domain, null, ['domain' => $domain->domain], null, $tenant->id);

        return back()->with('success', 'Domain ditambahkan dengan status pending. Minta ISP menambahkan TXT record lalu klik Verifikasi.');
    }

    /** Verifikasi kepemilikan via DNS TXT. TLS tetap harus disiapkan di server sebelum domain dipakai. */
    public function verifyDomain(Tenant $tenant, TenantDomain $domain)
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);
        $records = @dns_get_record('_almara.'.$domain->domain, DNS_TXT) ?: [];
        $found = collect($records)->contains(fn ($r) => trim($r['txt'] ?? '') === $domain->verification_token);

        $domain->forceFill($found
            ? ['status' => 'verified', 'verified_at' => now(), 'last_error' => null]
            : ['status' => 'failed', 'last_error' => 'TXT record _almara.'.$domain->domain.' belum ditemukan / tidak cocok.'])->save();
        AuditLogger::log('tenant.domain_verify', $domain, null, ['status' => $domain->status], null, $tenant->id);

        return back()->with($found ? 'success' : 'error', $found ? 'Domain terverifikasi (DNS). Pastikan sertifikat TLS sudah aktif di server.' : $domain->last_error);
    }

    protected function rules(?Tenant $tenant = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/', 'unique:tenants,code'],
            'pic_name' => ['required', 'string', 'max:120'],
            'whatsapp' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'office_phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'address' => ['required', 'string', 'max:500'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'url:http,https', 'max:190'],
            'subdomain' => ['nullable', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::notIn(['www', 'admin', 'api', 'app', 'mail']), Rule::unique('tenants', 'subdomain')->ignore($tenant?->id)],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'theme' => ['nullable', 'in:default,light,dark'],
            'timezone' => ['nullable', 'timezone:all'],
        ];
    }

    protected function attributes(): array
    {
        return [
            'name' => 'nama perusahaan', 'code' => 'kode ISP', 'pic_name' => 'nama PIC', 'whatsapp' => 'nomor WhatsApp',
            'email' => 'email perusahaan', 'address' => 'alamat', 'province' => 'provinsi', 'city' => 'kota/kabupaten',
            'saas_plan_id' => 'paket SaaS', 'admin_name' => 'nama administrator', 'admin_email' => 'email login administrator',
            'custom_domain' => 'domain custom',
        ];
    }

    protected function suggestCode(): string
    {
        do {
            $code = 'ISP-'.strtoupper(Str::random(5));
        } while (Tenant::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /** Peringatan (bukan penolakan) — kontak bisa sah dipakai bersama. */
    protected function duplicateWarnings(array $data, int $exceptId): array
    {
        $w = [];
        if (Tenant::where('id', '!=', $exceptId)->where('name', $data['name'])->exists()) {
            $w[] = 'Nama ISP yang sama sudah terdaftar.';
        }
        if (TenantProfile::where('tenant_id', '!=', $exceptId)->where('email', $data['email'])->exists()) {
            $w[] = 'Email perusahaan sudah dipakai ISP lain.';
        }
        if (TenantProfile::where('tenant_id', '!=', $exceptId)->where('whatsapp', $data['whatsapp'])->exists()) {
            $w[] = 'Nomor WhatsApp sudah dipakai ISP lain.';
        }

        return $w;
    }
}
