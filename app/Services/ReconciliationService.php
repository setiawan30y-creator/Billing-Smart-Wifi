<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationItem;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\Spreadsheet;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Rekonsiliasi mutasi bank ↔ pembayaran. Pencocokan otomatis hanya bila nominal sama persis,
 * tanggal berdekatan, dan (bila ada) referensi/kode pelanggan cocok; sisanya ditangani manual.
 * @2026-Alifiandra
 */
class ReconciliationService
{
    public function __construct(protected PaymentService $payments) {}

    public function upload(Tenant $tenant, UploadedFile $file): ReconciliationBatch
    {
        $rows = Spreadsheet::read($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($v) => $v !== '')) > 0));
        if (count($rows) < 2) {
            throw new InvalidArgumentException('File mutasi kosong.');
        }
        $head = array_map(fn ($h) => strtolower(trim(preg_replace('/[^a-z]/i', '', $h))), array_shift($rows));
        $idx = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $head, true))->first(fn ($i) => $i !== false);
        $cDate = $idx(['tanggal', 'date', 'tgl', 'tanggaltransaksi']);
        $cDesc = $idx(['keterangan', 'deskripsi', 'description', 'uraian', 'remark']);
        $cAmt = $idx(['jumlah', 'nominal', 'amount', 'kredit', 'credit', 'mutasi']);
        $cRef = $idx(['referensi', 'reference', 'ref', 'noreferensi']);
        if ($cAmt === null || $cDate === null) {
            throw new InvalidArgumentException('Kolom wajib "tanggal" dan "jumlah" tidak ditemukan. Gunakan template mutasi.');
        }

        return DB::transaction(function () use ($tenant, $file, $rows, $cDate, $cDesc, $cAmt, $cRef) {
            $batch = ReconciliationBatch::create([
                'number' => SequenceService::format($tenant->id, 'REK', now($tenant->timezone)->format('Ym')),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
                'file_path' => $file->store('tenants/'.$tenant->id.'/reconciliation', config('almara.private_disk')),
                'status' => 'open', 'created_by' => Auth::guard('web')->id(),
            ]);
            $total = '0';
            $n = 0;
            foreach ($rows as $i => $r) {
                $amount = Money::parse($r[$cAmt] ?? '0');
                if (! Money::isPositive($amount)) {
                    continue; // hanya dana masuk
                }
                $raw = trim((string) ($r[$cDate] ?? ''));
                $date = Spreadsheet::excelDate($raw);
                if (! $date) {
                    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'd.m.Y'] as $fmt) {
                        try {
                            $date = Carbon::createFromFormat('!'.$fmt, $raw)->toDateString();
                            break;
                        } catch (Throwable) {
                        }
                    }
                }
                $batch->items()->create([
                    'row_number' => $i + 2, 'txn_date' => $date, 'amount' => $amount,
                    'description' => $cDesc !== null ? mb_substr((string) ($r[$cDesc] ?? ''), 0, 500) : null,
                    'reference' => $cRef !== null ? mb_substr((string) ($r[$cRef] ?? ''), 0, 190) : null,
                    'status' => 'unmatched',
                ]);
                $total = Money::add($total, $amount);
                $n++;
            }
            $batch->update(['total_items' => $n, 'total_amount' => $total]);
            $this->autoMatch($batch);
            AuditLogger::log('reconciliation.uploaded', $batch, null, ['items' => $n, 'total' => $total]);

            return $batch;
        });
    }

    public function autoMatch(ReconciliationBatch $batch): int
    {
        $matched = 0;
        $used = ReconciliationItem::whereNotNull('payment_id')->pluck('payment_id')->all();
        foreach ($batch->items()->where('status', 'unmatched')->get() as $item) {
            $q = Payment::with('customer')->whereIn('status', ['pending', 'verified'])->where('method', 'transfer')
                ->where('amount', $item->amount)->whereNotIn('id', $used);
            if ($item->txn_date) {
                $q->whereBetween('paid_at', [$item->txn_date->copy()->subDays(3)->startOfDay(), $item->txn_date->copy()->addDays(3)->endOfDay()]);
            }
            $cands = $q->get();
            $text = strtoupper($item->description.' '.$item->reference);
            $strong = $cands->filter(fn ($p) => ($p->reference && str_contains($text, strtoupper($p->reference)))
                || ($p->customer && str_contains($text, strtoupper($p->customer->customer_code))));
            $pick = $strong->count() === 1 ? $strong->first() : ($cands->count() === 1 && $strong->isEmpty() ? $cands->first() : null);
            if ($pick) {
                $item->update(['status' => 'matched', 'payment_id' => $pick->id, 'match_type' => 'auto', 'note' => $strong->count() ? 'nominal + referensi cocok' : 'nominal & tanggal cocok (satu-satunya kandidat)']);
                $used[] = $pick->id;
                $matched++;
            }
        }

        return $matched;
    }

    public function matchManual(ReconciliationItem $item, Payment $payment): void
    {
        if (ReconciliationItem::where('payment_id', $payment->id)->where('id', '!=', $item->id)->exists()) {
            throw new InvalidArgumentException('Pembayaran ini sudah dicocokkan dengan mutasi lain.');
        }
        if (Money::cmp($payment->amount, $item->amount) !== 0) {
            throw new InvalidArgumentException('Nominal pembayaran ('.rupiah($payment->amount).') berbeda dengan mutasi ('.rupiah($item->amount).').');
        }
        $item->update(['status' => 'matched', 'payment_id' => $payment->id, 'match_type' => 'manual', 'resolved_by' => Auth::guard('web')->id()]);
    }

    /** Verifikasi pembayaran pending yang sudah dicocokkan dengan dana masuk di rekening. */
    public function verifyMatched(Tenant $tenant, ReconciliationBatch $batch): int
    {
        $n = 0;
        foreach ($batch->items()->where('status', 'matched')->with('payment')->get() as $item) {
            if ($item->payment?->status === 'pending') {
                $this->payments->verify($tenant, $item->payment);
                $n++;
            }
        }
        AuditLogger::log('reconciliation.verified', $batch, null, ['verified' => $n]);

        return $n;
    }

    /** Dana masuk tanpa catatan pembayaran → buat pembayaran untuk pelanggan (pending, lalu verifikasi). */
    public function createPayment(Tenant $tenant, ReconciliationItem $item, Customer $customer, bool $verify): Payment
    {
        return DB::transaction(function () use ($tenant, $item, $customer, $verify) {
            $p = $this->payments->record($tenant, [
                'customer_id' => $customer->id, 'amount' => (string) $item->amount, 'method' => 'transfer',
                'reference' => $item->reference ?: 'REK-'.$item->id, 'paid_at' => $item->txn_date ?? now(),
                'notes' => 'Dari rekonsiliasi mutasi: '.mb_substr((string) $item->description, 0, 150),
            ]);
            $item->update(['status' => 'matched', 'payment_id' => $p->id, 'match_type' => 'created', 'resolved_by' => Auth::guard('web')->id()]);
            if ($verify) {
                $this->payments->verify($tenant, $p);
            }

            return $p;
        });
    }
}
