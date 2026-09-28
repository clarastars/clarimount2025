<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
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
        return Inertia::render('settings/BirthdayWidget', [
            'settings' => $this->birthdaySettingsService->settings(),
            'scopes' => BirthdaySettingsService::SCOPES,
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'scope' => ['required', 'string', Rule::in(BirthdaySettingsService::SCOPES)],
            'days_ahead' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $this->birthdaySettingsService->update([
            'enabled' => $request->boolean('enabled'),
            'scope' => (string) $validated['scope'],
            'days_ahead' => (int) $validated['days_ahead'],
        ]);

        return redirect()
            ->route('settings.birthday-widget.edit')
            ->with('status', __('messages.settings.birthday_widget_saved'));
    }
}
