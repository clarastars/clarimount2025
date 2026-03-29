<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftWorkday;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts.
     */
    public function index(Request $request): Response
    {
        $query = Shift::query()->withCount('employees');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $shifts = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new shift.
     */
    public function create(): Response
    {
        return Inertia::render('Shifts/Create');
    }

    /**
     * Store a newly created shift.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'end_time' => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'grace_minutes' => 'nullable|integer|min:0|max:120',
            'workdays' => 'required|array',
            'workdays.*.weekday' => 'required|integer|min:0|max:6',
            'workdays.*.is_workday' => 'required|boolean',
            'workdays.*.start_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'workdays.*.end_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
        ]);

        $workdays = $this->normalizeAndValidateWorkdayTimes($validated['workdays']);

        DB::transaction(function () use ($validated, $workdays) {
            $shift = Shift::create([
                'name' => $validated['name'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'grace_minutes' => $validated['grace_minutes'] ?? 0,
            ]);

            foreach ($workdays as $wd) {
                ShiftWorkday::create([
                    'shift_id' => $shift->id,
                    'weekday' => $wd['weekday'],
                    'is_workday' => $wd['is_workday'],
                    'start_time' => $wd['start_time'],
                    'end_time' => $wd['end_time'],
                ]);
            }
        });

        return redirect()->route('shifts.index')
            ->with('success', __('shifts.created_successfully'));
    }

    /**
     * Display the specified shift (redirect to edit).
     */
    public function show(Shift $shift): RedirectResponse
    {
        return redirect()->route('shifts.edit', $shift);
    }

    /**
     * Show the form for editing the specified shift.
     */
    public function edit(Shift $shift): Response
    {
        $shift->load('workdays');
        return Inertia::render('Shifts/Edit', [
            'shift' => $shift,
        ]);
    }

    /**
     * Update the specified shift.
     */
    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'end_time' => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'grace_minutes' => 'nullable|integer|min:0|max:120',
            'workdays' => 'required|array',
            'workdays.*.weekday' => 'required|integer|min:0|max:6',
            'workdays.*.is_workday' => 'required|boolean',
            'workdays.*.start_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'workdays.*.end_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
        ]);

        $workdays = $this->normalizeAndValidateWorkdayTimes($validated['workdays']);

        DB::transaction(function () use ($validated, $workdays, $shift) {
            $shift->update([
                'name' => $validated['name'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'grace_minutes' => $validated['grace_minutes'] ?? 0,
            ]);

            $shift->workdays()->delete();
            foreach ($workdays as $wd) {
                ShiftWorkday::create([
                    'shift_id' => $shift->id,
                    'weekday' => $wd['weekday'],
                    'is_workday' => $wd['is_workday'],
                    'start_time' => $wd['start_time'],
                    'end_time' => $wd['end_time'],
                ]);
            }
        });

        return redirect()->route('shifts.index')
            ->with('success', __('shifts.updated_successfully'));
    }

    /**
     * Remove the specified shift.
     */
    public function destroy(Shift $shift): RedirectResponse
    {
        $employeesCount = $shift->employees()->count();
        if ($employeesCount > 0) {
            return back()->withErrors([
                'error' => __('shifts.cannot_delete_has_employees', ['count' => $employeesCount]),
            ]);
        }

        $shift->delete();
        return redirect()->route('shifts.index')
            ->with('success', __('shifts.deleted_successfully'));
    }

    /**
     * @param  array<int, array{weekday: int, is_workday: bool, start_time?: ?string, end_time?: ?string}>  $workdays
     * @return array<int, array{weekday: int, is_workday: bool, start_time: ?string, end_time: ?string}>
     */
    private function normalizeAndValidateWorkdayTimes(array $workdays): array
    {
        $out = [];
        foreach ($workdays as $i => $wd) {
            $isWorkday = (bool) ($wd['is_workday'] ?? false);
            $start = isset($wd['start_time']) ? trim((string) $wd['start_time']) : '';
            $end = isset($wd['end_time']) ? trim((string) $wd['end_time']) : '';
            $start = $start === '' ? null : $start;
            $end = $end === '' ? null : $end;

            if (! $isWorkday) {
                $out[] = [
                    'weekday' => (int) $wd['weekday'],
                    'is_workday' => false,
                    'start_time' => null,
                    'end_time' => null,
                ];

                continue;
            }

            if (($start !== null && $end === null) || ($start === null && $end !== null)) {
                throw ValidationException::withMessages([
                    "workdays.{$i}.start_time" => [__('shifts.custom_times_both_required')],
                ]);
            }

            $out[] = [
                'weekday' => (int) $wd['weekday'],
                'is_workday' => true,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        return $out;
    }
}
