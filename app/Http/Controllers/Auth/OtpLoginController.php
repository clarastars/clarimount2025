<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OtpLoginController extends Controller
{
    public function __construct(
        private readonly LoginOtpService $loginOtpService,
    ) {
    }

    public function identify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $this->ensureIsNotRateLimited($email, 'identify', 5);

        if (! $this->loginOtpService->isRegisteredWorkEmail($email)) {
            RateLimiter::hit($this->throttleKey($email, 'identify'), 60);
            $request->session()->forget(['login_step', 'login_email', 'status']);

            throw ValidationException::withMessages([
                'email' => __('messages.auth.account_not_registered'),
            ]);
        }

        $user = $this->loginOtpService->resolveUserByWorkEmail($email);

        if ($user && $this->loginOtpService->usesPasswordLogin($user)) {
            RateLimiter::clear($this->throttleKey($email, 'identify'));

            return redirect()
                ->route('login')
                ->withInput(['email' => $email])
                ->with([
                    'login_step' => 'password',
                    'login_email' => $email,
                ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth.account_not_registered'),
            ]);
        }

        try {
            $this->loginOtpService->sendOtp($email);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth.otp_send_failed'),
            ]);
        }

        RateLimiter::hit($this->throttleKey($email, 'identify'), 60);

        return redirect()
            ->route('login')
            ->withInput(['email' => $email])
            ->with([
                'login_step' => 'otp',
                'login_email' => $email,
                'status' => __('messages.auth.otp_sent'),
            ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp' => ['required', 'string', 'digits:4'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $this->ensureIsNotRateLimited($email, 'verify', 10);

        $user = $this->loginOtpService->verifyOtp($email, (string) $validated['otp']);

        if (! $user) {
            RateLimiter::hit($this->throttleKey($email, 'verify'), 60);

            throw ValidationException::withMessages([
                'otp' => __('messages.auth.otp_invalid'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($email, 'verify'));
        RateLimiter::clear($this->throttleKey($email, 'identify'));

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $this->ensureIsNotRateLimited($email, 'resend', 3);

        $user = $this->loginOtpService->resolveUserByWorkEmail($email);

        if (! $user || $this->loginOtpService->usesPasswordLogin($user)) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth.account_not_registered'),
            ]);
        }

        try {
            $this->loginOtpService->sendOtp($email);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'email' => __('messages.auth.otp_send_failed'),
            ]);
        }

        RateLimiter::hit($this->throttleKey($email, 'resend'), 60);

        return redirect()
            ->route('login')
            ->withInput(['email' => $email])
            ->with([
                'login_step' => 'otp',
                'login_email' => $email,
                'status' => __('messages.auth.otp_sent'),
            ]);
    }

    private function ensureIsNotRateLimited(string $email, string $action, int $maxAttempts): void
    {
        $key = $this->throttleKey($email, $action);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('messages.auth.otp_throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(string $email, string $action): string
    {
        return Str::transliterate(Str::lower($email) . '|' . $action . '|' . request()->ip());
    }
}
