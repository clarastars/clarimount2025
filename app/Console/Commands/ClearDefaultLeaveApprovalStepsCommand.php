<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\LeaveApprovalStep;
use App\Services\LeaveApprovalService;
use Illuminate\Console\Command;

class ClearDefaultLeaveApprovalStepsCommand extends Command
{
    protected $signature = 'leaves:clear-default-approval-steps
                            {--company= : Company id (omit to process all companies)}
                            {--all : Delete every approval step, not only the auto-seeded defaults}
                            {--force : Delete even when linked to pending leave requests}';

    protected $description = 'Remove auto-seeded leave approval steps (HR review, direct manager, management)';

    public function handle(LeaveApprovalService $service): int
    {
        $companyId = $this->option('company');
        $deleteAll = (bool) $this->option('all');
        $force = (bool) $this->option('force');

        $companies = Company::query()
            ->when($companyId !== null && $companyId !== '', fn ($q) => $q->where('id', (int) $companyId))
            ->orderBy('id')
            ->get();

        if ($companies->isEmpty()) {
            $this->error('No companies found.');

            return self::FAILURE;
        }

        $totalDeleted = 0;
        $totalSkipped = 0;

        foreach ($companies as $company) {
            $this->line("Company #{$company->id} — {$company->name_en}");

            if ($deleteAll) {
                $result = $this->deleteAllStepsForCompany((int) $company->id, $force, $service);
            } else {
                $result = $service->deleteDefaultStepsForCompany((int) $company->id, $force);
            }

            $totalDeleted += $result['deleted'];
            $totalSkipped += $result['skipped'];

            $this->line("  deleted: {$result['deleted']}, skipped: {$result['skipped']}");

            if ($result['skipped_titles'] !== []) {
                $this->warn('  skipped (pending requests): '.implode(', ', $result['skipped_titles']));
            }
        }

        $this->info("Done. Deleted {$totalDeleted} step(s), skipped {$totalSkipped}.");

        return self::SUCCESS;
    }

    /**
     * @return array{deleted: int, skipped: int, skipped_titles: list<string>}
     */
    private function deleteAllStepsForCompany(int $companyId, bool $force, LeaveApprovalService $service): array
    {
        $deleted = 0;
        $skipped = 0;
        $skippedTitles = [];

        $steps = LeaveApprovalStep::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($steps as $step) {
            if (! $force && $step->hasBlockingWorkflowUsage()) {
                $skipped++;
                $skippedTitles[] = $step->title;

                continue;
            }

            $step->delete();
            $deleted++;
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
            'skipped_titles' => $skippedTitles,
        ];
    }
}
