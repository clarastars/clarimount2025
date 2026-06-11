<?php

namespace App\Http\Controllers;

use App\Models\AssetTemplate;
use App\Models\AssetCategory;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssetTemplateController extends Controller
{
    private function authorizeTemplateApiAccess(): void
    {
        $user = Auth::user();

        if (
            $user->hasRole('super-admin')
            || $user->can('asset-inventory.access')
            || $user->can('employees.custody.update')
        ) {
            return;
        }

        abort(403);
    }

    /**
     * Display a listing of asset templates.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->get();
        
        $query = AssetTemplate::query()
            ->with(['assetCategory', 'createdBy']);

        // Handle search
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        // Handle category filter
        if ($categoryId = $request->get('category_id')) {
            $query->where('asset_category_id', $categoryId);
        }

        $templates = $query->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Get categories for filtering (from all companies the user owns)
        $companyIds = $companies->pluck('id');
        $categories = AssetCategory::whereIn('company_id', $companyIds)
            ->withDepth()
            ->orderBy('_lft')
            ->get();

        return Inertia::render('AssetTemplates/Index', [
            'templates' => $templates,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id']),
        ]);
    }

    /**
     * Search asset templates for async selection.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorizeTemplateApiAccess();

        $query = $request->get('q', '');
        $categoryId = $request->get('category_id');
        
        $templates = AssetTemplate::query()
            ->when($query, function ($q) use ($query) {
                return $q->search($query);
            })
            ->when($categoryId, function ($q) use ($categoryId) {
                return $q->where('asset_category_id', $categoryId);
            })
            ->with(['assetCategory'])
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'manufacturer' => $template->manufacturer,
                    'model_name' => $template->model_name,
                    'model_number' => $template->model_number,
                    'display_name' => $template->display_name,
                    'specifications' => $template->specifications,
                    'default_notes' => $template->default_notes,
                    'image_path' => $template->image_path,
                    'formatted_specifications' => $template->formatted_specifications,
                    'category_name' => $template->assetCategory?->name,
                    'usage_count' => $template->usage_count,
                    'asset_category' => $template->assetCategory ? [
                        'id' => $template->assetCategory->id,
                        'name' => $template->assetCategory->name,
                    ] : null,
                    'is_global' => true, // All templates are now global
                ];
            });

        return response()->json($templates);
    }

    /**
     * Get asset templates organized by category.
     */
    public function byCategory(Request $request): JsonResponse
    {
        $this->authorizeTemplateApiAccess();

        $templates = AssetTemplate::query()
            ->with(['assetCategory'])
            ->orderBy('usage_count', 'desc')
            ->orderBy('name')
            ->get();

        $templatesByCategory = [];
        
        foreach ($templates as $template) {
            $categoryName = $template->assetCategory?->name ?? 'Uncategorized';
            
            if (!isset($templatesByCategory[$categoryName])) {
                $templatesByCategory[$categoryName] = [];
            }
            
            $templatesByCategory[$categoryName][] = [
                'id' => $template->id,
                'name' => $template->name,
                'manufacturer' => $template->manufacturer,
                'model_name' => $template->model_name,
                'model_number' => $template->model_number,
                'display_name' => $template->display_name,
                'specifications' => $template->specifications,
                'default_notes' => $template->default_notes,
                'image_path' => $template->image_path,
                'formatted_specifications' => $template->formatted_specifications,
                'category_name' => $template->assetCategory?->name,
                'usage_count' => $template->usage_count,
                'asset_category' => $template->assetCategory ? [
                    'id' => $template->assetCategory->id,
                    'name' => $template->assetCategory->name,
                ] : null,
                'is_global' => true, // All templates are now global
            ];
        }

        // Sort categories by name
        ksort($templatesByCategory);

        return response()->json($templatesByCategory);
    }

    /**
     * Show the form for creating a new asset template.
     */
    public function create(): Response
    {
        $user = Auth::user();
        $companies = $user->ownedCompanies()->get();
        $companyIds = $companies->pluck('id');
        
        // Get categories for all companies the user owns
        $categories = collect();
        if ($companyIds->isNotEmpty()) {
            $categories = AssetCategory::whereIn('company_id', $companyIds)
                ->withDepth()
                ->orderBy('_lft')
                ->get();
        }

        return Inertia::render('AssetTemplates/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created asset template.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeTemplateApiAccess();

        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model_name' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'specifications' => 'nullable|array',
            'default_notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('asset-templates', 'public');
            $validated['image_path'] = $imagePath;
        }

        $validated['created_by_user_id'] = $user->id;

        $template = AssetTemplate::create($validated);

        // If this is an AJAX request (from assets wizard), return JSON
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'template' => $template->load(['assetCategory']),
                'message' => 'Asset template created successfully.',
            ]);
        }

        return redirect()->route('asset-templates.show', $template)
            ->with('success', 'Asset template created successfully.');
    }

    /**
     * Display the specified asset template.
     */
    public function show(AssetTemplate $assetTemplate): Response
    {
        $assetTemplate->load(['assetCategory', 'createdBy']);

        // Get all assets that use this template with their relationships
        $assets = $assetTemplate->assets()
            ->with(['location', 'company', 'assignedTo', 'assetCategory'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('AssetTemplates/Show', [
            'template' => $assetTemplate,
            'assets' => $assets,
        ]);
    }

    /**
     * Show the form for editing the specified asset template.
     */
    public function edit(AssetTemplate $assetTemplate): Response
    {
        $user = Auth::user();
        
        if (!$assetTemplate->canBeEditedBy($user)) {
            abort(403);
        }

        $companies = $user->ownedCompanies()->get();
        $companyIds = $companies->pluck('id');
        
        $categories = AssetCategory::whereIn('company_id', $companyIds)
            ->withDepth()
            ->orderBy('_lft')
            ->get();

        return Inertia::render('AssetTemplates/Edit', [
            'template' => $assetTemplate->load(['assetCategory']),
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified asset template.
     */
    public function update(Request $request, AssetTemplate $assetTemplate): RedirectResponse
    {
        $user = Auth::user();
        
        if (!$assetTemplate->canBeEditedBy($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model_name' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'specifications' => 'nullable|array',
            'default_notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'delete_image' => 'boolean',
        ]);

        // Handle image deletion
        if ($validated['delete_image'] && $assetTemplate->image_path) {
            \Storage::disk('public')->delete($assetTemplate->image_path);
            $validated['image_path'] = null;
        }

        // Handle image upload (new image)
        if ($request->hasFile('image')) {
            // Delete old image if exists (and not already deleted above)
            if ($assetTemplate->image_path && !$validated['delete_image']) {
                \Storage::disk('public')->delete($assetTemplate->image_path);
            }
            
            $imagePath = $request->file('image')->store('asset-templates', 'public');
            $validated['image_path'] = $imagePath;
        }

        // Remove delete_image from validated data as it's not a model field
        unset($validated['delete_image']);

        $assetTemplate->update($validated);

        return redirect()->route('asset-templates.show', $assetTemplate)
            ->with('success', 'Asset template updated successfully.');
    }

    /**
     * Remove the specified asset template.
     */
    public function destroy(AssetTemplate $assetTemplate): RedirectResponse
    {
        $user = Auth::user();
        
        if (!$assetTemplate->canBeEditedBy($user)) {
            abort(403);
        }

        // Delete associated image if exists
        if ($assetTemplate->image_path) {
            \Storage::disk('public')->delete($assetTemplate->image_path);
        }

        $assetTemplate->delete();

        return redirect()->route('asset-templates.index')
            ->with('success', 'Asset template deleted successfully.');
    }
}
