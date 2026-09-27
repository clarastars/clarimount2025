<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AdvanceEntitlementTier;
use App\Services\AdvanceEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvanceEntitlementTiersController extends Controller
{
    public function __construct(
        private AdvanceEntitlementService $entitlementService,
    ) {}

    public function index(): Response
    {
        $this->authorizeManagement();

        return Inertia::render('settings/AdvanceEntitlementTiers', [
            'tiers' => $this->entitlementService->allForSettings(),
            'status' => session('status') ?? session('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();

        $validated = $this->validatePayload($request);
        $maxMonths = $this->nullableInt($validated['max_months'] ?? null);

        $this->entitlementService->assertNoOverlap(
            (int) $validated['min_months'],
            $maxMonths,
        );

        AdvanceEntitlementTier::query()->create([
            'min_months' => (int) $validated['min_months'],
            'max_months' => $maxMonths,
            'max_amount' => round((float) $validated['max_amount'], 2),
            'max_installments' => (int) $validated['max_installments'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => true,
        ]);

        return back()->with('success', __('messages.settings.advance_entitlement_tiers_saved'));
    }

    public function update(Request $request, AdvanceEntitlementTier $advanceEntitlementTier): RedirectResponse
    {
        $this->authorizeManagement();

        $validated = $this->validatePayload($request);
        $maxMonths = $this->nullableInt($validated['max_months'] ?? null);

        $this->entitlementService->assertNoOverlap(
            (int) $validated['min_months'],
            $maxMonths,
            $advanceEntitlementTier->id,
        );

        $advanceEntitlementTier->update([
            'min_months' => (int) $validated['min_months'],
            'max_months' => $maxMonths,
            'max_amount' => round((float) $validated['max_amount'], 2),
            'max_installments' => (int) $validated['max_installments'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', __('messages.settings.advance_entitlement_tiers_updated'));
    }

    public function destroy(AdvanceEntitlementTier $advanceEntitlementTier): RedirectResponse
    {
        $this->authorizeManagement();

        $advanceEntitlementTier->delete();

        return back()->with('success', __('messages.settings.advance_entitlement_tiers_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'min_months' => ['required', 'integer', 'min:0', 'max:600'],
            'max_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'max_amount' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'max_installments' => ['required', 'integer', 'min:1', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function authorizeManagement(): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && ($user->hasRole('super-admin') || $user->can('settings.access')),
            403
        );
    }
}
