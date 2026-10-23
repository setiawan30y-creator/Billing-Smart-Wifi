<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Audit keuangan: penyesuaian (maker-checker), penutupan periode, dan ringkasan per periode.
 */
class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $tz = $this->tenant()->timezone;
        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period')) ? $request->query('period') : now($tz)->format('Y-m');
        [$y, $m] = explode('-', $period);

        $invoices = Invoice::where('status', '!=', 'void')->whereYear('issue_date', $y)->whereMonth('issue_date', $m)->get(['id', 'total', 'amount_paid', 'adjusted_amount', 'status']);
        $verified = Payment::where('status', 'verified')->whereYear('paid_at', $y)->whereMonth('paid_at', $m)->get(['amount', 'method']);

        return view('isp.finance.index', [
            'period' => $period,
            'summary' => [
                'invoiced' => Money::add(...$invoices->pluck('total')->all()),
                'invoice_count' => $invoices->count(),
                'collected' => Money::add(...$verified->pluck('amount')->all()),
                'by_method' => $verified->groupBy('method')->map(fn ($g) => Money::add(...$g->pluck('amount')->all())),
                'adjusted' => Money::add(...FinancialAdjustment::where('status', 'approved')->whereYear('approved_at', $y)->whereMonth('approved_at', $m)->pluck('amount')->all()),
                'outstanding' => Money::add(...$invoices->whereIn('status', ['unpaid', 'partial'])->map(fn ($i) => $i->balance())->all()),
            ],
            'adjustments' => FinancialAdjustment::with(['customer', 'invoice', 'creator', 'approver'])->latest()->paginate(20),
            'periods' => AccountingPeriod::with('closer')->orderByDesc('period')->limit(12)->get(),
            'closed' => AccountingPeriod::isClosed($period.'-01'),
        ]);
    }

    public function storeAdjustment(Request $request, FinanceService $service)
    {
        $request->merge(['amount' => Money::parse($request->input('amount'))]);
        $data = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('tenant_id', $this->tenant()->id)],
            'type' => ['required', Rule::in(array_keys(FinancialAdjustment::TYPES))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:250'],
        ]);
        try {
            $adj = $service->requestAdjustment($this->tenant(), Invoice::findOrFail($data['invoice_id']), $data['type'], $data['amount'], $data['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', "Penyesuaian {$adj->number} diajukan dan menunggu persetujuan.");
    }

    public function review(Request $request, FinancialAdjustment $adjustment, FinanceService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:190']]);
        try {
            $service->review($adjustment, $data['decision'] === 'approve', $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', $data['decision'] === 'approve' ? 'Penyesuaian disetujui; sisa tagihan dihitung ulang.' : 'Penyesuaian ditolak.');
    }

    public function closePeriod(Request $request, FinanceService $service)
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'note' => ['nullable', 'string', 'max:190']]);
        try {
            $service->closePeriod($this->tenant(), $data['period'], $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', "Periode {$data['period']} ditutup. Transaksi bertanggal di periode ini tidak dapat diubah.");
    }

    public function reopenPeriod(Request $request, AccountingPeriod $period, FinanceService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:190']]);
        $service->reopenPeriod($period, $data['reason']);

        return back()->with('success', "Periode {$period->period} dibuka kembali (tercatat di audit).");
    }
}
