<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Nationality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeReferenceDataController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/EmployeeReferenceData', [
            'nationalities' => Nationality::query()
                ->orderBy('name_en')
                ->get(),
            'countries' => Country::query()
                ->orderBy('name_en')
                ->get(),
        ]);
    }

    public function storeNationality(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:nationalities,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Nationality::create([
            'code' => strtoupper(trim((string) $validated['code'])),
            'name_en' => trim((string) $validated['name_en']),
            'name_ar' => trim((string) $validated['name_ar']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('settings.employee_reference_saved'));
    }

    public function updateNationality(Request $request, Nationality $nationality): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:nationalities,code,' . $nationality->id],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $nationality->update([
            'code' => strtoupper(trim((string) $validated['code'])),
            'name_en' => trim((string) $validated['name_en']),
            'name_ar' => trim((string) $validated['name_ar']),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', __('settings.employee_reference_updated'));
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:2', 'unique:countries,code'],
            'code_alpha3' => ['required', 'string', 'size:3', 'unique:countries,code_alpha3'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Country::create([
            'code' => strtoupper(trim((string) $validated['code'])),
            'code_alpha3' => strtoupper(trim((string) $validated['code_alpha3'])),
            'name_en' => trim((string) $validated['name_en']),
            'name_ar' => trim((string) $validated['name_ar']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('settings.employee_reference_saved'));
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:2', 'unique:countries,code,' . $country->id],
            'code_alpha3' => ['required', 'string', 'size:3', 'unique:countries,code_alpha3,' . $country->id],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $country->update([
            'code' => strtoupper(trim((string) $validated['code'])),
            'code_alpha3' => strtoupper(trim((string) $validated['code_alpha3'])),
            'name_en' => trim((string) $validated['name_en']),
            'name_ar' => trim((string) $validated['name_ar']),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', __('settings.employee_reference_updated'));
    }
}

