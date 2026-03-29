<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceImportRequest;
use App\Models\AttendanceImport;
use App\Models\AttendancePenalty;
use App\Models\BayzatSyncBatch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ZkDailyAttendance;
use App\Services\AttendanceImportService;
use App\Services\AttendancePenaltyService;
use App\Jobs\ProcessBayzatSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceImportService $importService,
        private AttendancePenaltyService $penaltyService
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

        // Get all employees in the company with their shifts
        $allEmployees = Employee::with(['shift.workdays'])
            ->where('company_id', $company->id)
            ->get();

        // Get existing attendance records. Prefer iClock API source when multiple exist per employee/date.
        $existingAttendance = ZkDailyAttendance::query()
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
            ->where('employees.company_id', $company->id)
            ->orderByRaw("(zk_devices.serial_number = 'FINGERPRINT_ICLOCK_API') ASC") // API record overwrites when keyBy
            ->get()
            ->keyBy(function ($record) {
                $dateStr = is_string($record->att_date) 
                    ? $record->att_date 
                    : ($record->att_date instanceof \Carbon\Carbon 
                        ? $record->att_date->format('Y-m-d') 
                        : $record->att_date);
                return ($record->employee_id ?? 'no_emp') . '_' . $dateStr;
            });

        // Build workday maps for quick lookup
        $shiftWorkdayMaps = [];
        foreach ($allEmployees as $employee) {
            if ($employee->shift) {
                $shiftWorkdayMaps[$employee->id] = $employee->shift->workdays
                    ->where('is_workday', true)
                    ->pluck('weekday')
                    ->toArray();
            }
        }

        // Generate all possible attendance records (including absences)
        // Only include past dates and today (not future dates)
        $today = Carbon::today('Asia/Riyadh');
        $allRecords = collect();
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Skip future dates - only process past dates and today
            if ($currentDate->isFuture()) {
                $currentDate->addDay();
                continue;
            }

            $dateStr = $currentDate->format('Y-m-d');
            $weekday = $currentDate->dayOfWeek; // 0=Sunday, 6=Saturday

            foreach ($allEmployees as $employee) {
                $workdays = $shiftWorkdayMaps[$employee->id] ?? [];
                $isWorkday = in_array($weekday, $workdays);

                // Check if there's an existing attendance record
                $key = $employee->id . '_' . $dateStr;
                $existingRecord = $existingAttendance->get($key);

                if ($existingRecord) {
                    // Use existing record (only if not future date)
                    $allRecords->push($existingRecord);
                } elseif ($isWorkday) {
                    // Create virtual record for absence (only for past/today dates)
                    $virtualRecord = (object) [
                        'id' => null,
                        'employee_id' => $employee->id,
                        'first_name' => $employee->first_name,
                        'last_name' => $employee->last_name,
                        'emp_code' => $employee->employee_id,
                        'company_id' => $employee->company_id,
                        'att_date' => $dateStr,
                        'device_pin' => $employee->fingerprint_device_id ?? null,
                        'first_punch' => null,
                        'last_punch' => null,
                        'punch_count' => 0,
                        'device_name' => null,
                        'serial_number' => null,
                        'is_virtual' => true, // Flag to identify virtual records
                    ];
                    $allRecords->push($virtualRecord);
                }
                // If not a workday, skip (no record needed)
            }

            $currentDate->addDay();
        }

        // Also filter out any existing records that are in the future
        $allRecords = $allRecords->filter(function ($record) use ($today) {
            $recordDate = Carbon::parse($record->att_date, 'Asia/Riyadh');
            return !$recordDate->isFuture();
        })->values();

        // Apply search filter if provided
        if (!empty($search)) {
            $allRecords = $allRecords->filter(function ($record) use ($search) {
                $fullName = ($record->first_name ?? '') . ' ' . ($record->last_name ?? '');
                return stripos($record->first_name ?? '', $search) !== false
                    || stripos($record->last_name ?? '', $search) !== false
                    || stripos($fullName, $search) !== false
                    || stripos($record->emp_code ?? '', $search) !== false
                    || stripos($record->device_pin ?? '', $search) !== false;
            });
        }

        // Sort records
        $allRecords = $allRecords->sortBy([
            ['att_date', 'desc'],
            ['first_name', 'asc'],
            ['last_name', 'asc'],
        ])->values();

        // Paginate manually
        $perPage = 15;
        $currentPage = $request->query('page', 1);
        $items = $allRecords->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        $fingerprintAttendance = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $allRecords->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Calculate attendance status and late minutes based on shifts
        // All timezone calculations use Asia/Riyadh timezone
        $employeeIds = $fingerprintAttendance->getCollection()
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->values();

        if ($employeeIds->isNotEmpty()) {
            // Eager load employees with shifts and workdays (already loaded above)
            $employees = $allEmployees->keyBy('id');

            // Workday maps already built above

            // First: Collect all absent records and process them from oldest to newest
            // This ensures that repeat_number increases correctly (oldest = 1, newest = highest)
            // Only process past dates and today (not future dates)
            $today = Carbon::today('Asia/Riyadh');
            
            $allAbsentRecords = $fingerprintAttendance->getCollection()
                ->filter(function ($record) use ($employees, $shiftWorkdayMaps, $today) {
                    $employee = $employees->get($record->employee_id);
                    if (!$employee || !$employee->shift) {
                        return false;
                    }

                    // If employee is not linked to a fingerprint device, skip absence penalties
                    if (!$employee->fingerprint_device_id) {
                        return false;
                    }

                    $attDate = Carbon::parse($record->att_date, 'Asia/Riyadh');
                    
                    // Only process past dates and today (not future dates)
                    if ($attDate->isFuture()) {
                        return false;
                    }
                    
                    $weekday = $attDate->dayOfWeek;
                    $workdays = $shiftWorkdayMaps[$employee->id] ?? [];

                    // Check if it's a workday
                    if (!in_array($weekday, $workdays)) {
                        return false;
                    }

                    // Check if absent (no first punch or virtual record)
                    return !$record->first_punch || ($record->is_virtual ?? false);
                })
                ->sortBy('att_date') // Sort from oldest to newest
                ->values();

            // Group absent records by employee to process them correctly
            $absentRecordsByEmployee = $allAbsentRecords->groupBy('employee_id');

            // Process absent records from oldest to newest for each employee
            foreach ($absentRecordsByEmployee as $employeeId => $employeeAbsentRecords) {
                $repeatNumber = 0;
                foreach ($employeeAbsentRecords as $record) {
                    $repeatNumber++;
                    if ($record->employee_id) {
                        $dateStr = is_string($record->att_date) 
                            ? $record->att_date 
                            : ($record->att_date instanceof \Carbon\Carbon 
                                ? $record->att_date->format('Y-m-d') 
                                : $record->att_date);
                        // Pass the calculated repeat_number directly
                        $this->penaltyService->calculateAbsenceWithoutExcusePenaltyWithRepeatNumber(
                            $record->employee_id, 
                            $dateStr, 
                            min($repeatNumber, 4)
                        );
                    }
                }
            }

            // Delete absence penalties for records that have punches (employee came to work)
            $recordsWithPunch = $fingerprintAttendance->getCollection()
                ->filter(function ($record) {
                    return $record->first_punch && !($record->is_virtual ?? false);
                });

            foreach ($recordsWithPunch as $record) {
                if ($record->employee_id) {
                    $dateStr = is_string($record->att_date) 
                        ? $record->att_date 
                        : ($record->att_date instanceof \Carbon\Carbon 
                            ? $record->att_date->format('Y-m-d') 
                            : $record->att_date);
                    
                    // Delete absence penalty if exists (employee has punch, so not absent)
                    AttendancePenalty::where('employee_id', $record->employee_id)
                        ->where('attendance_date', $dateStr)
                        ->where('violation_type', 'absent_without_excuse')
                        ->delete();
                }
            }

            // Now process all records for display (status, late minutes, etc.)
            $fingerprintAttendance->getCollection()->transform(function ($record) use ($employees, $shiftWorkdayMaps) {
                $employee = $employees->get($record->employee_id);

                // No employee or no shift assigned
                if (!$employee || !$employee->shift) {
                    $record->status_ar = 'غير محدد';
                    $record->late_minutes = null;
                    return $record;
                }

                // If employee is not linked to a fingerprint device, do not apply any attendance status or penalties
                if (!$employee->fingerprint_device_id) {
                    $record->status_ar = 'غير مربوط ببصمة';
                    $record->late_minutes = null;
                    return $record;
                }

                // Get weekday of attendance date (0=Sunday, 6=Saturday)
                $attDate = Carbon::parse($record->att_date, 'Asia/Riyadh');
                $weekday = $attDate->dayOfWeek; // Carbon: 0=Sunday, 1=Monday, ..., 6=Saturday
                $workdays = $shiftWorkdayMaps[$employee->id] ?? [];

                // Check if it's a workday
                if (!in_array($weekday, $workdays)) {
                    $record->status_ar = 'إجازة';
                    $record->late_minutes = 0;
                    return $record;
                }

                // No first punch (absent) or virtual record
                if (!$record->first_punch || ($record->is_virtual ?? false)) {
                    $record->status_ar = 'غائب';
                    $record->late_minutes = null;
                    // Penalty already created above, no need to create again
                    return $record;
                }

                // Calculate late minutes (per-weekday start when configured on shift workdays)
                $dateStr = $attDate->format('Y-m-d');
                $expectedStart = Carbon::parse(
                    $dateStr . ' ' . $employee->shift->effectiveStartTimeStringForWeekday($weekday),
                    'Asia/Riyadh'
                );
                
                // First punch time (convert from UTC to Asia/Riyadh)
                $firstPunch = Carbon::parse($record->first_punch)->setTimezone('Asia/Riyadh');
                
                // Calculate signed difference in minutes
                // Use timestamp difference to get signed value
                // Positive = late, Negative = early
                $actualLateMinutes = (int) round(($firstPunch->timestamp - $expectedStart->timestamp) / 60);
                
                // Apply grace period
                // If actualLateMinutes is negative (early), lateMinutes should be 0
                $lateMinutes = max(0, $actualLateMinutes - $employee->shift->grace_minutes);

                // Set status based on late minutes
                $record->status_ar = $lateMinutes > 0 ? 'متأخر' : 'في الموعد';
                $record->late_minutes = $lateMinutes;

                return $record;
            });
            
            // Load penalties for all records
            $attendanceDates = $fingerprintAttendance->getCollection()
                ->pluck('att_date')
                ->map(function ($date) {
                    if (is_string($date)) {
                        return $date;
                    }
                    return $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
                })
                ->unique()
                ->values()
                ->toArray();

            $penalties = AttendancePenalty::whereIn('employee_id', $employeeIds->toArray())
                ->whereIn('attendance_date', $attendanceDates)
                ->get()
                ->keyBy(function ($penalty) {
                    return $penalty->employee_id . '_' . $penalty->attendance_date->format('Y-m-d');
                });

            // Attach penalty to each record
            $fingerprintAttendance->getCollection()->transform(function ($record) use ($penalties) {
                $dateStr = is_string($record->att_date) 
                    ? $record->att_date 
                    : ($record->att_date instanceof \Carbon\Carbon 
                        ? $record->att_date->format('Y-m-d') 
                        : $record->att_date);
                $key = $record->employee_id . '_' . $dateStr;
                $record->penalty = $penalties->get($key);
                return $record;
            });
            
            // Apply status filter if provided
            if (!empty($statusFilter)) {
                $fingerprintAttendance->setCollection(
                    $fingerprintAttendance->getCollection()->filter(function ($record) use ($statusFilter) {
                        if ($statusFilter === 'late') {
                            return $record->status_ar === 'متأخر';
                        } elseif ($statusFilter === 'on_time') {
                            return $record->status_ar === 'في الموعد';
                        }
                        return true;
                    })->values()
                );
            }
        } else {
            // No employees found, set default status
            $fingerprintAttendance->getCollection()->transform(function ($record) {
                $record->status_ar = 'غير محدد';
                $record->late_minutes = null;
                return $record;
            });
            
            // Apply status filter if provided
            if (!empty($statusFilter)) {
                $fingerprintAttendance->setCollection(
                    $fingerprintAttendance->getCollection()->filter(function ($record) use ($statusFilter) {
                        if ($statusFilter === 'late') {
                            return $record->status_ar === 'متأخر';
                        } elseif ($statusFilter === 'on_time') {
                            return $record->status_ar === 'في الموعد';
                        }
                        return true;
                    })->values()
                );
            }
        }

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

                // Calculate late minutes (per-weekday start when configured)
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
        $currentPage = $request->query('page', 1);
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
