<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeOffboardingCase;
use App\Models\EmployeeOffboardingItem;
use App\Models\OffboardingChecklistItemTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OffboardingCaseService
{
    public function __construct(
        private OffboardingItemApprovalService $itemApprovalService,
        private OffboardingClearanceApprovalService $clearanceApprovalService,
        private OffboardingApprovalNotificationService $notificationService,
    ) {}

    public function activeCaseForEmployee(Employee $employee): ?EmployeeOffboardingCase
    {
        return EmployeeOffboardingCase::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                EmployeeOffboardingCase::STATUS_IN_PROGRESS,
                EmployeeOffboardingCase::STATUS_PENDING_CLEARANCE,
            ])
            ->latest('id')
            ->first();
    }

    public function start(Employee $employee, User $actor, ?string $terminationDate = null): EmployeeOffboardingCase
    {
        $employee->loadMissing('company');
        $company = $employee->company;

        if ($company === null) {
            throw ValidationException::withMessages([
                'employee' => __('messages.offboarding.employee_missing_company'),
            ]);
        }

        if ($employee->employment_status === 'terminated') {
            throw ValidationException::withMessages([
                'employee' => __('messages.offboarding.employee_already_terminated'),
            ]);
        }

        if ($this->activeCaseForEmployee($employee) !== null) {
            throw ValidationException::withMessages([
                'employee' => __('messages.offboarding.active_case_exists'),
            ]);
        }

        $templates = OffboardingChecklistItemTemplate::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($templates->isEmpty()) {
            throw ValidationException::withMessages([
                'employee' => __('messages.offboarding.no_checklist_items'),
            ]);
        }

        foreach ($templates as $template) {
            if (! $this->itemApprovalService->hasActiveStepsForTemplate((int) $template->id)) {
                throw ValidationException::withMessages([
                    'employee' => __('messages.offboarding.template_missing_steps', ['title' => $template->title]),
                ]);
            }
        }

        $case = DB::transaction(function () use ($employee, $company, $actor, $templates, $terminationDate) {
            $case = EmployeeOffboardingCase::query()->create([
                'employee_id' => $employee->id,
                'company_id' => $company->id,
                'status' => EmployeeOffboardingCase::STATUS_IN_PROGRESS,
                'started_by' => $actor->id,
                'started_at' => now(),
                'termination_date' => $terminationDate ?: now()->toDateString(),
            ]);

            foreach ($templates as $template) {
                EmployeeOffboardingItem::query()->create([
                    'offboarding_case_id' => $case->id,
                    'template_id' => $template->id,
                    'title' => $template->title,
                    'attachment_mode' => $template->attachment_mode,
                    'sort_order' => $template->sort_order,
                    'status' => EmployeeOffboardingItem::STATUS_PENDING,
                ]);
            }

            return $case->load('items');
        });

        $this->notificationService->notifyCaseStarted($case, $company, $actor);

        return $case;
    }

    public function afterItemApproved(
        EmployeeOffboardingItem $item,
        Company $company,
        User $actor,
    ): void {
        $item->loadMissing('offboardingCase.items');
        $case = $item->offboardingCase;

        if ($case === null) {
            return;
        }

        $this->notificationService->notifyItemFinalized($item, $company, $actor);

        if (! $case->allItemsApproved()) {
            return;
        }

        $this->moveToClearance($case, $company, $actor);
    }

    public function moveToClearance(
        EmployeeOffboardingCase $case,
        Company $company,
        User $actor,
    ): void {
        if (! $case->allItemsApproved()) {
            return;
        }

        if (! $this->clearanceApprovalService->hasActiveStepsForCompany($company)) {
            $this->finalize($case, $company, $actor);

            return;
        }

        $case->update([
            'status' => EmployeeOffboardingCase::STATUS_PENDING_CLEARANCE,
        ]);

        $this->notificationService->notifyClearanceStarted($case->fresh(['items', 'employee']), $company, $actor);
    }

    public function finalize(
        EmployeeOffboardingCase $case,
        Company $company,
        User $actor,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($case, $actor, $notes) {
            $case->loadMissing('employee');

            $terminationDate = $case->termination_date?->toDateString() ?: now()->toDateString();

            $case->update([
                'status' => EmployeeOffboardingCase::STATUS_CLEARED,
                'cleared_at' => now(),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'termination_date' => $terminationDate,
            ]);

            $case->employee?->update([
                'employment_status' => 'terminated',
                'termination_date' => $terminationDate,
            ]);
        });

        $this->notificationService->notifyCaseFinalized($case->fresh(['employee', 'items']), $company, $actor);
    }
}
