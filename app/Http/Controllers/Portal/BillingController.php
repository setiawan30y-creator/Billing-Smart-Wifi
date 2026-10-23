<?php

namespace App\Http\Controllers\Portal;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Tagihan & pembayaran pelanggan. Bukti transfer dari portal masuk sebagai pembayaran PENDING
 * dan baru mengurangi tagihan setelah diverifikasi staf ISP.
 */
class BillingController extends PortalController
{
    public function invoices(Request $request)
    {
        return view('portal.invoices', [
            'tenant' => $this->portalTenant($request),
            'invoices' => Invoice::where('customer_id', $this->account()->customer_id)->where('status', '!=', 'void')->latest('issue_date')->paginate(12),
        ]);
    }

    public function invoice(Request $request, string $tenantCode, int $invoice)
    {
        $inv = Invoice::with('items')->where('customer_id', $this->account()->customer_id)->findOrFail($invoice);

        return view('isp.invoices.print', ['invoice' => $inv->load('customer'), 'tenant' => $this->portalTenant($request)->load('profile')]);
    }

    public function payments(Request $request)
    {
        return view('portal.payments', [
            'tenant' => $this->portalTenant($request),
            'payments' => Payment::with('allocations.invoice')->where('customer_id', $this->account()->customer_id)->latest()->paginate(12),
        ]);
    }

    public function receipt(Request $request, string $tenantCode, int $payment)
    {
        $p = Payment::with('allocations.invoice', 'customer')->where('customer_id', $this->account()->customer_id)->where('status', 'verified')->findOrFail($payment);

        return view('isp.payments.receipt', ['payment' => $p, 'tenant' => $this->portalTenant($request)->load('profile')]);
    }

    public function pay(Request $request, string $tenantCode, int $invoice)
    {
        $inv = Invoice::where('customer_id', $this->account()->customer_id)->whereIn('status', ['unpaid', 'partial'])->findOrFail($invoice);

        return view('portal.pay', ['tenant' => $this->portalTenant($request)->load('profile'), 'invoice' => $inv, 'idempotencyKey' => (string) \Illuminate\Support\Str::uuid()]);
    }

    public function submitProof(Request $request, string $tenantCode, int $invoice, PaymentService $payments)
    {
        $tenant = $this->portalTenant($request);
        $inv = Invoice::where('customer_id', $this->account()->customer_id)->whereIn('status', ['unpaid', 'partial'])->findOrFail($invoice);
        $request->merge(['amount' => Money::parse($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'idempotency_key' => ['nullable', 'uuid'],
        ], [], ['proof' => 'bukti transfer', 'amount' => 'jumlah']);

        if (Payment::where('customer_id', $inv->customer_id)->where('status', 'pending')->whereJsonContains('planned_invoice_ids', $inv->id)->exists()) {
            return back()->with('error', 'Bukti pembayaran untuk invoice ini sudah dikirim dan sedang diverifikasi.');
        }

        try {
            $proof = $request->file('proof')->store('tenants/'.$tenant->id.'/payments', config('almara.private_disk'));
            $payment = $payments->record($tenant, [
                'customer_id' => $inv->customer_id, 'amount' => $data['amount'], 'method' => 'transfer',
                'reference' => $data['reference'] ?? null, 'paid_at' => $data['paid_at'], 'invoice_ids' => [$inv->id],
                'idempotency_key' => $data['idempotency_key'] ?? null, 'notes' => 'Bukti transfer dikirim dari portal pelanggan',
            ], $proof);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }
        AuditLogger::log('portal.payment_proof', $payment, null, ['invoice' => $inv->number, 'amount' => $data['amount']], null, $tenant->id);

        return redirect()->route('portal.payments', $tenantCode)->with('success', 'Bukti pembayaran terkirim. Tagihan akan diperbarui setelah diverifikasi oleh '.$tenant->displayName().'.');
    }
}
