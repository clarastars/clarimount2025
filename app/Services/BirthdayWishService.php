<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\BirthdayWishNotification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BirthdayWishService
{
    public const EMOJIS = [
        'cake' => '🎂',
        'balloon' => '🎈',
        'party' => '🎉',
        'gift' => '🎁',
        'flowers' => '💐',
        'sparkles' => '✨',
        'heart' => '❤️',
        'clap' => '👏',
    ];

    public function __construct(
        private UpcomingBirthdaysService $upcomingBirthdaysService,
    ) {}

    /**
     * @return list<array{key: string, emoji: string}>
     */
    public static function emojiOptions(): array
    {
        $options = [];
        foreach (self::EMOJIS as $key => $emoji) {
            $options[] = [
                'key' => $key,
                'emoji' => $emoji,
            ];
        }

        return $options;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array{sent: int, skipped: int, failed: list<string>}
     */
    public function send(User $sender, array $employeeIds, string $emojiKey, ?string $message = null): array
    {
        $emojiKey = strtolower(trim($emojiKey));
        if (! array_key_exists($emojiKey, self::EMOJIS)) {
            throw ValidationException::withMessages([
                'emoji' => [__('messages.dashboard.birthday_wish_invalid_emoji')],
            ]);
        }

        $message = $this->normalizeMessage($message);

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => [__('messages.dashboard.birthday_wish_no_recipients')],
            ]);
        }

        $viewerEmployee = $sender->employee;
        $allowedIds = collect($this->upcomingBirthdaysService->forViewer($viewerEmployee))
            ->reject(static fn (array $row): bool => (bool) ($row['is_self'] ?? false))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $sent = 0;
        $skipped = 0;
        $failed = [];

        foreach ($employeeIds as $employeeId) {
            if (! in_array($employeeId, $allowedIds, true)) {
                $failed[] = __('messages.dashboard.birthday_wish_not_allowed');
                continue;
            }

            $recipientEmployee = Employee::query()
                ->with('user')
                ->find($employeeId);

            if ($recipientEmployee === null) {
                $failed[] = __('messages.dashboard.birthday_wish_not_allowed');
                continue;
            }

            $recipientUser = $recipientEmployee->user;
            if ($recipientUser === null) {
                $failed[] = __('messages.dashboard.birthday_wish_no_portal', [
                    'name' => $recipientEmployee->full_name,
                ]);
                continue;
            }

            if ($recipientUser->id === $sender->id) {
                $skipped++;
                continue;
            }

            if ($this->alreadyWishedToday($sender, $recipientUser, $emojiKey)) {
                $skipped++;
                continue;
            }

            $recipientUser->notify(new BirthdayWishNotification([
                'emoji_key' => $emojiKey,
                'emoji' => self::EMOJIS[$emojiKey],
                'message' => $message,
                'from_user_id' => $sender->id,
                'from_employee_id' => $viewerEmployee?->id,
                'from_name' => $this->senderDisplayName($sender, $viewerEmployee),
                'employee_id' => $recipientEmployee->id,
                'employee_name' => $recipientEmployee->full_name,
                'url' => route('dashboard'),
            ]));

            $sent++;
        }

        if ($sent === 0 && $skipped === 0 && $failed !== []) {
            throw ValidationException::withMessages([
                'employee_ids' => [implode(' ', array_unique($failed))],
            ]);
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => array_values(array_unique($failed)),
        ];
    }

    private function normalizeMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $message = trim(preg_replace("/[ \t]+/u", ' ', str_replace(["\r\n", "\r"], "\n", $message)) ?? '');
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        if (mb_strlen($message) > 200) {
            throw ValidationException::withMessages([
                'message' => [__('messages.dashboard.birthday_wish_message_too_long')],
            ]);
        }

        return $message;
    }

    private function alreadyWishedToday(User $sender, User $recipient, string $emojiKey): bool
    {
        $startOfDay = Carbon::now(UpcomingBirthdaysService::TZ)->startOfDay();

        return $recipient->notifications()
            ->where('type', BirthdayWishNotification::class)
            ->where('created_at', '>=', $startOfDay)
            ->where('data->from_user_id', $sender->id)
            ->where('data->emoji_key', $emojiKey)
            ->exists();
    }

    private function senderDisplayName(User $sender, ?Employee $viewerEmployee): string
    {
        if ($viewerEmployee !== null && filled($viewerEmployee->full_name)) {
            return $viewerEmployee->full_name;
        }

        return (string) ($sender->name ?: $sender->email);
    }
}
