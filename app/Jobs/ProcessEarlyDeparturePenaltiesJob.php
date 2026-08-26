<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AttendancePenaltyService;
use App\Services\AttendancePresentationRebuildService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEarlyDeparturePenaltiesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $uniqueFor = 1800;

    public function __construct(
        public ?string $attendanceDate = null,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'process-early-departure-penalties-'.($this->attendanceDate ?? 'today');
    }

    public function handle(
        AttendancePresentationRebuildService $presentationService,
        AttendancePenaltyService $penaltyService
    ): void {
        $date = $this->attendanceDate ?? Carbon::today('Asia/Riyadh')->format('Y-m-d');

        // Refresh day rows first so the job evaluates the latest last-punch value.
        $presentationService->rebuildDateForAllCompanies($date);
        $penaltyService->processEarlyDeparturePenaltiesForDate($date);
    }
}
