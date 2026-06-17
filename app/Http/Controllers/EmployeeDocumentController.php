<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EmployeeDocumentController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private readonly EmployeeDocumentService $employeeDocumentService,
    ) {
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);
        $this->abortUnlessCanAccessEmployee($user, $employee);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(EmployeeDocument::types())],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx'],
        ]);

        $document = $this->employeeDocumentService->store(
            $employee,
            (string) $validated['type'],
            $validated['file'],
            $user?->id,
        );

        return response()->json([
            'document' => $this->employeeDocumentService->toUiArray($document),
        ]);
    }

    public function destroy(Employee $employee, string $type): JsonResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);
        $this->abortUnlessCanAccessEmployee($user, $employee);

        if (! in_array($type, EmployeeDocument::types(), true)) {
            abort(404);
        }

        $document = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->first();

        if ($document) {
            $this->employeeDocumentService->delete($document);
        }

        return response()->json(['success' => true]);
    }
}
