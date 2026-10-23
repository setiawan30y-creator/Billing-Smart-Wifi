<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $tz = $this->tenant()->timezone;

        $invoices = Invoice::with('customer')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('number', 'like', "%{$q}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('customer_code', 'like', "%{$q}%"))))
            ->when($request->query('status') === 'overdue', fn ($w) => $w->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', now($tz)->toDateString()))
            ->when($request->query('status') && $request->query('status') !== 'overdue', fn ($w) => $w->where('status', $request->query('status')))
            ->when($request->query('period'), fn ($w, $p) => $w->where('period', $p))
            ->latest('issue_date')->latest('id')->paginate(20)->withQueryString();

        return view('isp.invoices.index', ['invoices' => $invoices, 'defaultPeriod' => now($tz)->format('Y-m')]);
    }

    public function generate(Request $request, InvoiceService $service)
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'notify' => ['nullable', 'boolean']]);
        try {
            $r = $service->generateForPeriod($this->tenant(), $data['period'], $request->boolean('notify'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', "Generate invoice {$data['period']}: {$r['created']} dibuat, {$r['skipped']} dilewati (sudah ada).");
    }

    public function create(Request $request)
    {
        return view('isp.invoices.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_code']),
            'selected' => $request->integer('customer'),
            'today' => now($this->tenant()->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, InvoiceService $service)
    {
        $items = collect($request->input('items', []))->filter(fn ($i) => filled($i['description'] ?? null))
            ->map(fn ($i) => ['description' => trim($i['description']), 'quantity' => $i['quantity'] ?? 1, 'unit_price' => Money::parse($i['unit_price'] ?? 0)])->values()->all();
        $request->merge(['items' => $items, 'discount' => Money::parse($request->input('discount', 0))]);

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $this->tenant()->id)->whereNull('deleted_at')],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.description' => ['required', 'string', 'max:190'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['items' => 'item invoice']);

        try {
            $invoice = $service->createManual($this->tenant(), (int) $data['customer_id'], $data['items'], $data['issue_date'], $data['due_date'], $data['discount'] ?? '0', $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return redirect()->route('app.invoices.show', $invoice)->with('success', 'Invoice dibuat.');
    }

    public function show(Invoice $invoice)
    {
        return view('isp.invoices.show', ['invoice' => $invoice->load('customer', 'items', 'allocations.payment', 'service.package'), 'tenant' => $this->tenant()->load('profile')]);
    }

    public function print(Invoice $invoice)
    {
        return view('isp.invoices.print', ['invoice' => $invoice->load('customer', 'items'), 'tenant' => $this->tenant()->load('profile')]);
    }

    public function void(Request $request, Invoice $invoice, InvoiceService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        try {
            $service->void($invoice, $data['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Invoice dibatalkan (void). Data tetap tersimpan untuk audit.');
    }
}
