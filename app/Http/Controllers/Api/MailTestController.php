<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * TEMPORARY — for testing/debugging the SMTP email integration.
 * Sends a plain test email using the configured MAIL_* env values so you
 * can confirm SMTP host/port/credentials actually work end to end.
 *
 * Remove this controller + route once SMTP is verified working.
 */
class MailTestController extends Controller
{
    public function sendTest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $to = $request->input('to');

        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $username = config('mail.mailers.smtp.username');
        $fromAddress = config('mail.from.address');

        if ($mailer === 'smtp' && (empty($host) || empty($username) || empty($fromAddress))) {
            Log::error('SMTP mail configuration missing', [
                'host_configured' => ! empty($host),
                'username_configured' => ! empty($username),
                'from_address_configured' => ! empty($fromAddress),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'SMTP is not configured. Check MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS in .env.',
            ], 500);
        }

        try {
            Mail::raw(
                "This is a test email from SafeeMeet to verify SMTP configuration.\n\nSent at: ".now()->toDateTimeString(),
                function ($message) use ($to) {
                    $message->to($to)->subject('SafeeMeet SMTP Test Email');
                }
            );
        } catch (\Throwable $e) {
            Log::error('SMTP test email failed to send', [
                'mailer' => $mailer,
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => "Test email sent to {$to} successfully.",
            'data' => [
                'mailer' => $mailer,
                'host' => $host,
                'port' => $port,
            ],
        ], 200);
    }
}
