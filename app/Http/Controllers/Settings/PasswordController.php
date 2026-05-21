<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Password', [
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], $this->validationMessages());

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', __('messages.password.updated'));
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'current_password.required' => __('messages.validation.required'),
            'current_password.current_password' => __('messages.validation.current_password'),
            'password.required' => __('messages.validation.required'),
            'password.confirmed' => __('messages.validation.confirmed'),
            'password.min' => __('messages.validation.min', ['min' => 8]),
            'password.letters' => __('messages.validation.letters'),
            'password.mixed' => __('messages.validation.mixed'),
            'password.numbers' => __('messages.validation.numbers'),
            'password.symbols' => __('messages.validation.symbols'),
            'password.uncompromised' => __('messages.validation.uncompromised'),
        ];
    }
}
