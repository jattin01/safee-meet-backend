<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends free-text SMS (alerts/notifications) via the Telnyx Messaging API.
 *
 * NOTE: OTPs go through TelnyxVerifyService — the Verify API only sends its
 * own templated code, so any custom text must use this service instead.
 *
 * Docs: https://developers.telnyx.com/api-reference/messages/send-a-message
 */
class TelnyxSmsService
{
    private const BASE_URL = 'https://api.telnyx.com/v2';

    public function send(string $phone, string $text): bool
    {
        $phoneNumber = $this->toE164($phone);

        $payload = [
            'to' => $phoneNumber,
            'text' => $text,
        ];

        // A messaging profile with a number pool can pick the sender itself;
        // otherwise a specific Telnyx number (or alphanumeric ID) is required.
        if ($from = config('services.telnyx.from_number')) {
            $payload['from'] = $from;
        }
        if ($profileId = config('services.telnyx.messaging_profile_id')) {
            $payload['messaging_profile_id'] = $profileId;
        }

        try {
            $response = Http::timeout(15)
                ->withToken(config('services.telnyx.api_key'))
                ->acceptJson()
                ->post(self::BASE_URL . '/messages', $payload);

            if ($response->successful()) {
                Log::info('SMS sent via Telnyx', [
                    'phone' => $phone,
                    'message_id' => $response->json('data.id'),
                ]);

                return true;
            }

            Log::error('Failed to send SMS via Telnyx', [
                'phone' => $phone,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Exception while sending SMS via Telnyx', [
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
