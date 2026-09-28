<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\BirthdaySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BirthdaySettingsController extends Controller
{
    public function __construct(
        private BirthdaySettingsService $birthdaySettingsService,
    ) {}

    public function edit(): Response
    {
        $companies = Company::query()
            ->orderBy('name_ar')
            ->orderBy('name_en')
            ->get(['id', 'name_ar', 'name_en'])
            ->map(static fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->getName(),
                'name_ar' => $company->name_ar,
                'name_en' => $company->name_en,
            ])
            ->values();

        return Inertia::render('settings/BirthdayWidget', [
            'settings' => $this->birthdaySettingsService->settings(),
            'scopes' => BirthdaySettingsService::SCOPES,
            'companyFilterModes' => BirthdaySettingsService::COMPANY_FILTERS,
            'companies' => $companies,
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'scope' => ['required', 'string', Rule::in(BirthdaySettingsService::SCOPES)],
            'days_ahead' => ['required', 'integer', 'min:0', 'max:365'],
            'company_filter_mode' => ['required', 'string', Rule::in(BirthdaySettingsService::COMPANY_FILTERS)],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'distinct', Rule::exists('companies', 'id')],
        ]);

        $mode = (string) $validated['company_filter_mode'];
        $companyIds = array_map('intval', $validated['company_ids'] ?? []);

        if (
            in_array($mode, [
                BirthdaySettingsService::COMPANY_FILTER_INCLUDE,
                BirthdaySettingsService::COMPANY_FILTER_EXCLUDE,
            ], true)
            && $companyIds === []
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'company_ids' => __('messages.settings.birthday_widget_companies_required'),
                ]);
        }

        $this->birthdaySettingsService->update([
            'enabled' => $request->boolean('enabled'),
            'scope' => (string) $validated['scope'],
            'days_ahead' => (int) $validated['days_ahead'],
            'company_filter_mode' => $mode,
            'company_ids' => $companyIds,
        ]);

        return redirect()
            ->route('settings.birthday-widget.edit')
            ->with('status', __('messages.settings.birthday_widget_saved'));
    }
}
