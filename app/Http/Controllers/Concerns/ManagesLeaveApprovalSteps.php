<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\LeaveApprovalStep;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

trait ManagesLeaveApprovalSteps
{
    protected function canManageLeaveApprovalStepsForCompany(Company $company): bool
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('settings.access');
    }

    protected function abortUnlessCanManageLeaveApprovalSteps(Company $company): void
    {
        abort_unless($this->canManageLeaveApprovalStepsForCompany($company), 403);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, description: string|null}>
     */
    protected function accessibleTeamsForLeaveApprovalSteps()
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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapLeaveApprovalStepsForUi(Company $company): array
    {
        return LeaveApprovalStep::query()
            ->where('company_id', $company->id)
            ->with('team')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveApprovalStep $step) => [
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

    protected function userCanUseTeamForLeaveApprovalStep(int $teamId): bool
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

    protected function abortUnlessLeaveStepBelongsToCompany(LeaveApprovalStep $step, Company $company): void
    {
        abort_unless((int) $step->company_id === (int) $company->id, 404);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Company>
     */
    protected function manageableCompaniesForLeaveApprovalSteps()
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
