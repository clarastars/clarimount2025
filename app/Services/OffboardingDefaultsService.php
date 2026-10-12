<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\OffboardingChecklistItemTemplate;
use App\Models\OffboardingClearanceApprovalStep;
use App\Models\OffboardingDefaultChecklistItem;
use App\Models\OffboardingDefaultClearanceApprovalStep;
use App\Models\OffboardingDefaultItemApprovalStep;
use App\Models\OffboardingItemApprovalStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OffboardingDefaultsService
{
    /**
     * @return Collection<int, OffboardingDefaultChecklistItem>
     */
    public function activeChecklistItems(): Collection
    {
        return OffboardingDefaultChecklistItem::query()
            ->with(['approvalSteps' => function ($query): void {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, OffboardingDefaultClearanceApprovalStep>
     */
    public function activeClearanceSteps(): Collection
    {
        return OffboardingDefaultClearanceApprovalStep::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function hasAnyDefaultsConfigured(): bool
    {
        return $this->activeChecklistItems()->isNotEmpty()
            || $this->activeClearanceSteps()->isNotEmpty();
    }

    public function seedChecklistForCompany(Company $company): void
    {
        if (OffboardingChecklistItemTemplate::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $defaults = $this->activeChecklistItems();

        if ($defaults->isEmpty()) {
            return;
        }

        $this->createChecklistFromDefaults($company, $defaults);
    }

    public function seedClearanceForCompany(Company $company): void
    {
        if (OffboardingClearanceApprovalStep::query()->where('company_id', $company->id)->exists()) {
            return;
        }

        $defaults = $this->activeClearanceSteps();

        if ($defaults->isEmpty()) {
            return;
        }

        $this->createClearanceFromDefaults($company, $defaults);
    }

    /**
     * @return array{applied: bool, reason: string|null}
     */
    public function applyDefaultsToCompany(Company $company): array
    {
        $checklistDefaults = $this->activeChecklistItems();
        $clearanceDefaults = $this->activeClearanceSteps();

        if ($checklistDefaults->isEmpty() && $clearanceDefaults->isEmpty()) {
            return [
                'applied' => false,
                'reason' => 'empty_defaults',
            ];
        }

        if ($this->companyHasBlockingUsage($company)) {
            return [
                'applied' => false,
                'reason' => 'blocking_usage',
            ];
        }

        DB::transaction(function () use ($company, $checklistDefaults, $clearanceDefaults): void {
            if ($checklistDefaults->isNotEmpty()) {
                OffboardingChecklistItemTemplate::query()
                    ->where('company_id', $company->id)
                    ->get()
                    ->each(fn (OffboardingChecklistItemTemplate $template) => $template->delete());

                $this->createChecklistFromDefaults($company, $checklistDefaults);
            }

            if ($clearanceDefaults->isNotEmpty()) {
                OffboardingClearanceApprovalStep::query()
                    ->where('company_id', $company->id)
                    ->get()
                    ->each(fn (OffboardingClearanceApprovalStep $step) => $step->delete());

                $this->createClearanceFromDefaults($company, $clearanceDefaults);
            }
        });

        return [
            'applied' => true,
            'reason' => null,
        ];
    }

    /**
     * @return array{applied: int, skipped: int, skipped_reasons: array<string, int>}
     */
    public function applyDefaultsToAllCompanies(): array
    {
        $applied = 0;
        $skipped = 0;
        $skippedReasons = [
            'empty_defaults' => 0,
            'blocking_usage' => 0,
        ];

        if (! $this->hasAnyDefaultsConfigured()) {
            return [
                'applied' => 0,
                'skipped' => 0,
                'skipped_reasons' => ['empty_defaults' => 1],
            ];
        }

        $companies = Company::query()->orderBy('id')->get(['id']);

        foreach ($companies as $company) {
            $result = $this->applyDefaultsToCompany($company);

            if ($result['applied']) {
                $applied++;
                continue;
            }

            $skipped++;
            $reason = $result['reason'] ?? 'unknown';
            $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
        }

        return [
            'applied' => $applied,
            'skipped' => $skipped,
            'skipped_reasons' => $skippedReasons,
        ];
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderDefaultChecklistItems(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                OffboardingDefaultChecklistItem::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderDefaultItemSteps(int $defaultChecklistItemId, array $orderedIds): void
    {
        DB::transaction(function () use ($defaultChecklistItemId, $orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                OffboardingDefaultItemApprovalStep::query()
                    ->where('default_checklist_item_id', $defaultChecklistItemId)
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderDefaultClearanceSteps(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                OffboardingDefaultClearanceApprovalStep::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    private function companyHasBlockingUsage(Company $company): bool
    {
        $templates = OffboardingChecklistItemTemplate::query()
            ->where('company_id', $company->id)
            ->with('approvalSteps')
            ->get();

        foreach ($templates as $template) {
            $inUse = $template->instanceItems()
                ->whereHas('offboardingCase', function ($q): void {
                    $q->whereIn('status', ['in_progress', 'pending_clearance']);
                })
                ->exists();

            if ($inUse) {
                return true;
            }

            foreach ($template->approvalSteps as $step) {
                if ($step->hasBlockingWorkflowUsage()) {
                    return true;
                }
            }
        }

        $clearanceSteps = OffboardingClearanceApprovalStep::query()
            ->where('company_id', $company->id)
            ->get();

        foreach ($clearanceSteps as $step) {
            if ($step->hasBlockingWorkflowUsage()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, OffboardingDefaultChecklistItem>  $defaults
     */
    private function createChecklistFromDefaults(Company $company, Collection $defaults): void
    {
        foreach ($defaults as $index => $defaultItem) {
            $template = OffboardingChecklistItemTemplate::query()->create([
                'company_id' => $company->id,
                'title' => $defaultItem->title,
                'attachment_mode' => $defaultItem->attachment_mode,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);

            $steps = ($defaultItem->relationLoaded('approvalSteps')
                ? $defaultItem->approvalSteps
                : $defaultItem->approvalSteps()->orderBy('sort_order')->orderBy('id')->get()
            )->where('is_active', true)->values();

            foreach ($steps as $stepIndex => $defaultStep) {
                OffboardingItemApprovalStep::query()->create([
                    'company_id' => $company->id,
                    'template_id' => $template->id,
                    'title' => $defaultStep->title,
                    'team_id' => $defaultStep->team_id,
                    'sort_order' => $stepIndex + 1,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, OffboardingDefaultClearanceApprovalStep>  $defaults
     */
    private function createClearanceFromDefaults(Company $company, Collection $defaults): void
    {
        foreach ($defaults as $index => $defaultStep) {
            OffboardingClearanceApprovalStep::query()->create([
                'company_id' => $company->id,
                'title' => $defaultStep->title,
                'sort_order' => $index + 1,
                'team_id' => $defaultStep->team_id,
                'is_active' => true,
            ]);
        }
    }
}
