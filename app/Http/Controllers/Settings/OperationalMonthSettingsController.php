<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\OperationalMonthService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationalMonthSettingsController extends Controller
{
    public function edit(OperationalMonthService $operationalMonthService): Response
    {
        $settings = $operationalMonthService->getSettings();
        $range = $operationalMonthService->resolveCurrentOperationalMonthRange(Carbon::now('Asia/Riyadh'));

        return Inertia::render('settings/OperationalMonth', [
            'settings' => $settings,
            'currentState' => [
                'mode' => $settings['is_custom'] ? 'custom' : 'calendar',
                'start' => $range['start']->format('Y-m-d'),
                'end' => $range['end']->format('Y-m-d'),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, OperationalMonthService $operationalMonthService): RedirectResponse
    {
        $validated = $request->validate([
            'start_day' => ['nullable', 'integer', 'between:1,31'],
            'end_day' => ['nullable', 'integer', 'between:1,31'],
        ]);

        $startDay = $validated['start_day'] ?? null;
        $endDay = $validated['end_day'] ?? null;

        if (($startDay === null) xor ($endDay === null)) {
            return back()->withErrors([
                'boundaries' => __('messages.settings.operational_month_boundaries_required_together'),
            ]);
        }

        if ($startDay !== null && $endDay !== null && $startDay <= $endDay) {
            return back()->withErrors([
                'boundaries' => __('messages.settings.operational_month_boundaries_invalid_order'),
            ]);
        }

        $operationalMonthService->saveSettings(
            $startDay !== null ? (int) $startDay : null,
            $endDay !== null ? (int) $endDay : null
        );

        return redirect()
            ->route('settings.operational-month.edit')
            ->with('status', __('messages.settings.operational_month_saved'));
    }
}
