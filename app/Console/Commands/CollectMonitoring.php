<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\IncidentService;
use App\Services\MonitoringService;
use Illuminate\Console\Command;

class CollectMonitoring extends Command
{
    protected $signature = 'monitoring:collect {--tenant=} {--force : abaikan interval}';

    protected $description = 'Ambil sampel monitoring (read-only) untuk target yang jatuh tempo & deteksi gangguan massal';

    public function handle(MonitoringService $monitoring, IncidentService $incidents): int
    {
        $tenants = Tenant::whereIn('status', ['trial', 'active'])->when($this->option('tenant'), fn ($q, $c) => $q->where('code', $c))->get();
        foreach ($tenants as $tenant) {
            $run = $monitoring->collect($tenant, (bool) $this->option('force'));
            $new = $incidents->detect($tenant);
            $this->line("{$tenant->code}: {$run->targets} target · up {$run->up} · down {$run->down} · collector error {$run->collector_errors}".($new ? " · {$new} insiden baru" : ''));
        }

        return self::SUCCESS;
    }
}
