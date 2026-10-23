<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\Tenant;
use App\Services\SaasBillingService;
use App\Support\Money;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SaasInvoiceController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.saas-invoices.index', [
            'invoices' => SaasInvoice::with('tenant')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
                ->latest()->paginate(20)->withQueryString(),
            'tenants' => Tenant::whereIn('status', ['trial', 'active', 'suspended'])->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function generate(Request $request, SaasBillingService $service)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'period' => ['required', 'date_format:Y-m'],
        ]);
        try {
            $invoice = $service->generate(Tenant::findOrFail($data['tenant_id']), $data['period']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return redirect()->route('admin.saas-invoices.show', $invoice)->with('success', 'Invoice SaaS dibuat.');
    }

    public function show(SaasInvoice $invoice)
    {
        return view('admin.saas-invoices.show', ['invoice' => $invoice->load('tenant.profile', 'items', 'payments.recorder', 'usage')]);
    }

    public function pay(Request $request, SaasInvoice $invoice, SaasBillingService $service)
    {
        $request->merge(['amount' => Money::parse($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:transfer,cash,gateway,other'],
            'reference' => ['required', 'string', 'max:120'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $service->recordPayment($invoice, $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Pembayaran SaaS dicatat.');
    }

    public function reverse(Request $request, SaasPayment $payment, SaasBillingService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        try {
            $service->reversePayment($payment, $data['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Pembayaran dibalik (reversal).');
    }
}
