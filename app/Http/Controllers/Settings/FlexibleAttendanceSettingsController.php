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
                $legacy = max(
                    0,
                    (int) $company->getSetting(Company::SETTING_FLEXIBLE_TIME_MINUTES, 30) ?: 30
                );

                $beforeRaw = $company->getSetting(Company::SETTING_FLEXIBLE_TIME_BEFORE_MINUTES, null);
                $afterRaw = $company->getSetting(Company::SETTING_FLEXIBLE_TIME_AFTER_MINUTES, null);

                return [
                    'id' => $company->id,
                    'name_ar' => $company->name_ar,
                    'name_en' => $company->name_en,
                    'flexible_time_enabled' => $company->flexibleTimeEnabled(),
                    'flexible_time_before_minutes' => $beforeRaw !== null && $beforeRaw !== ''
                        ? max(0, (int) $beforeRaw)
                        : $legacy,
                    'flexible_time_after_minutes' => $afterRaw !== null && $afterRaw !== ''
                        ? max(0, (int) $afterRaw)
                        : $legacy,
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
            'companies.*.flexible_time_before_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'companies.*.flexible_time_after_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
        ]);

        foreach ($validated['companies'] as $index => $row) {
            if (! $row['flexible_time_enabled']) {
                continue;
            }

            if (! array_key_exists('flexible_time_before_minutes', $row) || $row['flexible_time_before_minutes'] === null) {
                return back()->withErrors([
                    "companies.{$index}.flexible_time_before_minutes" => __('messages.settings.flexible_attendance_before_required'),
                ]);
            }

            if (! array_key_exists('flexible_time_after_minutes', $row) || $row['flexible_time_after_minutes'] === null) {
                return back()->withErrors([
                    "companies.{$index}.flexible_time_after_minutes" => __('messages.settings.flexible_attendance_after_required'),
                ]);
            }
        }

        $rowsById = collect($validated['companies'])->keyBy('id');

        DB::transaction(function () use ($rowsById): void {
            $companies = Company::query()
                ->whereIn('id', $rowsById->keys()->all())
                ->get();

            foreach ($companies as $company) {
                /** @var array{id: int, flexible_time_enabled: bool, flexible_time_before_minutes?: int|null, flexible_time_after_minutes?: int|null} $row */
                $row = $rowsById->get($company->id);
                $enabled = (bool) $row['flexible_time_enabled'];
                $settings = $company->settings ?? [];

                data_set($settings, Company::SETTING_FLEXIBLE_TIME_ENABLED, $enabled);

                if ($enabled) {
                    $before = max(0, (int) $row['flexible_time_before_minutes']);
                    $after = max(0, (int) $row['flexible_time_after_minutes']);

                    data_set($settings, Company::SETTING_FLEXIBLE_TIME_BEFORE_MINUTES, $before);
                    data_set($settings, Company::SETTING_FLEXIBLE_TIME_AFTER_MINUTES, $after);
                    // Keep legacy key in sync with the after-bound for older readers.
                    data_set($settings, Company::SETTING_FLEXIBLE_TIME_MINUTES, $after);
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
