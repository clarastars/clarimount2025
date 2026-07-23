<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exports\EmployeesMissingHireDateExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissingHireDateExportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $missingCount = Employee::query()
            ->whereNull('hire_date')
            ->count();

        $byCompany = Employee::query()
            ->whereNull('hire_date')
            ->leftJoin('companies', 'companies.id', '=', 'employees.company_id')
            ->selectRaw('employees.company_id')
            ->selectRaw('companies.name_en as company_name_en')
            ->selectRaw('companies.name_ar as company_name_ar')
            ->selectRaw('COUNT(employees.id) as employees_count')
            ->groupBy('employees.company_id', 'companies.name_en', 'companies.name_ar')
            ->orderBy('companies.name_en')
            ->get()
            ->map(fn ($row) => [
                'company_id' => $row->company_id !== null ? (int) $row->company_id : null,
                'company_name_en' => (string) ($row->company_name_en ?: 'No Company'),
                'company_name_ar' => (string) ($row->company_name_ar ?: 'بدون شركة'),
                'employees_count' => (int) $row->employees_count,
            ])
            ->values()
            ->all();

        return Inertia::render('settings/MissingHireDateExport', [
            'missingCount' => $missingCount,
            'byCompany' => $byCompany,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $employees = Employee::query()
            ->whereNull('hire_date')
            ->leftJoin('companies', 'companies.id', '=', 'employees.company_id')
            ->orderBy('companies.name_en')
            ->orderBy('employees.id')
            ->get([
                'employees.id',
                'employees.employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.company_id',
                'companies.name_en as company_name_en',
                'companies.name_ar as company_name_ar',
                'employees.employment_status',
                'employees.work_email',
                'employees.personal_email',
                'employees.annual_leave_balance',
                'employees.leave_accrued_balance',
                'employees.created_at',
            ]);

        $filename = 'employees-missing-hire-date-'.now('Asia/Riyadh')->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new EmployeesMissingHireDateExport($employees),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
