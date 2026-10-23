<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasPlan;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaasPlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => SaasPlan::withCount(['subscriptions' => fn ($q) => $q->whereIn('status', ['trial', 'active', 'past_due'])])->orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new SaasPlan(['is_active' => true, 'features' => ['billing', 'payments_manual']])]);
    }

    public function store(Request $request)
    {
        $plan = SaasPlan::create($this->validated($request));
        AuditLogger::log('saas_plan.created', $plan, null, $plan->toArray());

        return redirect()->route('admin.plans.index')->with('success', 'Paket SaaS ditambahkan.');
    }

    public function edit(SaasPlan $plan)
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    /** Perubahan katalog tidak mengubah langganan berjalan (langganan memakai snapshot). */
    public function update(Request $request, SaasPlan $plan)
    {
        $original = $plan->getOriginal();
        $plan->update($this->validated($request, $plan));
        AuditLogger::changes('saas_plan.updated', $plan, $original, $request->input('reason'));

        return redirect()->route('admin.plans.index')->with('success', 'Paket SaaS diperbarui. Langganan berjalan tetap memakai snapshot harga lama.');
    }

    protected function validated(Request $request, ?SaasPlan $plan = null): array
    {
        foreach (['price_monthly', 'price_yearly', 'overage_client_price', 'overage_router_price'] as $f) {
            if ($request->filled($f)) {
                $request->merge([$f => Money::parse($request->input($f))]);
            }
        }
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('saas_plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'required_unless:is_custom,1'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'client_quota' => ['nullable', 'integer', 'min:1'],
            'router_quota' => ['nullable', 'integer', 'min:0'],
            'overage_client_price' => ['nullable', 'numeric', 'min:0'],
            'overage_router_price' => ['nullable', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(array_keys(SaasPlan::FEATURES))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_custom'] = $request->boolean('is_custom');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_price_approved'] = $request->boolean('is_price_approved');
        $data['overage_client_price'] ??= 0;
        $data['overage_router_price'] ??= 0;
        $data['features'] ??= [];
        $data['sort_order'] ??= 0;

        return $data;
    }
}
