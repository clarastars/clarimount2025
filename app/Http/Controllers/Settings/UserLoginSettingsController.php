<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserLoginSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'uses_password_login' => (bool) $user->uses_password_login,
            ]);

        return Inertia::render('settings/UserLogin', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $usesPasswordLogin = $request->boolean('uses_password_login');
        $isEnablingPasswordLogin = $usesPasswordLogin && ! $user->uses_password_login;
        $passwordProvided = trim((string) $request->input('password', '')) !== '';
        $needsPassword = $isEnablingPasswordLogin && blank($user->password);

        $validated = $request->validate([
            'uses_password_login' => ['required', 'boolean'],
            'password' => [
                Rule::requiredIf($needsPassword),
                'nullable',
                'string',
                'confirmed',
                Password::defaults(),
            ],
        ]);

        $updateData = [
            'uses_password_login' => $usesPasswordLogin,
        ];

        if ($usesPasswordLogin && $passwordProvided) {
            $updateData['password'] = (string) $validated['password'];
        }

        $user->update($updateData);

        return redirect()
            ->route('settings.user-login.index')
            ->with('status', __('messages.settings.user_login_saved'));
    }
}
