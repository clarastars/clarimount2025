<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class FingerprintDeviceEmployeeController extends Controller
{
    use AuthorizesEmployeeAccess;

    /**
     * Display employees fetched from the fingerprint device API.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $baseUrl = rtrim(config('services.fingerprint_device.base_url'), '/');
        $token = config('services.fingerprint_device.token');
        $timeout = (int) config('services.fingerprint_device.timeout', 15);

        $employees = [];
        $error = null;

        $url = $baseUrl . '/personnel/api/employees/';

        if (empty($token) || empty($baseUrl)) {
            $error = __('messages.employees.fingerprint_device_not_configured');
        } else {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Authorization' => 'Token ' . $token,
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $employees = $this->collectEmployeesFromResponse($data, $baseUrl, $token, $timeout);

                    if (config('app.debug') && count($employees) > 0) {
                        Log::debug('Fingerprint device API first raw item (for mapping)', [
                            'first_raw' => $this->getFirstRawItem($data),
                        ]);
                    }

                    $employees = array_values(array_map([$this, 'normalizeEmployee'], $employees));
                } else {
                    $error = __('messages.employees.fingerprint_device_error', [
                        'status' => $response->status(),
                    ]);
                    Log::warning('Fingerprint device API error', [
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $error = __('messages.employees.fingerprint_device_connection_error', [
                    'message' => $e->getMessage(),
                ]);
                Log::error('Fingerprint device API exception', [
                    'url' => $url,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Employees/FingerprintDevice', [
            'employees' => $employees,
            'error' => $error,
        ]);
    }

    /**
     * Return JSON list of fingerprint device employees for linking (e.g. select dropdown).
     */
    public function listForSelect(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);

        $baseUrl = rtrim(config('services.fingerprint_device.base_url'), '/');
        $token = config('services.fingerprint_device.token');
        $timeout = (int) config('services.fingerprint_device.timeout', 15);

        $employees = [];
        $url = $baseUrl . '/personnel/api/employees/';

        if (empty($token) || empty($baseUrl)) {
            return response()->json(['employees' => [], 'error' => __('messages.employees.fingerprint_device_not_configured')], 200);
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $token,
                ])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $rawList = $this->collectEmployeesFromResponse($data, $baseUrl, $token, $timeout);
                $employees = array_values(array_map([$this, 'normalizeEmployee'], $rawList));
            }
        } catch (\Throwable $e) {
            Log::error('Fingerprint device API exception (listForSelect)', ['exception' => $e->getMessage()]);
            return response()->json(['employees' => [], 'error' => $e->getMessage()], 200);
        }

        return response()->json(['employees' => $employees]);
    }

    /**
     * Collect all employees from API response, following pagination (next) if present.
     *
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function collectEmployeesFromResponse(array $data, string $baseUrl, string $token, int $timeout): array
    {
        $all = [];
        $current = $data;

        do {
            $chunk = [];
            if (isset($current['results']) && is_array($current['results'])) {
                $chunk = $current['results'];
            } elseif (isset($current['data']) && is_array($current['data'])) {
                $chunk = $current['data'];
            } elseif (array_is_list($current)) {
                $chunk = $current;
            }

            foreach ($chunk as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }

            $nextUrl = $current['next'] ?? null;
            if (empty($nextUrl) || ! is_string($nextUrl)) {
                break;
            }

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $token,
                ])
                ->get($nextUrl);

            if (! $response->successful()) {
                Log::warning('Fingerprint device API pagination failed', ['url' => $nextUrl, 'status' => $response->status()]);
                break;
            }
            $current = $response->json() ?? [];
        } while (true);

        return $all;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function getFirstRawItem(array $data): ?array
    {
        $list = $data['results'] ?? $data['data'] ?? $data;
        if (! is_array($list) || empty($list)) {
            return null;
        }
        $first = reset($list);
        return is_array($first) ? $first : null;
    }

    /**
     * Normalize one employee from API to id, first_name, dept_name, position_name.
     * Tries multiple possible key names and nested structures.
     *
     * @param array<string, mixed> $emp
     * @return array{id: string, first_name: string, dept_name: string, position_name: string}
     */
    private function normalizeEmployee(array $emp): array
    {
        $id = $this->getFingerprintEmployeeId($emp);
        $firstName = $this->getString($emp, [
            'first_name', 'firstname', 'name', 'full_name', 'fullname', 'emp_name', 'employee_name',
        ]);
        $deptName = $this->getString($emp, [
            'dept_name', 'department', 'dept', 'department_name', 'dep_name',
        ]);
        if ($deptName === '' && isset($emp['department']) && is_array($emp['department'])) {
            $deptName = $this->getString($emp['department'], ['name', 'name_ar', 'name_en', 'dept_name']);
        }
        $positionName = $this->getString($emp, [
            'position_name', 'position', 'job_title', 'title', 'job_name', 'designation',
        ]);
        if ($positionName === '' && isset($emp['position']) && is_array($emp['position'])) {
            $positionName = $this->getString($emp['position'], ['name', 'name_ar', 'name_en', 'position_name']);
        }

        $empCode = $this->getString($emp, ['emp_code', 'code', 'employee_id', 'pin']);

        return [
            'id' => $id,
            'emp_code' => $empCode,
            'first_name' => $firstName,
            'dept_name' => $deptName,
            'position_name' => $positionName,
        ];
    }

    /**
     * Extract unique identifier for fingerprint device employee (for linking).
     *
     * @param array<string, mixed> $emp
     */
    private function getFingerprintEmployeeId(array $emp): string
    {
        $keys = ['id', 'emp_id', 'pin', 'emp_code', 'employee_id', 'pk', 'code', 'emp_code_id'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $emp)) {
                $v = $emp[$key];
                if (is_scalar($v)) {
                    return (string) $v;
                }
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $keys
     */
    private function getString(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($item[$key])) {
                $v = $item[$key];
                if (is_string($v)) {
                    return $v;
                }
                if (is_numeric($v)) {
                    return (string) $v;
                }
            }
        }
        return '';
    }
}
