<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FlexibleAttendanceSettingsController extends Controller
{
    public function edit(): Response
    {
        $companies = Company::query()
            ->orderBy('name_ar')
            ->orderBy('name_en')
            ->get(['id', 'name_ar', 'name_en', 'settings'])
            ->map(static function (Company $company): array {
                return [
                    'id' => $company->id,
                    'name_ar' => $company->name_ar,
                    'name_en' => $company->name_en,
                    'flexible_time_enabled' => $company->flexibleTimeEnabled(),
                    'flexible_time_minutes' => max(
                        1,
                        (int) $company->getSetting(Company::SETTING_FLEXIBLE_TIME_MINUTES, 30) ?: 30
                    ),
                ];
            })
            ->values();

        return Inertia::render('settings/FlexibleAttendance', [
            'companies' => $companies,
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'companies' => ['required', 'array', 'min:1'],
            'companies.*.id' => ['required', 'integer', 'distinct', Rule::exists('companies', 'id')],
            'companies.*.flexible_time_enabled' => ['required', 'boolean'],
            'companies.*.flexible_time_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        foreach ($validated['companies'] as $index => $row) {
            if ($row['flexible_time_enabled'] && empty($row['flexible_time_minutes'])) {
                return back()->withErrors([
                    "companies.{$index}.flexible_time_minutes" => __('messages.settings.flexible_attendance_minutes_required'),
                ]);
            }
        }

        $rowsById = collect($validated['companies'])->keyBy('id');

        DB::transaction(function () use ($rowsById): void {
            $companies = Company::query()
                ->whereIn('id', $rowsById->keys()->all())
                ->get();

            foreach ($companies as $company) {
                /** @var array{id: int, flexible_time_enabled: bool, flexible_time_minutes?: int|null} $row */
                $row = $rowsById->get($company->id);
                $enabled = (bool) $row['flexible_time_enabled'];
                $settings = $company->settings ?? [];

                data_set($settings, Company::SETTING_FLEXIBLE_TIME_ENABLED, $enabled);

                if ($enabled) {
                    data_set(
                        $settings,
                        Company::SETTING_FLEXIBLE_TIME_MINUTES,
                        (int) $row['flexible_time_minutes']
                    );
                }

                $company->settings = $settings;
                $company->save();
            }
        });

        return redirect()
            ->route('settings.flexible-attendance.edit')
            ->with('status', __('messages.settings.flexible_attendance_saved'));
    }
}
