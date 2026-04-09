<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Display a listing of the companies.
     */
    public function index(): Response
    {
        $companies = Company::where('owner_id', Auth::id())
            ->latest()
            ->paginate(10);

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        return Inertia::render('Companies/Create');
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'company_email' => 'required|email|unique:companies,company_email',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $validated['owner_id'] = Auth::id();
        $validated['slug'] = Str::slug($validated['name_en']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company = Company::create($validated);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Company created successfully.');
    }

    /**
     * Display the specified company.
     */
    public function show(Company $company): Response
    {
        // Check if user owns this company
        if ($company->owner_id !== Auth::id()) {
            abort(403);
        }

        // Load the company with owner and Bayzat configuration
        $company->load(['owner', 'bayzatConfig']);
        
        // Get total assets count from all locations associated with this company
        $totalAssetsCount = $company->locations()
            ->withCount('assets')
            ->get()
            ->sum('assets_count');

        return Inertia::render('Companies/Show', [
            'company' => $company,
            'totalAssetsCount' => $totalAssetsCount,
        ]);
    }

    /**
     * Show the form for editing the specified company.
     */
    public function edit(Company $company): Response
    {
        // Check if user owns this company
        if ($company->owner_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update the specified company in storage.
     */
    public function update(Request $request, Company $company)
    {
        // Check if user owns this company
        if ($company->owner_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'company_email' => 'required|email|unique:companies,company_email,' . $company->id,
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'fingerprint_report_name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        // Update slug if English name changed
        if ($validated['name_en'] !== $company->name_en) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        if ($request->boolean('remove_logo') && !empty($company->logo)) {
            Storage::disk('public')->delete($company->logo);
            $validated['logo'] = null;
        }

        if ($request->hasFile('logo')) {
            if (!empty($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        unset($validated['remove_logo']);

        $company->update($validated);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Remove the specified company from storage.
     */
    public function destroy(Company $company)
    {
        // Check if user owns this company
        if ($company->owner_id !== Auth::id()) {
            abort(403);
        }

        if (!empty($company->logo)) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    /**
     * Search companies for async selection.
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = $request->get('q', '');
        
        $companies = Company::where('owner_id', $user->id)
            ->when($query, function ($q) use ($query) {
                return $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('name_en', 'like', "%{$query}%")
                        ->orWhere('name_ar', 'like', "%{$query}%")
                        ->orWhere('company_email', 'like', "%{$query}%");
                });
            })
            ->orderBy('name_en')
            ->limit(20)
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name_en' => $company->name_en,
                    'name_ar' => $company->name_ar,
                    'company_email' => $company->company_email,
                    'display_name' => $company->name_en . ($company->name_ar ? " ({$company->name_ar})" : ''),
                ];
            });

        return response()->json($companies);
    }
}
