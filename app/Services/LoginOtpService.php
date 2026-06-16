<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class LoginOtpService
{
    private const OTP_LENGTH = 4;

    private const OTP_TTL_MINUTES = 10;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(
        private readonly EmployeePortalUserService $employeePortalUserService,
    ) {
    }

    public function isRegisteredWorkEmail(string $email): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail === '') {
            return false;
        }

        if (User::query()->where('email', $normalizedEmail)->exists()) {
            return true;
        }

        return Employee::query()
            ->whereNotNull('work_email')
            ->where('work_email', '!=', '')
            ->whereRaw('LOWER(TRIM(work_email)) = ?', [$normalizedEmail])
            ->exists();
    }

    public function resolveUserByWorkEmail(string $email): ?User
    {
        $normalizedEmail = $this->normalizeEmail($email);

        $user = User::query()->where('email', $normalizedEmail)->first();
        if ($user) {
            return $user;
        }

        $employee = Employee::query()
            ->where('work_email', $normalizedEmail)
            ->first();

        if (! $employee) {
            return null;
        }

        return $this->employeePortalUserService->createOrSyncPortalUser($employee);
    }

    public function usesPasswordLogin(User $user): bool
    {
        return (bool) $user->uses_password_login;
    }

    /**
     * @return array{sent: bool}
     */
    public function sendOtp(string $email): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $user = $this->resolveUserByWorkEmail($normalizedEmail);

        if (! $user || $this->usesPasswordLogin($user)) {
            return ['sent' => false];
        }

        $otp = $this->generateOtp();

        Cache::put(
            $this->cacheKey($normalizedEmail),
            [
                'otp_hash' => Hash::make($otp),
                'user_id' => $user->id,
                'attempts' => 0,
            ],
            now()->addMinutes(self::OTP_TTL_MINUTES),
        );

        try {
            Mail::to($normalizedEmail)->send(new LoginOtpMail(
                otp: $otp,
                userName: $user->name,
                expiresInMinutes: self::OTP_TTL_MINUTES,
                locale: in_array($user->language, ['ar', 'en'], true) ? $user->language : 'ar',
            ));
        } catch (Throwable $exception) {
            Cache::forget($this->cacheKey($normalizedEmail));

            Log::error('Failed to send login OTP email.', [
                'email' => $normalizedEmail,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return ['sent' => true];
    }

    public function verifyOtp(string $email, string $otp): ?User
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $cacheKey = $this->cacheKey($normalizedEmail);
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['otp_hash'], $payload['user_id'])) {
            return null;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($cacheKey);

            return null;
        }

        if (! Hash::check($otp, (string) $payload['otp_hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($cacheKey, $payload, now()->addMinutes(self::OTP_TTL_MINUTES));

            return null;
        }

        Cache::forget($cacheKey);

        return User::query()->find((int) $payload['user_id']);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 10 ** self::OTP_LENGTH - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function cacheKey(string $normalizedEmail): string
    {
        return 'login_otp:' . hash('sha256', $normalizedEmail);
    }
}
