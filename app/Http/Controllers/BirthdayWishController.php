<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BirthdayWishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BirthdayWishController extends Controller
{
    public function __construct(
        private BirthdayWishService $birthdayWishService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $validated = $request->validate([
            'emoji' => ['required', 'string', Rule::in(array_keys(BirthdayWishService::EMOJIS))],
            'message' => ['nullable', 'string', 'max:200'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employee_ids' => ['nullable', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $employeeIds = [];
        if (! empty($validated['employee_ids']) && is_array($validated['employee_ids'])) {
            $employeeIds = array_map('intval', $validated['employee_ids']);
        } elseif (! empty($validated['employee_id'])) {
            $employeeIds = [(int) $validated['employee_id']];
        }

        $result = $this->birthdayWishService->send(
            $user,
            $employeeIds,
            (string) $validated['emoji'],
            isset($validated['message']) ? (string) $validated['message'] : null,
        );

        $message = $result['sent'] > 0
            ? __('messages.dashboard.birthday_wish_sent', ['count' => $result['sent']])
            : __('messages.dashboard.birthday_wish_already_sent');

        return response()->json([
            'success' => $result['sent'] > 0,
            'message' => $message,
            'sent' => $result['sent'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ]);
    }

    public function hideOwnBirthday(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $employee = $user->employee;
        abort_unless($employee !== null, 403);

        $employee->setHideBirthday(true);
        $employee->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.dashboard.birthday_hidden_success'),
            'hide_birthday' => true,
        ]);
    }
}
