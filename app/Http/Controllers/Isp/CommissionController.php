<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\CollectionAssignment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommissionService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Komisi kolektor/agen. Kolektor hanya melihat komisinya sendiri.
 */
class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $manage = $request->user()->can('commissions.manage');
        $userFilter = $manage ? $request->integer('user') : $request->user()->id;

        $entries = CommissionEntry::with(['payment.customer', 'user'])
            ->when($userFilter, fn ($q) => $q->where('user_id', $userFilter))
            ->when($request->query('status', 'pending'), fn ($q, $s) => $s === 'semua' ? $q : $q->where('status', $s))
            ->latest('id')->paginate(30)->withQueryString();

        $summary = CommissionEntry::selectRaw('user_id, status, sum(amount) as total, count(*) as n')
            ->when(! $manage, fn ($q) => $q->where('user_id', $request->user()->id))
            ->groupBy('user_id', 'status')->get()->groupBy('user_id');

        return view('isp.commissions.index', [
            'entries' => $entries, 'summary' => $summary, 'manage' => $manage, 'userFilter' => $userFilter,
            'collectors' => $this->collectors(),
            'rules' => $manage ? CommissionRule::with('user')->latest('effective_from')->get() : collect(),
            'settlements' => CommissionSettlement::with('user')->when(! $manage, fn ($q) => $q->where('user_id', $request->user()->id))->latest()->limit(10)->get(),
            'assignmentCount' => CollectionAssignment::selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id'),
        ]);
    }

    public function storeRule(Request $request)
    {
        if ($request->filled('value')) {
            $request->merge(['value' => Money::parse($request->input('value'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'user_id' => ['nullable', Rule::in($this->collectors()->pluck('id')->all())],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000'],
            'method' => ['required', 'in:any,cash,transfer'],
            'effective_from' => ['required', 'date'],
        ]);
        $rule = CommissionRule::create($data + ['is_active' => true]);
        AuditLogger::log('commission.rule_created', $rule, null, $data);

        return back()->with('success', 'Aturan komisi disimpan. Berlaku untuk pembayaran terverifikasi berikutnya.');
    }

    public function toggleRule(CommissionRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        AuditLogger::log('commission.rule_toggled', $rule, null, ['is_active' => $rule->is_active]);

        return back()->with('success', 'Aturan komisi diperbarui.');
    }

    public function assignments(Request $request)
    {
        return view('isp.commissions.assignments', [
            'collectors' => $this->collectors(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_code', 'address']),
            'map' => CollectionAssignment::pluck('user_id', 'customer_id'),
        ]);
    }

    public function saveAssignments(Request $request)
    {
        $ids = $this->collectors()->pluck('id')->all();
        $data = $request->validate(['assign' => ['array'], 'assign.*' => ['nullable', Rule::in($ids)]]);
        $valid = Customer::pluck('id')->flip();
        $n = 0;
        foreach ($data['assign'] ?? [] as $customerId => $userId) {
            if (! isset($valid[$customerId])) {
                continue;
            }
            if ($userId) {
                CollectionAssignment::updateOrCreate(['customer_id' => $customerId], ['user_id' => $userId]);
            } else {
                CollectionAssignment::where('customer_id', $customerId)->delete();
            }
            $n++;
        }
        AuditLogger::log('commission.assignments_updated', null, null, ['rows' => $n]);

        return back()->with('success', 'Penugasan penagihan disimpan.');
    }

    public function settle(Request $request, CommissionService $service)
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::in($this->collectors()->pluck('id')->all())],
            'entries' => ['required', 'array', 'min:1'], 'entries.*' => ['integer'],
            'reference' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $s = $service->settle($this->tenant(), User::findOrFail($data['user_id']), $data['entries'], $data['reference'] ?? null, $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', "Komisi {$s->number} dibayarkan: ".rupiah($s->total));
    }

    protected function collectors()
    {
        return User::forCurrentTenant()->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'collector'))->orderBy('name')->get(['id', 'name']);
    }
}
