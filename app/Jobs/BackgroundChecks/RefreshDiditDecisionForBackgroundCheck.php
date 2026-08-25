<?php

namespace App\Jobs\BackgroundChecks;

use App\Jobs\Verification\StoreDiditVerificationImages;
use App\Models\UserVerification;
use App\Services\Verification\DiditDecisionClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshDiditDecisionForBackgroundCheck implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int|string $verificationId) {}

    public function uniqueId(): string
    {
        return (string) $this->verificationId;
    }

    public function handle(DiditDecisionClient $client): void
    {
        $verification = UserVerification::find($this->verificationId);
        if (! $verification || ! $verification->didit_session_id) {
            return;
        }

        $decision = $client->retrieve($verification->didit_session_id);
        $payload = $verification->didit_payload ?? [];
        $updatedPayload = isset($payload['decision'])
            ? array_replace($payload, [
                'status' => $decision['status'] ?? $payload['status'] ?? null,
                'decision' => $decision,
            ])
            : $decision;

        if ($updatedPayload === $payload) {
            StoreDiditVerificationImages::dispatch($verification->id)->afterCommit();

            return;
        }

        $verification->forceFill([
            'didit_payload' => $updatedPayload,
            'didit_decision_status' => $decision['status']
                ?? $verification->didit_decision_status,
        ])->save();

        StoreDiditVerificationImages::dispatch($verification->id)->afterCommit();
    }
}
