<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LaborLawRule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;

class LaborLawRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = LaborLawRule::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('violation_type', 'like', "%{$search}%")
                  ->orWhere('action_type', 'like', "%{$search}%")
                  ->orWhere('reason_text', 'like', "%{$search}%");
            });
        }

        // Filter by violation type
        if ($request->filled('violation_type')) {
            $query->where('violation_type', $request->violation_type);
        }

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        $rules = $query->orderBy('violation_type')
            ->orderBy('repeat_number')
            ->paginate(15)
            ->withQueryString();

        // Get unique violation types for filter
        $violationTypes = LaborLawRule::distinct()
            ->pluck('violation_type')
            ->sort()
            ->values();

        return Inertia::render('LaborLawRules/Index', [
            'rules' => $rules,
            'violationTypes' => $violationTypes,
            'filters' => $request->only(['search', 'violation_type', 'action_type']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('LaborLawRules/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'violation_type' => [
                'required',
                'string',
                'max:255',
            ],
            'min_minutes' => 'nullable|integer|min:0',
            'max_minutes' => 'nullable|integer|min:0|gte:min_minutes',
            'repeat_number' => [
                'required',
                'integer',
                'min:1',
                'max:4',
                Rule::unique('labor_law_rules')->where(function ($query) use ($request) {
                    return $query->where('violation_type', $request->violation_type);
                }),
            ],
            'action_type' => 'required|string|in:warning,deduction_percentage,deduction_days,termination',
            'action_value' => 'nullable|integer|min:0',
            'action_value_gross_days' => 'nullable|integer|min:0',
            'action_value_basic_days' => 'nullable|integer|min:0',
            'reason_text' => 'required|string',
        ]);

        LaborLawRule::create($validated);

        return redirect()->route('labor-law-rules.index')
            ->with('success', __('labor_law_rules.created_successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(LaborLawRule $laborLawRule): Response
    {
        return Inertia::render('LaborLawRules/Show', [
            'rule' => $laborLawRule,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LaborLawRule $laborLawRule): Response
    {
        return Inertia::render('LaborLawRules/Edit', [
            'rule' => $laborLawRule,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LaborLawRule $laborLawRule)
    {
        $validated = $request->validate([
            'violation_type' => [
                'required',
                'string',
                'max:255',
            ],
            'min_minutes' => 'nullable|integer|min:0',
            'max_minutes' => 'nullable|integer|min:0|gte:min_minutes',
            'repeat_number' => [
                'required',
                'integer',
                'min:1',
                'max:4',
                Rule::unique('labor_law_rules')->where(function ($query) use ($request) {
                    return $query->where('violation_type', $request->violation_type);
                })->ignore($laborLawRule->id),
            ],
            'action_type' => 'required|string|in:warning,deduction_percentage,deduction_days,termination',
            'action_value' => 'nullable|integer|min:0',
            'action_value_gross_days' => 'nullable|integer|min:0',
            'action_value_basic_days' => 'nullable|integer|min:0',
            'reason_text' => 'required|string',
        ]);

        $laborLawRule->update($validated);

        return redirect()->route('labor-law-rules.index')
            ->with('success', __('labor_law_rules.updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LaborLawRule $laborLawRule)
    {
        $laborLawRule->delete();

        return redirect()->route('labor-law-rules.index')
            ->with('success', __('labor_law_rules.deleted_successfully'));
    }
}
