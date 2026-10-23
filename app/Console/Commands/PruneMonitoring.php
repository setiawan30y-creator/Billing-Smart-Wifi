<?php

namespace App\Console\Commands;

use App\Models\MonitoringCollectionRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMonitoring extends Command
{
    protected $signature = 'monitoring:prune {--days=30}';

    protected $description = 'Hapus sampel monitoring lebih lama dari retensi (default 30 hari)';

    public function handle(): int
    {
        $cut = now()->subDays((int) $this->option('days'));
        $n = DB::table('monitoring_samples')->where('measured_at', '<', $cut)->delete();
        MonitoringCollectionRun::where('started_at', '<', $cut)->delete();
        $this->line("{$n} sampel dihapus.");

        return self::SUCCESS;
    }
}
