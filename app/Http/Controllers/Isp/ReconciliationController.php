<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationItem;
use App\Services\AuditLogger;
use App\Services\ReconciliationService;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class ReconciliationController extends Controller
{
    public function index()
    {
        return view('isp.reconciliation.index', [
            'batches' => ReconciliationBatch::withCount(['items as matched_count' => fn ($q) => $q->where('status', 'matched'), 'items as unmatched_count' => fn ($q) => $q->where('status', 'unmatched')])->latest()->paginate(15),
            'pendingTransfers' => Payment::where('status', 'pending')->where('method', 'transfer')->count(),
        ]);
    }

    public function template()
    {
        return response()->download(Spreadsheet::writeXlsx([
            ['tanggal', 'keterangan', 'jumlah', 'referensi'],
            ['2026-01-05', 'TRSF E-BANKING CR PLG-00001', '150000', 'FT2601050001'],
        ], 'Mutasi'), 'template-mutasi-bank.xlsx')->deleteFileAfterSend();
    }

    public function store(Request $request, ReconciliationService $service)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']]);
        try {
            $batch = $service->upload($this->tenant(), $request->file('file'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        } catch (Throwable $e) {
            return back()->with('error', 'File mutasi tidak dapat dibaca: '.mb_substr($e->getMessage(), 0, 150));
        }

        return redirect()->route('app.reconciliation.show', $batch)->with('success', 'Mutasi dibaca & dicocokkan otomatis. Tangani baris yang belum cocok.');
    }

    public function show(Request $request, ReconciliationBatch $batch)
    {
        $items = $batch->items()->with('payment.customer')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->get();
        $used = ReconciliationItem::whereNotNull('payment_id')->pluck('payment_id');

        return view('isp.reconciliation.show', [
            'batch' => $batch, 'items' => $items,
            'candidates' => Payment::with('customer')->where('method', 'transfer')->whereIn('status', ['pending', 'verified'])->whereNotIn('id', $used)
                ->whereIn('amount', $items->where('status', 'unmatched')->pluck('amount')->map(fn ($a) => (string) $a)->unique())->get()->groupBy(fn ($p) => (string) $p->amount),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_code']),
        ]);
    }

    public function resolve(Request $request, ReconciliationBatch $batch, ReconciliationItem $item, ReconciliationService $service)
    {
        abort_unless($item->reconciliation_batch_id === $batch->id && $batch->status === 'open', 404);
        $tenantId = $this->tenant()->id;
        $data = $request->validate([
            'action' => ['required', 'in:match,create,ignore,unmatch'],
            'payment_id' => ['required_if:action,match', 'nullable', Rule::exists('payments', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['required_if:action,create', 'nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'note' => ['nullable', 'string', 'max:190'],
        ]);
        try {
            match ($data['action']) {
                'match' => $service->matchManual($item, Payment::findOrFail($data['payment_id'])),
                'create' => $service->createPayment($this->tenant(), $item, Customer::findOrFail($data['customer_id']), $request->user()->can('payments.verify')),
                'ignore' => $item->update(['status' => 'ignored', 'note' => $data['note'] ?? 'Bukan pembayaran pelanggan', 'resolved_by' => Auth::guard('web')->id()]),
                'unmatch' => $item->payment?->status === 'verified' && $item->match_type === 'created'
                    ? throw new InvalidArgumentException('Pembayaran sudah terverifikasi. Lakukan reversal dari halaman pembayaran.')
                    : $item->update(['status' => 'unmatched', 'payment_id' => null, 'match_type' => null]),
            };
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }
        AuditLogger::log('reconciliation.item_'.$data['action'], $batch, null, ['row' => $item->row_number, 'payment' => $data['payment_id'] ?? null]);

        return back()->with('success', 'Baris mutasi diperbarui.');
    }

    public function verify(ReconciliationBatch $batch, ReconciliationService $service)
    {
        abort_unless(request()->user()->can('payments.verify'), 403);
        $n = $service->verifyMatched($this->tenant(), $batch);

        return back()->with('success', "{$n} pembayaran pending yang cocok dengan mutasi telah diverifikasi.");
    }

    public function complete(ReconciliationBatch $batch)
    {
        $open = $batch->items()->where('status', 'unmatched')->count();
        if ($open) {
            return back()->with('error', "Masih ada {$open} baris belum cocok. Cocokkan, buat pembayaran, atau tandai diabaikan.");
        }
        $batch->update(['status' => 'completed', 'completed_by' => Auth::guard('web')->id(), 'completed_at' => now()]);
        AuditLogger::log('reconciliation.completed', $batch);

        return back()->with('success', 'Rekonsiliasi ditutup.');
    }
}
