<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RebuildAttendancePresentationJob;
use App\Services\AttendancePresentationRebuildService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildAttendancePresentationsCommand extends Command
{
    protected $signature = 'attendance:rebuild-presentations
                            {--date= : Single day Y-m-d (Asia/Riyadh); default today when not using --month}
                            {--month : Rebuild from start of current month through today}
                            {--company= : Only this company id (runs in-process, not queued)}
                            {--sync : Run in this PHP process for all companies (no queue)}';

    protected $description = 'Rebuild cached attendance index rows (status, late minutes, punches) and absence penalties';

    public function handle(AttendancePresentationRebuildService $service): int
    {
        if ($this->option('month') && $this->option('date')) {
            $this->error('Use either --month or --date, not both.');

            return 1;
        }

        $companyId = $this->option('company') !== null && $this->option('company') !== ''
            ? (int) $this->option('company')
            : null;

        if ($companyId !== null) {
            if ($this->option('month')) {
                $now = Carbon::now('Asia/Riyadh');
                $start = $now->copy()->startOfMonth()->format('Y-m-d');
                $end = Carbon::today('Asia/Riyadh')->format('Y-m-d');
                $this->info("Rebuilding presentations for company {$companyId} from {$start} to {$end}...");
                $service->rebuildCompanyDateRange($companyId, $start, $end);
            } else {
                $date = $this->option('date')
                    ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                    : Carbon::today('Asia/Riyadh')->format('Y-m-d');
                $this->info("Rebuilding presentations for company {$companyId} on {$date}...");
                $service->rebuildCompanyDateRange($companyId, $date, $date);
            }
            $this->info('Done.');

            return 0;
        }

        if ($this->option('sync')) {
            if ($this->option('month')) {
                $this->info('Rebuilding presentations for current month (all companies)...');
                $service->rebuildCurrentMonthForAllCompanies();
            } else {
                $date = $this->option('date')
                    ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                    : Carbon::today('Asia/Riyadh')->format('Y-m-d');
                $this->info("Rebuilding presentations for {$date} (all companies)...");
                $service->rebuildDateForAllCompanies($date);
            }
            $this->info('Done.');

            return 0;
        }

        if ($this->option('month')) {
            RebuildAttendancePresentationJob::dispatch(null, true);
            $this->info('Queued: rebuild current month for all companies.');
        } else {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'), 'Asia/Riyadh')->format('Y-m-d')
                : Carbon::today('Asia/Riyadh')->format('Y-m-d');
            RebuildAttendancePresentationJob::dispatch($date, false);
            $this->info("Queued: rebuild presentations for {$date}.");
        }

        return 0;
    }
}
