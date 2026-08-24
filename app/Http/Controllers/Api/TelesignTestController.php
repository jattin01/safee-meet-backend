<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sms\TelesignSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * TEMPORARY — for testing/debugging the Telesign SMS integration.
 * Uses the same TelesignSmsService that AuthController relies on for
 * real OTP sending, so this exercises the exact same code path/config.
 *
 * Remove this controller + route once Telesign is verified working.
 */
class TelesignTestController extends Controller
{
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phoneNumber = preg_replace('/\D/', '', $request->input('phone_number'));
        $otp = (string) random_int(100000, 999999);

        $customerId = config('services.telesign.customer_id');
        $apiKey = config('services.telesign.api_key');

        if (empty($customerId) || empty($apiKey)) {
            Log::error('Telesign OTP configuration missing', [
                'customer_id_configured' => ! empty($customerId),
                'api_key_configured' => ! empty($apiKey),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OTP service is not configured.',
            ], 500);
        }

        Log::info('Telesign OTP request initiated', [
            'phone_number' => $this->maskPhoneNumber($phoneNumber),
        ]);

        $sent = app(TelesignSmsService::class)->sendOtp($phoneNumber, $otp);

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send OTP. Check storage/logs/laravel.log for details.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => [
                'phone_number' => $this->maskPhoneNumber($phoneNumber),
                // Only returned here for testing; never expose the OTP in a real endpoint.
                'otp_used' => $otp,
            ],
        ], 200);
    }

    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 4) {
            return $phoneNumber;
        }

        return str_repeat('*', strlen($phoneNumber) - 4).substr($phoneNumber, -4);
    }
}
