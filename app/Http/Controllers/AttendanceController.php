<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceImportRequest;
use App\Models\AttendanceDailyPresentation;
use App\Models\AttendanceImport;
use App\Models\AttendancePenalty;
use App\Models\BayzatSyncBatch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ZkDailyAttendance;
use App\Services\AttendanceImportService;
use App\Jobs\ProcessBayzatSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceImportService $importService,
    ) {}

    public function index(Request $request, Company $company): Response
    {
        $user = Auth::user();
        
        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }
        
        $ownedCompanyIds = collect([$company->id]);

        $imports = AttendanceImport::query()
            ->with(['user', 'syncBatches.company'])
            ->withCount(['records', 'validRecords', 'invalidRecords'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Get sync statistics
        $syncStats = [
            'total_imports' => AttendanceImport::count(),
            'pending_syncs' => BayzatSyncBatch::where('status', 'pending')->count(),
            'failed_syncs' => BayzatSyncBatch::where('status', 'failed')->count(),
        ];

        // Get fingerprint attendance data with date range filtering
        $filterType = $request->query('filter', 'today');
        $fromDate = $request->query('from');
        $toDate = $request->query('to');
        $search = $request->query('search', '');
        $statusFilter = $request->query('status'); // 'late' or 'on_time'

        // Calculate date range based on filter type
        $now = Carbon::now('Asia/Riyadh');
        switch ($filterType) {
            case 'today':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
            case 'month':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
            case 'custom':
                if ($fromDate && $toDate) {
                    $startDate = Carbon::parse($fromDate, 'Asia/Riyadh')->startOfDay();
                    $endDate = Carbon::parse($toDate, 'Asia/Riyadh')->endOfDay();
                } else {
                    // Fallback to today if dates not provided
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                }
                break;
            default:
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
        }

        // Fingerprint grid: read precomputed rows (rebuilt by jobs / attendance:rebuild-presentations)
        $startYmd = $startDate->format('Y-m-d');
        $endYmd = $endDate->format('Y-m-d');

        $presentationQuery = AttendanceDailyPresentation::query()
            ->where('attendance_daily_presentations.company_id', $company->id)
            ->whereBetween('att_date', [$startYmd, $endYmd])
            ->with(['employee' => static function ($q) {
                $q->select('id', 'first_name', 'father_name', 'last_name', 'employee_id', 'company_id');
            }]);

        if ($search !== '' && $search !== null) {
            $presentationQuery->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($eq) use ($search) {
                    $eq->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('father_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$search.'%'])
                        ->orWhereRaw("CONCAT(first_name, ' ', father_name, ' ', last_name) LIKE ?", ['%'.$search.'%'])
                        ->orWhere('employee_id', 'like', '%'.$search.'%');
                })->orWhere('attendance_daily_presentations.device_pin', 'like', '%'.$search.'%');
            });
        }

        if ($statusFilter === 'late') {
            $presentationQuery->where('status_ar', 'متأخر');
        } elseif ($statusFilter === 'on_time') {
            $presentationQuery->where('status_ar', 'في الموعد');
        }

        $presentationQuery
            ->join('employees', 'employees.id', '=', 'attendance_daily_presentations.employee_id')
            ->orderByDesc('attendance_daily_presentations.att_date')
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->select('attendance_daily_presentations.*');

        $paginator = $presentationQuery->paginate(15)->withQueryString();

        $penaltyMap = $this->penaltyMapForPresentationPage($paginator->getCollection());

        $fingerprintAttendance = $paginator->through(function (AttendanceDailyPresentation $p) use ($penaltyMap) {
            $e = $p->employee;
            $dateStr = $p->att_date instanceof Carbon ? $p->att_date->format('Y-m-d') : (string) $p->att_date;
            $key = $p->employee_id.'_'.$dateStr;

            return [
                'id' => $p->id,
                'employee_id' => $p->employee_id,
                'first_name' => $e->first_name ?? '',
                'father_name' => $e->father_name ?? '',
                'last_name' => $e->last_name ?? '',
                'emp_code' => $e->employee_id ?? null,
                'company_id' => (int) $p->company_id,
                'att_date' => $p->att_date,
                'device_pin' => $p->device_pin,
                'first_punch' => $p->first_punch,
                'last_punch' => $p->last_punch,
                'punch_count' => $p->punch_count,
                'first_verify_mode' => $p->first_verify_mode,
                'last_verify_mode' => $p->last_verify_mode,
                'device_name' => $p->device_name,
                'serial_number' => $p->serial_number,
                'status_ar' => $p->status_ar,
                'late_minutes' => $p->late_minutes,
                'is_virtual' => $p->is_virtual_absence,
                'penalty' => $penaltyMap->get($key),
            ];
        });

        // Get statistics for selected date range (filtered by company)
        $statsQuery = ZkDailyAttendance::query()
            ->leftJoin('employees', function ($join) {
                $join->on('employees.fingerprint_device_id', '=', 'zk_daily_attendance.device_pin');
            })
            ->whereBetween('zk_daily_attendance.att_date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->where('employees.company_id', $company->id);

        $fingerprintStats = [
            'present_count' => (clone $statsQuery)->distinct('zk_daily_attendance.device_pin')->count('zk_daily_attendance.device_pin'),
            'total_punches' => (clone $statsQuery)->sum('zk_daily_attendance.punch_count'),
        ];

        return Inertia::render('Attendance/Index', [
            'company' => $company,
            'imports' => $imports,
            'syncStats' => $syncStats,
            'fingerprintAttendance' => $fingerprintAttendance,
            'fingerprintStats' => $fingerprintStats,
            'filters' => [
                'filter' => $filterType,
                'from' => $fromDate,
                'to' => $toDate,
                'search' => $search,
                'status' => $statusFilter,
            ],
            'dateRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    public function late(Request $request, Company $company): Response
    {
        $user = Auth::user();
        
        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        // Get filter type (default: month)
        $filterType = $request->query('filter', 'month');
        $fromDate = $request->query('from');
        $toDate = $request->query('to');
        $search = $request->query('search', '');

        // Calculate date range based on filter type
        $now = Carbon::now('Asia/Riyadh');
        switch ($filterType) {
            case 'today':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
            case 'month':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
            case 'custom':
                if ($fromDate && $toDate) {
                    $startDate = Carbon::parse($fromDate, 'Asia/Riyadh')->startOfDay();
                    $endDate = Carbon::parse($toDate, 'Asia/Riyadh')->endOfDay();
                } else {
                    // Fallback to current month if dates not provided
                    $startDate = $now->copy()->startOfMonth();
                    $endDate = $now->copy()->endOfMonth();
                }
                break;
            default:
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
        }

        // Query late attendance records
        $query = ZkDailyAttendance::query()
            ->select([
                'zk_daily_attendance.*',
                'employees.id as employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_id as emp_code',
                'employees.company_id',
                'zk_devices.name as device_name',
                'zk_devices.serial_number',
            ])
            ->leftJoin('employees', function ($join) {
                $join->on('employees.fingerprint_device_id', '=', 'zk_daily_attendance.device_pin');
            })
            ->leftJoin('zk_devices', 'zk_devices.id', '=', 'zk_daily_attendance.device_id')
            ->whereBetween('zk_daily_attendance.att_date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->where('employees.company_id', $company->id);

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('employees.first_name', 'like', "%{$search}%")
                  ->orWhere('employees.last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(employees.first_name, ' ', employees.last_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('employees.employee_id', 'like', "%{$search}%")
                  ->orWhere('zk_daily_attendance.device_pin', 'like', "%{$search}%");
            });
        }

        // Get all records first (before pagination) to calculate late minutes
        $allRecords = $query
            ->orderBy('zk_daily_attendance.att_date', 'desc')
            ->orderBy('employees.first_name', 'asc')
            ->orderBy('employees.last_name', 'asc')
            ->get();

        // Calculate attendance status and late minutes based on shifts
        // All timezone calculations use Asia/Riyadh timezone
        $employeeIds = $allRecords->pluck('employee_id')
            ->filter()
            ->unique()
            ->values();

        if ($employeeIds->isNotEmpty()) {
            // Eager load employees with shifts and workdays
            $employees = Employee::with(['shift.workdays'])
                ->whereIn('id', $employeeIds)
                ->get()
                ->keyBy('id');

            // Build workday maps for quick lookup
            $shiftWorkdayMaps = [];
            foreach ($employees as $employee) {
                if ($employee->shift) {
                    $shiftWorkdayMaps[$employee->id] = $employee->shift->workdays
                        ->where('is_workday', true)
                        ->pluck('weekday')
                        ->toArray();
                }
            }

            // Process each attendance record
            $allRecords = $allRecords->map(function ($record) use ($employees, $shiftWorkdayMaps) {
                $employee = $employees->get($record->employee_id);
                $date = $record->att_date->format('Y-m-d');

                // No employee or no shift assigned
                if (!$employee || !$employee->shift) {
                    $record->status_ar = 'غير محدد';
                    $record->late_minutes = null;
                    return $record;
                }

                // Get weekday of attendance date
                $attDate = Carbon::parse($date, 'Asia/Riyadh');
                $weekday = $attDate->dayOfWeek;
                $workdays = $shiftWorkdayMaps[$employee->id] ?? [];

                // Check if it's a workday
                if (!in_array($weekday, $workdays)) {
                    $record->status_ar = 'إجازة';
                    $record->late_minutes = 0;
                    return $record;
                }

                // No first punch (absent)
                if (!$record->first_punch) {
                    $record->status_ar = 'غائب';
                    $record->late_minutes = null;
                    return $record;
                }

                // Calculate late minutes (respect day-specific shift_workdays override when set)
                $expectedStart = Carbon::parse(
                    $date . ' ' . $employee->shift->effectiveStartTimeStringForWeekday($weekday),
                    'Asia/Riyadh'
                );
                $firstPunch = Carbon::parse($record->first_punch)->setTimezone('Asia/Riyadh');
                $actualLateMinutes = (int) round(($firstPunch->timestamp - $expectedStart->timestamp) / 60);
                $lateMinutes = max(0, $actualLateMinutes - $employee->shift->grace_minutes);

                // Set status based on late minutes
                $record->status_ar = $lateMinutes > 0 ? 'متأخر' : 'في الموعد';
                $record->late_minutes = $lateMinutes;

                return $record;
            });

            // Filter only late records (late_minutes > 0 and status_ar = 'متأخر')
            $allRecords = $allRecords->filter(function ($record) {
                return $record->late_minutes > 0 && $record->status_ar === 'متأخر';
            })->values();
        } else {
            // No employees found, set empty collection
            $allRecords = collect([]);
        }

        // Sort by date (desc) then by late_minutes (desc)
        $allRecords = $allRecords->sort(function ($a, $b) {
            // First sort by date (desc)
            $dateCompare = $b->att_date->format('Y-m-d') <=> $a->att_date->format('Y-m-d');
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            // Then sort by late_minutes (desc)
            return ($b->late_minutes ?? 0) <=> ($a->late_minutes ?? 0);
        })->values();

        // Calculate statistics from all late records
        $lateRecords = $allRecords;

        // Paginate the filtered results
        $currentPage = (int) $request->query('page', '1');
        $perPage = 15;
        $items = $lateRecords->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $lateAttendance = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $lateRecords->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $stats = [
            'total_late_records' => $lateRecords->count(),
            'total_late_minutes' => $lateRecords->sum('late_minutes') ?? 0,
            'average_late_minutes' => $lateRecords->avg('late_minutes') ?? 0,
        ];

        return Inertia::render('Attendance/Late', [
            'company' => $company,
            'lateAttendance' => $lateAttendance,
            'stats' => $stats,
            'filters' => [
                'filter' => $filterType,
                'from' => $fromDate,
                'to' => $toDate,
                'search' => $search,
            ],
            'dateRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Attendance/Import');
    }

    public function store(AttendanceImportRequest $request, Company $company)
    {
        $user = Auth::user();
        
        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }
        
        try {
            $import = $this->importService->processImport(
                $request->file('file'),
                Auth::id(),
                Auth::user()->team_id
            );

            return redirect()
                ->route('attendance.show', [$company, $import])
                ->with('success', __('messages.attendance_import_started'));

        } catch (\Exception $e) {
            return back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Company $company, AttendanceImport $attendance): Response
    {
        $user = Auth::user();
        
        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        $attendance->load([
            'user',
            'team',
            'records' => function ($query) {
                $query->orderBy('date', 'desc')->orderBy('csv_employee_id');
            },
            'syncBatches.company'
        ]);

        // Get sync progress per company
        $syncProgress = $attendance->syncBatches->map(function ($batch) {
            return [
                'company_id' => $batch->company_id,
                'company_name' => $batch->company->name,
                'status' => $batch->status,
                'total_records' => $batch->total_records,
                'synced_records' => $batch->synced_records,
                'failed_records' => $batch->failed_records,
                'success_rate' => $batch->success_rate,
                'completion_percentage' => $batch->completion_percentage,
                'started_at' => $batch->started_at,
                'completed_at' => $batch->completed_at,
                'error_message' => $batch->error_message,
            ];
        });

        // Get validation errors summary
        $validationSummary = [
            'total_errors' => count($attendance->validation_errors ?? []),
            'unmapped_departments' => $attendance->unmapped_departments ?? [],
            'error_types' => $this->categorizeValidationErrors($attendance->validation_errors ?? []),
        ];

        return Inertia::render('Attendance/Show', [
            'company' => $company,
            'import' => $attendance,
            'syncProgress' => $syncProgress,
            'validationSummary' => $validationSummary,
        ]);
    }

    public function retrySync(Company $company, AttendanceImport $attendance)
    {
        $user = Auth::user();
        
        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        try {
            $this->importService->retryFailedRecords($attendance);

            return back()->with('success', __('messages.sync_retry_initiated'));

        } catch (\Exception $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }
    }

    public function retrySyncBatch(Company $company, BayzatSyncBatch $batch)
    {
        $user = Auth::user();
        
        // Verify user owns this company
        if (!$user->ownedCompanies()->where('id', $company->id)->exists()) {
            abort(403, 'You do not have access to this company.');
        }

        try {
            // Reset batch status
            $batch->update([
                'status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
                'error_message' => null,
                'synced_records' => 0,
                'failed_records' => 0,
            ]);

            // Reset associated records
            $batch->attendanceImport->records()
                ->where('company_id', $batch->company_id)
                ->where('bayzat_sync_status', 'failed')
                ->update([
                    'bayzat_sync_status' => 'pending',
                    'bayzat_sync_error' => null,
                ]);

            // Dispatch new sync job
            ProcessBayzatSync::dispatch($batch);

            return back()->with('success', __('messages.batch_sync_retry_initiated'));

        } catch (\Exception $e) {
            return back()->withErrors(['sync' => $e->getMessage()]);
        }
    }

    public function downloadTemplate()
    {
        $headers = [
            'Employee ID',
            'First Name',
            'Department',
            'Date',
            'Weekday',
            'Check In',
            'Check Out',
            'Clock In',
            'Clock Out',
            'Work Duration',
            'Break Duration',
            'Overtime Duration',
        ];

        $sampleData = [
            ['EMP001', 'John Doe', 'IT Department', '2024-01-15', 'Monday', '09:00:00', '17:00:00', '09:00:00', '17:00:00', '8.0', '1.0', '0.0'],
            ['EMP002', 'Jane Smith', 'HR Department', '2024-01-15', 'Monday', '08:30:00', '16:30:00', '08:30:00', '16:30:00', '8.0', '1.0', '0.0'],
        ];

        $filename = 'attendance_import_template.csv';
        $handle = fopen('php://output', 'w');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        fputcsv($handle, $headers);
        foreach ($sampleData as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        exit;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AttendanceDailyPresentation>  $items
     * @return \Illuminate\Support\Collection<string, AttendancePenalty>
     */
    private function penaltyMapForPresentationPage(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $employeeIds = $items->pluck('employee_id')->unique()->values()->all();
        $dates = $items->map(function (AttendanceDailyPresentation $p) {
            $d = $p->att_date;

            return $d instanceof Carbon ? $d->format('Y-m-d') : (string) $d;
        })->unique()->values()->all();

        return AttendancePenalty::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('attendance_date', $dates)
            ->get()
            ->keyBy(static function (AttendancePenalty $penalty) {
                $d = $penalty->attendance_date;
                $dateStr = $d instanceof Carbon ? $d->format('Y-m-d') : (string) $d;

                return $penalty->employee_id.'_'.$dateStr;
            });
    }

    private function categorizeValidationErrors(array $errors): array
    {
        $categories = [
            'employee_not_found' => 0,
            'department_not_mapped' => 0,
            'invalid_date' => 0,
            'missing_fields' => 0,
            'other' => 0,
        ];

        foreach ($errors as $error) {
            if (str_contains($error, 'Employee ID') && str_contains($error, 'not found')) {
                $categories['employee_not_found']++;
            } elseif (str_contains($error, 'Department') && str_contains($error, 'not mapped')) {
                $categories['department_not_mapped']++;
            } elseif (str_contains($error, 'date format')) {
                $categories['invalid_date']++;
            } elseif (str_contains($error, 'required')) {
                $categories['missing_fields']++;
            } else {
                $categories['other']++;
            }
        }

        return $categories;
    }
}
