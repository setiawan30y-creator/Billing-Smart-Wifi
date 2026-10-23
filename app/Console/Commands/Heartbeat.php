<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Heartbeat extends Command
{
    protected $signature = 'almara:heartbeat';

    protected $description = 'Tanda scheduler berjalan (dipantau di halaman Kesehatan Platform)';

    public function handle(): int
    {
        Cache::forever('almara:scheduler-heartbeat', now()->toIso8601String());

        return self::SUCCESS;
    }
}
