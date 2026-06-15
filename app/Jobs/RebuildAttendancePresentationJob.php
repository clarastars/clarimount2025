<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AttendancePresentationRebuildService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildAttendancePresentationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    /** Slightly longer than job timeout so overlapping rebuilds cannot run in parallel. */
    public int $uniqueFor = 1260;

    /**
     * @param  string|null  $dateYmd  When set, rebuild this day for all companies (Asia/Riyadh).
     * @param  bool  $fullCurrentMonth  When true, rebuild from start of current month through today for all companies.
     */
    public function __construct(
        public ?string $dateYmd = null,
        public bool $fullCurrentMonth = false,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'rebuild-attendance-presentation';
    }

    public function handle(AttendancePresentationRebuildService $service): void
    {
        if ($this->fullCurrentMonth) {
            $service->rebuildCurrentMonthForAllCompanies();

            return;
        }

        $date = $this->dateYmd ?? Carbon::today('Asia/Riyadh')->format('Y-m-d');
        $service->rebuildDateForAllCompanies($date);
    }
}
