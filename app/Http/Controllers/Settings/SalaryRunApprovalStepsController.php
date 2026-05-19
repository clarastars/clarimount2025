<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SalaryRunApprovalStep;
use App\Models\Team;
use App\Services\SalaryRunApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalaryRunApprovalStepsController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $steps = SalaryRunApprovalStep::query()
            ->with('team')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SalaryRunApprovalStep $step) => [
                'id' => $step->id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'team_name' => $step->team?->name,
                'is_active' => $step->is_active,
                'has_approvals' => $step->stepApprovals()->exists(),
            ]);

        $teams = $this->accessibleTeamsForUser($user);

        return Inertia::render('settings/SalaryRunApprovals', [
            'steps' => $steps,
            'teams' => $teams,
            'status' => session('status'),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, description: string|null}>
     */
    private function accessibleTeamsForUser($user)
    {
        return Team::query()
            ->where('owner_id', $user->id)
            ->orWhere('id', $user->team_id)
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->unique('id')
            ->values()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
            ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        abort_unless($this->userCanUseTeam($user, (int) $validated['team_id']), 403);

        $maxOrder = (int) SalaryRunApprovalStep::query()->max('sort_order');

        SalaryRunApprovalStep::query()->create([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('status', __('messages.settings.salary_run_approvals_saved'));
    }

    public function update(Request $request, SalaryRunApprovalStep $salaryRunApprovalStep): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless($this->userCanUseTeam($user, (int) $validated['team_id']), 403);

        $salaryRunApprovalStep->update([
            'title' => $validated['title'],
            'team_id' => $validated['team_id'],
            'is_active' => $validated['is_active'] ?? $salaryRunApprovalStep->is_active,
        ]);

        return back()->with('status', __('messages.settings.salary_run_approvals_saved'));
    }

    public function destroy(SalaryRunApprovalStep $salaryRunApprovalStep): RedirectResponse
    {
        if ($salaryRunApprovalStep->stepApprovals()->exists()) {
            return back()->withErrors([
                'step' => __('messages.settings.salary_run_approvals_cannot_delete'),
            ]);
        }

        $salaryRunApprovalStep->delete();

        $remaining = SalaryRunApprovalStep::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($remaining !== []) {
            app(SalaryRunApprovalService::class)->reorderSteps($remaining);
        }

        return back()->with('status', __('messages.settings.salary_run_approvals_deleted'));
    }

    public function reorder(Request $request, SalaryRunApprovalService $approvalService): RedirectResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:salary_run_approval_steps,id'],
        ]);

        $approvalService->reorderSteps($validated['ordered_ids']);

        return back()->with('status', __('messages.settings.salary_run_approvals_reordered'));
    }

    private function userCanUseTeam($user, int $teamId): bool
    {
        if ($user->hasRole('super-admin')) {
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
}
