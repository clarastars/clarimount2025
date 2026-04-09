<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmailTestController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/EmailTest', [
            'defaultToEmail' => $request->user()?->email ?? '',
            'status' => $request->session()->get('status'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'email:rfc,dns', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            Mail::to((string) $validated['to'])->send(
                new \App\Mail\TestEmail(
                    emailSubject: (string) $validated['subject'],
                    bodyText: (string) $validated['message'],
                    senderName: (string) ($request->user()?->name ?? config('app.name')),
                    sentAt: now()->toDateTimeString(),
                )
            );
        } catch (Throwable $exception) {
            Log::error('Failed to send test email.', [
                'to' => $validated['to'],
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'send' => __('messages.settings.email_test_send_failed'),
            ]);
        }

        return redirect()
            ->route('settings.email-test.index')
            ->with('status', __('messages.settings.email_test_sent_success'));
    }
}
