<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\FingerprintIclockAttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFingerprintIclockAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600; // 10 minutes (many employees × API call each)

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(FingerprintIclockAttendanceService $service): void
    {
        Log::channel('daily')->info('[FingerprintIclock] Starting scheduled sync for today');
        $service->syncToday();
        Log::channel('daily')->info('[FingerprintIclock] Scheduled sync completed');
    }
}
