<?php

namespace App\Jobs\Sos;

use App\Models\Incident;
use App\Models\User;
use App\Services\Sms\TelnyxSmsService;
use App\Support\Phone\PhoneNumberNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Texts every emergency contact saved on an SOS incident. Each contact's
 * result is recorded on the incident, and a retry only re-sends to the
 * contacts that have not received the alert yet.
 */
class SendSosAlertSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> SOS is urgent, so retries are short. */
    public array $backoff = [10, 30];

    public function __construct(public readonly int|string $incidentId) {}

    public function handle(TelnyxSmsService $sms): void
    {
        $incident = Incident::with(['reporter', 'meeting.host', 'meeting.guest'])->find($this->incidentId);
        if (! $incident || $incident->type !== 'sos') {
            return;
        }

        $contacts = $incident->emergency_contacts_notified ?? [];
        if ($contacts === []) {
            return;
        }

        $text = self::message($incident);
        $anyFailed = false;

        foreach ($contacts as $index => $contact) {
            if (($contact['sms_status'] ?? null) === 'sent') {
                continue;
            }

            // Contacts saved before numbers were normalized may lack a
            // country code; resolve it from the reporter's own phone.
            $phone = PhoneNumberNormalizer::forSms(
                (string) ($contact['phone_number'] ?? ''),
                $incident->reporter?->phone,
            );
            $sent = $phone !== null && $sms->send($phone, $text);

            $contacts[$index]['sms_status'] = $sent ? 'sent' : 'failed';
            if ($sent) {
                $contacts[$index]['sms_sent_at'] = now()->toIso8601String();
            }
            $anyFailed = $anyFailed || ! $sent;
        }

        $incident->forceFill(['emergency_contacts_notified' => $contacts])->save();

        // The sync driver runs inside the SOS request and cannot retry later;
        // the failure is already recorded per contact, so never fail the SOS.
        if ($anyFailed && config('queue.default') !== 'sync') {
            throw new RuntimeException('SOS SMS could not be delivered to every emergency contact.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('SOS SMS alerts exhausted all attempts', [
            'incident_id' => $this->incidentId,
            'error' => $exception?->getMessage(),
        ]);
    }

    public static function message(Incident $incident): string
    {
        $reporter = $incident->reporter;
        $name = self::personName($reporter);
        $appName = config('app.name', 'SafeeMeet');

        $text = "SOS ALERT: {$name} has triggered an emergency SOS on {$appName}.";

        if ($meeting = $incident->meeting) {
            $otherParty = (int) $meeting->host_user_id === (int) $incident->reporter_user_id
                ? $meeting->guest
                : $meeting->host;

            $details = array_filter([
                $meeting->reference ? "Meeting {$meeting->reference}" : 'Meeting',
                $otherParty ? 'with '.self::personName($otherParty) : null,
                $meeting->location ? "at {$meeting->location}" : null,
                self::meetingWhen($meeting->meeting_date, $meeting->meeting_time),
            ]);

            $text .= ' '.implode(' ', $details).'.';
        }

        return $text.' Please contact them immediately.';
    }

    public static function personName(?User $user): string
    {
        $name = trim((string) ($user?->name ?: $user?->display_name ?: ''));

        return $name !== '' ? $name : 'A SafeeMeet user';
    }

    private static function meetingWhen(mixed $date, mixed $time): ?string
    {
        $parts = array_filter([
            $date ? 'on '.(is_object($date) ? $date->format('d M Y') : (string) $date) : null,
            $time ? substr((string) $time, 0, 5) : null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
