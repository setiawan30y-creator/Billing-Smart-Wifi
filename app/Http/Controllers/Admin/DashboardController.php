<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasInvoice;
use App\Models\Tenant;
use App\Models\TenantProvisioningJob;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $byStatus = Tenant::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $openSaas = SaasInvoice::where('is_simulation', false)->whereIn('status', ['unpaid', 'partial'])->get(['total', 'amount_paid']);

        return view('admin.dashboard', [
            'byStatus' => $byStatus,
            'totalTenants' => $byStatus->sum(),
            'failedJobs' => TenantProvisioningJob::where('status', 'failed')->with('tenant')->latest()->limit(5)->get(),
            'saasReceivable' => Money::add(...$openSaas->map(fn ($i) => Money::sub($i->total, $i->amount_paid))->all()),
            'recentTenants' => Tenant::with('currentSubscription.plan')->latest()->limit(8)->get(),
            'totalCustomers' => DB::table('customers')->whereNull('deleted_at')->count(),
            'totalRouters' => DB::table('network_devices')->where('type', 'mikrotik')->whereNull('deleted_at')->count(),
        ]);
    }
}
