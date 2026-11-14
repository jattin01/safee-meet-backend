<?php

use App\Jobs\Sos\SendSosAlertSms;
use App\Models\EmergencyContact;
use App\Models\Incident;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'app.name' => 'SafeeMeet',
        'queue.default' => 'sync',
        'services.telnyx.api_key' => 'test-telnyx-key',
        'services.telnyx.from_number' => '+15550000000',
    ]);
});

function addEmergencyContactFor(User $user, string $name, string $phone): EmergencyContact
{
    return EmergencyContact::create([
        'user_id' => $user->id,
        'full_name' => $name,
        'relationship' => 'Friend',
        'phone_number' => $phone,
    ]);
}

function sentSmsTexts(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), 'api.telnyx.com/v2/messages'))
        ->map(fn (array $pair) => ['to' => $pair[0]['to'], 'text' => $pair[0]['text']])
        ->values()
        ->all();
}

it('texts every emergency contact with the user name and meeting details on SOS', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-1']])]);

    $akash = User::factory()->create(['name' => 'Akash Verma']);
    $guest = User::factory()->create(['name' => 'Rohit Singh']);
    addEmergencyContactFor($akash, 'Priya', '+919876543210');
    addEmergencyContactFor($akash, 'Ravi', '+919999999999');
    $meeting = Meeting::create([
        'reference' => 'SM-7821',
        'host_user_id' => $akash->id,
        'guest_user_id' => $guest->id,
        'meeting_date' => '2026-11-20',
        'meeting_time' => '16:30:00',
        'location' => 'Central Cafe',
    ]);
    Sanctum::actingAs($akash);

    $this->postJson('/api/v1/sos/trigger', [
        'meeting_id' => $meeting->id,
        'latitude' => 28.61,
        'longitude' => 77.20,
    ])->assertCreated()
        ->assertJsonPath('contactsNotified', 2);

    $sms = sentSmsTexts();
    expect($sms)->toHaveCount(2)
        ->and(array_column($sms, 'to'))->toBe(['+919876543210', '+919999999999'])
        ->and($sms[0]['text'])->toBe(
            'SOS ALERT: Akash Verma has triggered an emergency SOS on SafeeMeet. '
            .'Meeting SM-7821 with Rohit Singh at Central Cafe on 20 Nov 2026 16:30. '
            .'Please contact them immediately.'
        );

    $contacts = Incident::sole()->emergency_contacts_notified;
    expect(array_column($contacts, 'sms_status'))->toBe(['sent', 'sent']);
});

it('sends the alert without meeting details when SOS is not tied to a meeting', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-1']])]);

    $user = User::factory()->create(['name' => 'Akash']);
    addEmergencyContactFor($user, 'Priya', '+919876543210');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sos/trigger', ['latitude' => 28.61, 'longitude' => 77.20])
        ->assertCreated();

    expect(sentSmsTexts()[0]['text'])->toBe(
        'SOS ALERT: Akash has triggered an emergency SOS on SafeeMeet. Please contact them immediately.'
    );
});

it('records a failed SMS without breaking the SOS request', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['errors' => [['detail' => 'blocked']]], 422)]);

    $user = User::factory()->create();
    addEmergencyContactFor($user, 'Priya', '+919876543210');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sos/trigger', ['latitude' => 1, 'longitude' => 1])
        ->assertCreated();

    expect(Incident::sole()->emergency_contacts_notified[0]['sms_status'])->toBe('failed');
});

it('only re-sends to contacts that have not received the alert on retry', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-2']])]);

    $user = User::factory()->create();
    $incident = Incident::create([
        'reporter_user_id' => $user->id,
        'type' => 'sos',
        'latitude' => 1,
        'longitude' => 1,
        'status' => 'open',
        'emergency_contacts_notified' => [
            ['full_name' => 'Priya', 'phone_number' => '+919876543210', 'sms_status' => 'sent'],
            ['full_name' => 'Ravi', 'phone_number' => '+919999999999', 'sms_status' => 'failed'],
        ],
    ]);

    app()->call([new SendSosAlertSms($incident->id), 'handle']);

    expect(array_column(sentSmsTexts(), 'to'))->toBe(['+919999999999'])
        ->and(array_column($incident->fresh()->emergency_contacts_notified, 'sms_status'))->toBe(['sent', 'sent']);
});

it('falls back to the display name, then a generic name, in SMS text', function () {
    // Some sign-up flows only fill display_name (a column the test schema
    // does not have), so this checks the fallback on unsaved models.
    expect(SendSosAlertSms::personName(new User(['name' => 'Akash Verma'])))->toBe('Akash Verma')
        ->and(SendSosAlertSms::personName(new User(['display_name' => 'Akash'])))->toBe('Akash')
        ->and(SendSosAlertSms::personName(new User))->toBe('A SafeeMeet user')
        ->and(SendSosAlertSms::personName(null))->toBe('A SafeeMeet user');
});

it('adds the reporter country code to an old contact saved without one', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-4']])]);

    $user = User::factory()->create(['phone' => '+919812345678']);
    addEmergencyContactFor($user, 'Priya', '98765 43210');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sos/trigger', ['latitude' => 1, 'longitude' => 1])->assertCreated();

    expect(array_column(sentSmsTexts(), 'to'))->toBe(['+919876543210'])
        ->and(Incident::sole()->emergency_contacts_notified[0]['sms_status'])->toBe('sent');
});

it('marks a contact failed without calling Telnyx when the number cannot be resolved', function () {
    Http::fake();

    $user = User::factory()->create(['phone' => null]);
    addEmergencyContactFor($user, 'Priya', '12345');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sos/trigger', ['latitude' => 1, 'longitude' => 1])->assertCreated();

    expect(Incident::sole()->emergency_contacts_notified[0]['sms_status'])->toBe('failed');
    Http::assertNothingSent();
});

it('keeps the typed number but texts the contact in international format', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-5']])]);

    $user = User::factory()->create(['phone' => '+919812345678']);
    Sanctum::actingAs($user);

    // The API response is unchanged for the app: the number as typed.
    $this->postJson('/api/v1/emergency-contact', [
        'full_name' => 'Priya',
        'relationship' => 'Sister',
        'phone_number' => '098765 43210',
    ])->assertOk()
        ->assertJsonPath('data.phone_number', '098765 43210');

    // Same person typed differently is still a duplicate.
    $this->postJson('/api/v1/emergency-contact', [
        'full_name' => 'Priya',
        'relationship' => 'Sister',
        'phone_number' => '+91 98765 43210',
    ])->assertStatus(409);

    Http::assertSent(fn (HttpRequest $request) => $request['to'] === '+919876543210');
});

it('texts a complete number from another country as typed', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-6']])]);

    $user = User::factory()->create(['phone' => '+919812345678']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/emergency-contact', [
        'full_name' => 'Tom',
        'relationship' => 'Friend',
        'phone_number' => '+44 7911 123456',
    ])->assertOk();

    Http::assertSent(fn (HttpRequest $request) => $request['to'] === '+447911123456');
});

it('uses the configured default country when the user has no registered phone', function (string $country, string $expected) {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-7']])]);
    config(['services.sms.default_country' => $country]);

    $user = User::factory()->create(['phone' => null]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/emergency-contact', [
        'full_name' => 'Priya',
        'relationship' => 'Sister',
        'phone_number' => '9876543210',
    ])->assertOk();

    Http::assertSent(fn (HttpRequest $request) => $request['to'] === $expected);
})->with([
    'india' => ['IN', '+919876543210'],
    'us' => ['US', '+19876543210'],
]);

it('still saves a value that is not a phone number, without texting it', function () {
    Http::fake();

    $user = User::factory()->create(['phone' => '+919812345678']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/emergency-contact', [
        'full_name' => 'Tom',
        'relationship' => 'Friend',
        'phone_number' => '12345',
    ])->assertOk()
        ->assertJsonPath('status', true);

    expect(EmergencyContact::count())->toBe(1);
    Http::assertNothingSent();
});

it('tells the user when there are no emergency contacts to notify', function () {
    Http::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sos/trigger', ['latitude' => 1, 'longitude' => 1])
        ->assertCreated()
        ->assertJsonPath('contactsNotified', 0)
        ->assertJsonPath('message', 'Emergency SOS activated. You have no emergency contacts to notify.');

    Http::assertNothingSent();
});

it('texts a new emergency contact that they were added, using the user name', function () {
    Http::fake(['api.telnyx.com/*' => Http::response(['data' => ['id' => 'msg-3']])]);

    $user = User::factory()->create(['name' => 'Akash']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/emergency-contact', [
        'full_name' => 'Priya',
        'relationship' => 'Sister',
        'phone_number' => '+919876543210',
    ])->assertOk();

    Http::assertSent(fn (HttpRequest $request) => $request['to'] === '+919876543210'
        && str_contains($request['text'], 'Akash has added you as their emergency/trusted contact'));
});
