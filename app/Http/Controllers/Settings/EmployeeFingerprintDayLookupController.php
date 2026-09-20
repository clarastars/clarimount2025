<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeFingerprintDayLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeFingerprintDayLookupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return Inertia::render('settings/EmployeeFingerprintDayLookup', [
            'defaultDate' => now('Asia/Riyadh')->toDateString(),
        ]);
    }

    public function search(Request $request, EmployeeFingerprintDayLookupService $service): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $query = trim((string) $request->query('q', ''));

        return response()->json([
            'results' => $service->searchEmployees($query),
        ]);
    }

    public function show(Request $request, EmployeeFingerprintDayLookupService $service): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from_api' => ['sometimes', 'boolean'],
        ]);

        $employee = Employee::query()->with('company')->findOrFail((int) $validated['employee_id']);
        $result = $service->lookup($employee, $validated['date']);

        if ($request->boolean('from_api')) {
            $result['api_punches'] = $service->fetchFromApi($employee, $validated['date']);
        } else {
            $result['api_punches'] = null;
        }

        return response()->json($result);
    }
}
