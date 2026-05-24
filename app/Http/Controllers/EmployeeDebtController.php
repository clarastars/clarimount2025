<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\EmployeeDebt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeDebtController extends Controller
{
    use AuthorizesEmployeeAccess;

    /**
     * Store a newly created debt for an employee.
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);
        $this->abortUnlessCanAccessEmployee($user, $employee);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'debt_type' => 'nullable|string|max:255',
        ]);

        $employee->debts()->create($validated);

        return back()->with('success', __('messages.debts.debt_added_successfully'));
    }

    /**
     * Update the specified debt.
     */
    public function update(Request $request, Employee $employee, EmployeeDebt $debt): RedirectResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);
        $this->abortUnlessCanAccessEmployee($user, $employee);

        if ($debt->employee_id !== $employee->id) {
            abort(404, 'Debt not found for this employee.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'debt_type' => 'nullable|string|max:255',
        ]);

        $debt->update($validated);

        return back()->with('success', __('messages.debts.debt_updated_successfully'));
    }

    /**
     * Remove the specified debt.
     */
    public function destroy(Employee $employee, EmployeeDebt $debt): RedirectResponse
    {
        $user = Auth::user();
        $this->abortUnlessCanManageEmployees($user);
        $this->abortUnlessCanAccessEmployee($user, $employee);

        if ($debt->employee_id !== $employee->id) {
            abort(404, 'Debt not found for this employee.');
        }

        $debt->delete();

        return back()->with('success', __('messages.debts.debt_deleted_successfully'));
    }
}
