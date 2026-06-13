<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LeaveAccrualService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AccrueMonthlyLeaveBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public ?string $period = null,
        public bool $force = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(LeaveAccrualService $service): void
    {
        $period = $this->period ?? $service->resolveCurrentAccrualPeriod();
        $result = $service->accrueForPeriod($period, $this->force);

        Log::info('Monthly leave accrual completed.', [
            'period' => $period,
            'processed' => $result['processed'],
            'skipped' => $result['skipped'],
            'accrued' => $result['accrued'],
            'force' => $this->force,
        ]);
    }
}
