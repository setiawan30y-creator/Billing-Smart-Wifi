<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupJob;
use App\Models\MonitoringCollectionRun;
use App\Models\Tenant;
use App\Services\UsageMeter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kesehatan platform SaaS (berbeda dari monitoring jaringan tenant):
 * antrean, scheduler, collector, integrasi, backup, dan kapasitas tenant terhadap paket.
 */
class HealthController extends Controller
{
    public function __invoke()
    {
        $heartbeat = Cache::get('almara:scheduler-heartbeat');
        $jobsPending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $oldestJob = Schema::hasTable('jobs') ? DB::table('jobs')->min('created_at') : null;

        $tenants = Tenant::with(['currentSubscription.plan'])->whereIn('status', ['trial', 'active', 'suspended'])->orderBy('name')->get()->map(function ($t) {
            $q = UsageMeter::quotaStatus($t);
            $lastBackup = BackupJob::where('tenant_id', $t->id)->where('status', 'completed')->max('finished_at');
            $lastRun = MonitoringCollectionRun::where('tenant_id', $t->id)->latest('id')->first();

            return ['tenant' => $t, 'quota' => $q, 'last_backup' => $lastBackup, 'last_run' => $lastRun];
        });

        $since = now()->subDay();

        return view('admin.health', [
            'queue' => ['driver' => config('queue.default'), 'pending' => $jobsPending, 'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0, 'oldest' => $oldestJob ? now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($oldestJob)) : null],
            'heartbeat' => $heartbeat ? \Carbon\Carbon::parse($heartbeat) : null,
            'integrations' => [
                'wa_failed' => DB::table('notification_logs')->where('status', 'failed')->where('updated_at', '>=', $since)->count(),
                'wa_queued_old' => DB::table('notification_logs')->where('status', 'queued')->where('created_at', '<', now()->subMinutes(30))->count(),
                'webhook_unmatched' => DB::table('payment_webhook_events')->where('status', 'unmatched')->where('created_at', '>=', $since)->count(),
                'collector_errors' => DB::table('monitoring_collection_runs')->where('started_at', '>=', $since)->sum('collector_errors'),
                'collector_failed_runs' => DB::table('monitoring_collection_runs')->where('started_at', '>=', $since)->whereNotNull('error')->count(),
            ],
            'backups' => ['failed' => BackupJob::where('status', 'failed')->where('started_at', '>=', now()->subWeek())->count()],
            'samples' => DB::table('monitoring_samples')->count(),
            'tenants' => $tenants,
            'db' => DB::connection()->getDriverName(),
        ]);
    }
}
