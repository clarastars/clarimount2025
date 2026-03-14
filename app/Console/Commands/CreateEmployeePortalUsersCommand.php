<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\EmployeePortalUserService;
use Illuminate\Console\Command;

class CreateEmployeePortalUsersCommand extends Command
{
    protected $signature = 'employees:create-portal-users
                           {--dry-run : List employees that would get a user without creating}';

    protected $description = 'Create portal users (role employee, password 12345678) for employees that do not have one yet';

    public function handle(EmployeePortalUserService $service): int
    {
        $dryRun = $this->option('dry-run');

        $employees = Employee::whereNull('user_id')
            ->where(function ($q) {
                $q->whereNotNull('work_email')->where('work_email', '!=', '')
                    ->orWhereNotNull('email')->where('email', '!=', '');
            })
            ->get();

        if ($employees->isEmpty()) {
            $this->info('No employees without a portal user (or without email) found.');
            return 0;
        }

        if ($dryRun) {
            $this->info('Would create portal users for ' . $employees->count() . ' employee(s):');
            foreach ($employees as $e) {
                $this->line('  - ' . $e->id . ': ' . ($e->work_email ?? $e->email) . ' (' . $e->full_name . ')');
            }
            return 0;
        }

        $created = 0;
        foreach ($employees as $employee) {
            try {
                $user = $service->createOrSyncPortalUser($employee);
                if ($user) {
                    $created++;
                    $this->line('Created/linked user for employee: ' . $employee->full_name . ' (' . ($employee->work_email ?? $employee->email) . ')');
                }
            } catch (\Throwable $e) {
                $this->warn('Failed for employee ' . $employee->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Done. Created/linked {$created} portal user(s).");
        return 0;
    }
}
