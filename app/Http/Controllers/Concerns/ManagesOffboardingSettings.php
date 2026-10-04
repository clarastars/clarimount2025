<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\OffboardingChecklistItemTemplate;
use App\Models\OffboardingClearanceApprovalStep;
use App\Models\OffboardingItemApprovalStep;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

trait ManagesOffboardingSettings
{
    protected function canManageOffboardingSettingsForCompany(Company $company): bool
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('settings.access');
    }

    protected function abortUnlessCanManageOffboardingSettings(Company $company): void
    {
        abort_unless($this->canManageOffboardingSettingsForCompany($company), 403);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, description: string|null}>
     */
    protected function accessibleTeamsForOffboardingSettings()
    {
        $user = Auth::user();

        $query = Team::query()->orderBy('name');

        if (! $user->hasRole('super-admin') && ! $user->can('settings.access')) {
            $query->where(function ($inner) use ($user) {
                $inner->where('owner_id', $user->id)
                    ->orWhere('id', $user->team_id);
            });
        }

        return $query
            ->get(['id', 'name', 'description'])
            ->unique('id')
            ->values()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
            ]);
    }

    protected function userCanUseTeamForOffboarding(int $teamId): bool
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin') || $user->can('settings.access')) {
            return Team::query()->whereKey($teamId)->exists();
        }

        return Team::query()
            ->whereKey($teamId)
            ->where(function ($query) use ($user) {
                $query->where('owner_id', $user->id)
                    ->orWhere('id', $user->team_id);
            })
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapOffboardingTemplatesForUi(Company $company): array
    {
        return OffboardingChecklistItemTemplate::query()
            ->where('company_id', $company->id)
            ->withCount([
                'approvalSteps as active_steps_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OffboardingChecklistItemTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'sort_order' => $template->sort_order,
                'attachment_mode' => $template->attachment_mode,
                'is_active' => $template->is_active,
                'active_steps_count' => (int) $template->active_steps_count,
                'can_delete' => ! $template->instanceItems()
                    ->whereHas('offboardingCase', function ($q) {
                        $q->whereIn('status', ['in_progress', 'pending_clearance']);
                    })
                    ->exists(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapOffboardingItemStepsForUi(OffboardingChecklistItemTemplate $template): array
    {
        return OffboardingItemApprovalStep::query()
            ->where('template_id', $template->id)
            ->with('team')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OffboardingItemApprovalStep $step) => [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'team_name' => $step->team?->name,
                'is_active' => $step->is_active,
                'can_delete' => ! $step->hasBlockingWorkflowUsage(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapOffboardingClearanceStepsForUi(Company $company): array
    {
        return OffboardingClearanceApprovalStep::query()
            ->where('company_id', $company->id)
            ->with('team')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OffboardingClearanceApprovalStep $step) => [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'team_name' => $step->team?->name,
                'is_active' => $step->is_active,
                'can_delete' => ! $step->hasBlockingWorkflowUsage(),
            ])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Company>
     */
    protected function manageableCompaniesForOffboardingSettings()
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin') || $user->can('settings.access')) {
            return Company::query()
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_ar']);
        }

        return collect();
    }
}
