<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends and verifies SMS OTPs via the Telnyx Verify API.
 *
 * NOTE: Unlike Telesign, Telnyx generates the OTP itself — the calling
 * controllers only start a verification and later submit the user's code.
 *
 * Docs: https://developers.telnyx.com/api-reference/verify/trigger-sms-verification
 *       https://developers.telnyx.com/api-reference/verify/verify-verification-code-by-phone-number
 */
class TelnyxVerifyService
{
    private const BASE_URL = 'https://api.telnyx.com/v2';

    public function sendOtp(string $phone): bool
    {
        $phoneNumber = $this->toE164($phone);

        try {
            $response = Http::timeout(15)
                ->withToken(config('services.telnyx.api_key'))
                ->acceptJson()
                ->post(self::BASE_URL . '/verifications/sms', [
                    'phone_number' => $phoneNumber,
                    'verify_profile_id' => config('services.telnyx.verify_profile_id'),
                ]);

            if ($response->successful()) {
                Log::info('OTP SMS verification started via Telnyx', [
                    'phone' => $phone,
                    'verification_id' => $response->json('data.id'),
                    'status' => $response->json('data.status'),
                ]);

                return true;
            }

            Log::error('Failed to send OTP SMS via Telnyx', [
                'phone' => $phone,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Exception while sending OTP SMS via Telnyx', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    public function verifyOtp(string $phone, string $code): bool
    {
        $phoneNumber = $this->toE164($phone);

        try {
            $response = Http::timeout(15)
                ->withToken(config('services.telnyx.api_key'))
                ->acceptJson()
                ->post(self::BASE_URL . '/verifications/by_phone_number/' . rawurlencode($phoneNumber) . '/actions/verify', [
                    'code' => $code,
                    'verify_profile_id' => config('services.telnyx.verify_profile_id'),
                ]);

            $responseCode = $response->json('data.response_code');

            if ($response->successful() && $responseCode === 'accepted') {
                return true;
            }

            Log::warning('Telnyx OTP verification not accepted', [
                'phone' => $phone,
                'status' => $response->status(),
                'response_code' => $responseCode,
                'error' => $response->successful() ? null : $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Exception while verifying OTP via Telnyx', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function toE164(string $phone): string
    {
        return '+' . ltrim($phone, '+');
    }
}
