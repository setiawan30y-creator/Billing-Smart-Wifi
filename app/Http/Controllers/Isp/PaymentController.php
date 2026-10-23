<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $payments = Payment::with(['customer', 'receiver'])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('number', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($request->query('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($request->query('method'), fn ($w, $m) => $w->where('method', $m))
            ->latest()->paginate(20)->withQueryString();

        return view('isp.payments.index', ['payments' => $payments]);
    }

    public function create(Request $request)
    {
        $invoice = $request->integer('invoice') ? Invoice::with('customer')->find($request->integer('invoice')) : null;
        $customerId = $invoice?->customer_id ?? $request->integer('customer');

        return view('isp.payments.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_code']),
            'customerId' => $customerId,
            'invoice' => $invoice,
            'openInvoices' => $customerId ? Invoice::where('customer_id', $customerId)->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get() : collect(),
            'idempotencyKey' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function store(Request $request, PaymentService $service)
    {
        $tenantId = $this->tenant()->id;
        $request->merge(['amount' => Money::parse($request->input('amount'))]);
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'transfer', 'other'])],
            'reference' => ['required_if:method,transfer', 'nullable', 'string', 'max:120'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)->where('customer_id', $request->integer('customer_id'))],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'uuid'],
        ], ['reference.required_if' => 'Nomor referensi transfer wajib diisi.']);

        $proof = $request->hasFile('proof')
            ? $request->file('proof')->store('tenants/'.$tenantId.'/payments', config('almara.private_disk'))
            : null;

        try {
            $payment = $service->record($this->tenant(), $data, $proof);
            if ($request->boolean('verify_now') && $request->user()->can('payments.verify') && $payment->status === 'pending') {
                $service->verify($this->tenant(), $payment);
            }
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return redirect()->route('app.payments.show', $payment)->with('success', $payment->fresh()->status === 'verified'
            ? 'Pembayaran dicatat dan terverifikasi.'
            : 'Pembayaran dicatat dan menunggu verifikasi.');
    }

    public function show(Payment $payment)
    {
        return view('isp.payments.show', [
            'payment' => $payment->load('customer', 'allocations.invoice', 'receiver', 'verifier'),
            'openInvoices' => Invoice::where('customer_id', $payment->customer_id)->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get(),
        ]);
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->status === 'verified', 404, 'Kuitansi hanya untuk pembayaran terverifikasi.');

        return view('isp.payments.receipt', ['payment' => $payment->load('customer', 'allocations.invoice'), 'tenant' => $this->tenant()->load('profile')]);
    }

    public function proof(Payment $payment)
    {
        $disk = Storage::disk(config('almara.private_disk'));
        abort_unless($payment->proof_path && $disk->exists($payment->proof_path), 404);

        return response($disk->get($payment->proof_path), 200, ['Content-Type' => $disk->mimeType($payment->proof_path), 'Cache-Control' => 'no-store, private']);
    }

    public function verify(Request $request, Payment $payment, PaymentService $service)
    {
        $tenantId = $this->tenant()->id;
        $data = $request->validate([
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)->where('customer_id', $payment->customer_id)],
        ]);
        try {
            $service->verify($this->tenant(), $payment, $data['invoice_ids'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Pembayaran terverifikasi dan dialokasikan ke invoice.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        try {
            $service->reject($payment, $data['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Pembayaran ditolak.');
    }

    public function reverse(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        try {
            $service->reverse($payment, $data['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Pembayaran dibalik (reversal). Status invoice dihitung ulang.');
    }
}
