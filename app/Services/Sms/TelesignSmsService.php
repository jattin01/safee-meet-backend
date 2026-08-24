<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends app-generated OTPs via Telesign's SMS API.
 *
 * NOTE: Telesign only delivers the SMS here — OTP generation, caching and
 * verification stay in the calling controllers, exactly as they did with the
 * previous MSG91 integration.
 *
 * Docs: https://developer.telesign.com/enterprise/reference/sendmessage
 */
class TelesignSmsService
{
    public function sendOtp(string $phone, string $otp): bool
    {
        $customerId = config('services.telesign.customer_id');
        $apiKey = config('services.telesign.api_key');

        $phoneNumber = ltrim($phone, '+');
        $message = 'Your SAFEE verification code is ' . $otp . '. It expires in 10 minutes.';

        $resourcePath = '/v1/messaging';
        $method = 'POST';
        $params = [
            'phone_number' => $phoneNumber,
            'message' => $message,
            'message_type' => 'OTP',
        ];

        $authFields = $this->buildAuthHeader($method, $resourcePath, $customerId, $apiKey, $params);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => $authFields['authorization'],
                    'x-ts-auth-method' => $authFields['auth_method'],
                    'x-ts-date' => $authFields['date'],
                    'x-ts-nonce' => $authFields['nonce'],
                ])
                ->asForm()
                ->post('https://rest-api.telesign.com' . $resourcePath, $params);

            if ($response->successful()) {
                Log::info('OTP SMS sent successfully via Telesign', [
                    'phone' => $phone,
                    'response' => $response->json(),
                ]);

                return true;
            }

            Log::error('Failed to send OTP SMS via Telesign', [
                'phone' => $phone,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Exception while sending OTP SMS via Telesign', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Build Telesign's required HMAC-SHA256 signed Authorization header,
     * along with the x-ts-* header values the signature was computed over.
     * All of these must be sent on the actual request, or Telesign will
     * recompute a different signature and reject it.
     */
    private function buildAuthHeader(
        string $method,
        string $resourcePath,
        string $customerId,
        string $apiKey,
        array $params
    ): array {
        $authMethod = 'HMAC-SHA256';
        $nonce = (string) Str::uuid();
        $date = gmdate('D, d M Y H:i:s T');
        $contentType = 'application/x-www-form-urlencoded';

        $stringToSignBuilder = [
            $method,
            $contentType,
            '',
            "x-ts-auth-method:{$authMethod}",
            "x-ts-date:{$date}",
            "x-ts-nonce:{$nonce}",
        ];

        $stringToSignBuilder[] = http_build_query($params);
        $stringToSignBuilder[] = $resourcePath;

        $stringToSign = implode("\n", $stringToSignBuilder);

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($apiKey), true));

        return [
            'authorization' => "TSA {$customerId}:{$signature}",
            'auth_method' => $authMethod,
            'date' => $date,
            'nonce' => $nonce,
        ];
    }
}
