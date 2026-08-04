<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Services\LeaveTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function __construct(
        private LeaveTypeService $leaveTypeService,
    ) {}

    public function index(): Response
    {
        $this->authorizeManagement();

        return Inertia::render('settings/LeaveTypes', [
            'leaveTypes' => $this->leaveTypeService->allForSettings(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();

        $validated = $this->validatePayload($request);
        $baseKey = $this->leaveTypeService->buildKeyFromName(
            (string) ($validated['name_en'] !== '' ? $validated['name_en'] : $validated['name_ar'])
        );

        LeaveType::query()->create([
            'key' => $this->nextUniqueKey($baseKey),
            'name_en' => trim((string) $validated['name_en']),
            'name_ar' => trim((string) $validated['name_ar']),
            'min_notice_days' => (int) $validated['min_notice_days'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => true,
        ]);

        return back()->with('success', __('messages.settings.leave_types_saved'));
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->authorizeManagement();

        $validated = $this->validatePayload($request);

        $leaveType->update([
            'name_en' => trim((string) $validated['name_en']),
            'name_ar' => trim((string) $validated['name_ar']),
            'min_notice_days' => (int) $validated['min_notice_days'],
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => true,
        ]);

        return back()->with('success', __('messages.settings.leave_types_updated'));
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $this->authorizeManagement();

        $leaveType->delete();

        return back()->with('success', __('messages.settings.leave_types_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'min_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    private function authorizeManagement(): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && ($user->hasRole('super-admin') || $user->can('settings.access') || $user->can('leave-types.manage')),
            403
        );
    }

    private function nextUniqueKey(string $baseKey): string
    {
        $rootBase = $baseKey !== '' ? $baseKey : 'leave-type';
        $key = $rootBase;
        $counter = 1;

        while (LeaveType::withTrashed()->where('key', $key)->exists()) {
            $counter++;
            $key = $rootBase.'-'.$counter;
        }

        return Str::limit($key, 100, '');
    }
}
