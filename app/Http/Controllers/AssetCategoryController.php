<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    private function accessibleCompany($user): ?Company
    {
        $company = $user->currentCompany();
        if ($company) {
            return $company;
        }

        $employeeCompanyId = Employee::query()
            ->where('user_id', $user->id)
            ->value('company_id');

        if (! $employeeCompanyId) {
            return null;
        }

        return Company::query()->find($employeeCompanyId);
    }

    /**
     * Display a listing of the asset categories.
     */
    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }

        $categories = AssetCategory::scoped(['company_id' => $company->id])
            ->withDepth()
            ->orderBy('_lft')
            ->get();

        return Inertia::render('AssetCategories/Index', [
            'categories' => $categories,
            'company' => $company,
        ]);
    }

    /**
     * Show the form for creating a new asset category.
     */
    public function create(): Response|RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }

        $parentCategories = AssetCategory::scoped(['company_id' => $company->id])
            ->withDepth()
            ->orderBy('_lft')
            ->get();

        return Inertia::render('AssetCategories/Create', [
            'company' => $company,
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Store a newly created asset category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:asset_categories,id',
        ]);

        $validated['company_id'] = $company->id;

        // If parent_id is provided, validate it belongs to the same company
        if ($validated['parent_id']) {
            $parent = AssetCategory::find($validated['parent_id']);
            if (!$parent || $parent->company_id !== $company->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent category.']);
            }
        }

        $category = new AssetCategory($validated);
        
        // Use nested set operations for proper tree structure
        if ($validated['parent_id']) {
            $parent = AssetCategory::find($validated['parent_id']);
            $category->appendToNode($parent)->save();
        } else {
            $category->saveAsRoot();
        }

        return redirect()->route('asset-categories.index')
            ->with('success', 'Asset category created successfully.');
    }

    /**
     * Display the specified asset category.
     */
    public function show(AssetCategory $assetCategory): Response|RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }
        
        // Check if user has access to this category
        if ($assetCategory->company_id !== $company->id) {
            abort(403);
        }

        // Load relationships
        $assetCategory->load(['company', 'parent', 'children.assets']);
        $assetCategory->loadCount(['assets']);

        // Get hierarchy (ancestors + current category)
        $hierarchy = $assetCategory->ancestors()->get()->push($assetCategory);
        
        // Calculate statistics
        $totalAssets = $assetCategory->descendantsAndSelf()->withCount('assets')->get()->sum('assets_count');
        $subcategoriesCount = $assetCategory->descendants()->count();
        $recentActivity = $assetCategory->assets()->where('created_at', '>=', now()->subDays(30))->count();

        $statistics = [
            'total_assets' => $totalAssets,
            'subcategories_count' => $subcategoriesCount,
            'recent_activity' => $recentActivity,
        ];

        // Get recent assets (limit to 5 most recent)
        $recentAssets = $assetCategory->assets()
            ->with(['location', 'category'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('AssetCategories/Show', [
            'category' => $assetCategory,
            'statistics' => $statistics,
            'hierarchy' => $hierarchy,
            'recentAssets' => $recentAssets,
            'company' => $company,
        ]);
    }

    /**
     * Show the form for editing the specified asset category.
     */
    public function edit(AssetCategory $assetCategory): Response|RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }
        
        // Check if user has access to this category
        if ($assetCategory->company_id !== $company->id) {
            abort(403);
        }

        // Get potential parent categories (excluding descendants of current category)
        $parentCategories = AssetCategory::scoped(['company_id' => $company->id])
            ->whereNotDescendantOf($assetCategory)
            ->where('id', '!=', $assetCategory->id)
            ->withDepth()
            ->orderBy('_lft')
            ->get();

        return Inertia::render('AssetCategories/Edit', [
            'category' => $assetCategory,
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Update the specified asset category in storage.
     */
    public function update(Request $request, AssetCategory $assetCategory): RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }
        
        // Check if user has access to this category
        if ($assetCategory->company_id !== $company->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|exists:asset_categories,id',
        ]);

        // If parent_id is provided, validate it belongs to the same company and isn't a descendant
        if ($validated['parent_id']) {
            $parent = AssetCategory::find($validated['parent_id']);
            if (!$parent || $parent->company_id !== $company->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent category.']);
            }
            
            // Prevent circular references
            if ($parent->isDescendantOf($assetCategory) || $parent->id === $assetCategory->id) {
                return back()->withErrors(['parent_id' => 'Cannot move category to its descendant or itself.']);
            }
        }

        // Update basic attributes
        $assetCategory->update($validated);

        // Handle parent change if needed
        $currentParentId = $assetCategory->parent_id;
        $newParentId = $validated['parent_id'];
        
        if ($currentParentId != $newParentId) {
            if ($newParentId) {
                $parent = AssetCategory::find($newParentId);
                $assetCategory->appendToNode($parent)->save();
            } else {
                $assetCategory->saveAsRoot();
            }
        }

        return redirect()->route('asset-categories.show', $assetCategory)
            ->with('success', 'Asset category updated successfully.');
    }

    /**
     * Remove the specified asset category from storage.
     */
    public function destroy(AssetCategory $assetCategory): RedirectResponse
    {
        $user = Auth::user();
        $company = $this->accessibleCompany($user);
        
        // If user doesn't have a company, redirect to create one
        if (!$company) {
            return redirect()->route('companies.create')
                ->with('info', 'Please create a company first to manage asset categories.');
        }
        
        // Check if user has access to this category
        if ($assetCategory->company_id !== $company->id) {
            abort(403);
        }

        // Check if category has assets
        if ($assetCategory->assets()->count() > 0) {
            return redirect()->route('asset-categories.index')
                ->with('error', 'Cannot delete category that has assets. Please move or delete all assets first.');
        }

        // Check if category has children
        if ($assetCategory->children()->count() > 0) {
            return redirect()->route('asset-categories.index')
                ->with('error', 'Cannot delete category that has sub-categories. Please move or delete all sub-categories first.');
        }

        $assetCategory->delete();

        return redirect()->route('asset-categories.index')
            ->with('success', 'Asset category deleted successfully.');
    }
}
