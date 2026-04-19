<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BayzatFingerprintAttendancePushService;
use App\Services\FingerprintIclockAttendanceService;
use Carbon\Carbon;
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

    public function handle(
        FingerprintIclockAttendanceService $service,
        BayzatFingerprintAttendancePushService $bayzatFingerprintPush,
    ): void {
        $jobContext = [
            'job_class' => static::class,
            'queue' => $this->queue ?? 'default',
            'attempt' => $this->attempts(),
        ];

        Log::channel('daily')->info('[FingerprintIclock] Scheduled job started', $jobContext);

        $syncStarted = microtime(true);
        Log::channel('daily')->info('[FingerprintIclock] Starting iClock sync for today');
        $service->syncToday();
        $syncMs = (int) round((microtime(true) - $syncStarted) * 1000);
        Log::channel('daily')->info('[FingerprintIclock] iClock sync finished', [
            'duration_ms' => $syncMs,
        ]);

        $pushStarted = microtime(true);
        Log::channel('daily')->info('[FingerprintIclock] Starting Bayzat fingerprint push phase');
        try {
            $bayzatFingerprintPush->pushToday();
            Log::channel('daily')->info('[FingerprintIclock] Bayzat fingerprint push phase finished', [
                'duration_ms' => (int) round((microtime(true) - $pushStarted) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::channel('daily')->error('[FingerprintIclock] Bayzat fingerprint push phase failed', [
                'duration_ms' => (int) round((microtime(true) - $pushStarted) * 1000),
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        $today = Carbon::today('Asia/Riyadh')->format('Y-m-d');
        RebuildAttendancePresentationJob::dispatch($today, false);
        Log::channel('daily')->info('[FingerprintIclock] Queued attendance presentation rebuild', [
            'att_date' => $today,
        ]);

        Log::channel('daily')->info('[FingerprintIclock] Scheduled job completed', $jobContext);
    }
}
