<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeGlobalSearchSettingsController extends Controller
{
    private const SETTING_KEY = 'employee_global_search_enabled';

    public function edit(): Response
    {
        return Inertia::render('settings/EmployeeGlobalSearch', [
            'settings' => [
                'enabled' => $this->isEnabled(),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $validated['enabled'] ? '1' : '0']
        );

        return redirect()
            ->route('settings.employee-global-search.edit')
            ->with('status', __('messages.settings.employee_global_search_saved'));
    }

    private function isEnabled(): bool
    {
        $value = SystemSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        if ($value === null) {
            return true;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}

