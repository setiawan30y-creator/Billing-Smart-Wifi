<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\UsageMeter;
use App\Support\Money;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $tenant = $this->tenant();
        $tz = $tenant->timezone;
        $monthStart = now($tz)->startOfMonth();

        $open = Invoice::whereIn('status', ['unpaid', 'partial'])->get(['id', 'total', 'amount_paid', 'adjusted_amount', 'due_date']);
        $overdue = $open->filter(fn ($i) => $i->due_date->lt(now($tz)->startOfDay()));

        return view('isp.dashboard', [
            'tenant' => $tenant,
            'quota' => UsageMeter::quotaStatus($tenant),
            'customers' => Customer::count(),
            'receivable' => Money::add(...$open->map(fn ($i) => $i->balance())->all()),
            'overdueCount' => $overdue->count(),
            'overdueAmount' => Money::add(...$overdue->map(fn ($i) => $i->balance())->all()),
            'collectedMonth' => Money::add(...Payment::where('status', 'verified')->where('verified_at', '>=', $monthStart)->pluck('amount')->all()),
            'pendingPayments' => Payment::where('status', 'pending')->count(),
            'recentInvoices' => Invoice::with('customer')->latest()->limit(6)->get(),
            'recentPayments' => Payment::with('customer')->latest()->limit(6)->get(),
            'subscription' => $tenant->currentSubscription()->with('plan')->first(),
        ]);
    }
}
