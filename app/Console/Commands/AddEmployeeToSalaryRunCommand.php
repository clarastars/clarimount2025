<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalaryRun;
use App\Services\SalaryRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddEmployeeToSalaryRunCommand extends Command
{
    protected $signature = 'salary-runs:add-employee
        {company : Company ID}
        {salary-run : Salary run ID}
        {employee : Employee ID}
        {--dry-run : Preview the inclusion without saving}
        {--force : Allow adding to a finalized salary run}';

    protected $description = 'Add one employee to an existing salary run and calculate their payroll line';

    public function handle(SalaryRunService $salaryRunService): int
    {
        $companyId = (int) $this->argument('company');
        $salaryRunId = (int) $this->argument('salary-run');
        $employeeId = (int) $this->argument('employee');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $salaryRun = SalaryRun::query()
            ->where('company_id', $companyId)
            ->whereKey($salaryRunId)
            ->first();

        if ($salaryRun === null) {
            $this->error(__('messages.salary_runs.add_employee_run_not_found'));

            return self::FAILURE;
        }

        $itemsCountBefore = $salaryRun->items()->count();

        if ($dryRun) {
            $this->warn(__('messages.salary_runs.add_employee_dry_run_notice'));
        }

        try {
            $item = DB::transaction(function () use ($salaryRunService, $companyId, $salaryRunId, $employeeId, $force, $dryRun, $itemsCountBefore) {
                $item = $salaryRunService->addEmployeeToSalaryRun($companyId, $salaryRunId, $employeeId, $force);

                if ($dryRun) {
                    $this->printItemSummary($item, $itemsCountBefore);
                    throw new DryRunRollbackException();
                }

                return $item;
            });
        } catch (DryRunRollbackException) {
            $this->newLine();
            $this->comment(__('messages.salary_runs.add_employee_dry_run_rolled_back'));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info(__('messages.salary_runs.add_employee_success'));
        $this->printItemSummary($item->fresh(['employee']), $itemsCountBefore);

        return self::SUCCESS;
    }

    private function printItemSummary(\App\Models\SalaryRunItem $item, int $itemsCountBefore): void
    {
        $employee = $item->employee;

        $this->table(
            [__('messages.salary_runs.employee'), __('messages.salary_runs.basic_salary'), __('messages.salary_runs.gross_salary'), __('messages.salary_runs.penalties_total'), __('messages.salary_runs.additions_total'), __('messages.salary_runs.net_salary')],
            [[
                $employee?->full_name ?? (string) $item->employee_id,
                number_format((float) $item->basic_salary, 2),
                number_format((float) $item->gross_salary, 2),
                number_format((float) $item->penalties_total, 2),
                number_format((float) $item->additions_total, 2),
                number_format((float) $item->net_salary, 2),
            ]]
        );

        $this->line(__('messages.salary_runs.add_employee_items_count', [
            'before' => $itemsCountBefore,
            'after' => $itemsCountBefore + 1,
        ]));
    }
}
