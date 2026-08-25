<?php

use App\Contracts\CriminalBackgroundCheckProvider;
use App\DTOs\BackgroundChecks\ProviderResult;
use App\DTOs\BackgroundChecks\VerifiedIdentityData;
use App\Exceptions\BackgroundCheckProviderException;
use App\Jobs\BackgroundChecks\EvaluateBackgroundCheckEligibility;
use App\Jobs\BackgroundChecks\RefreshDiditDecisionForBackgroundCheck;
use App\Jobs\BackgroundChecks\SubmitSearchbugBackgroundCheck;
use App\Models\Admin;
use App\Models\BackgroundCheck;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\UserVerification;
use App\Models\VerificationLevel;
use App\Services\BackgroundChecks\BackgroundCheckService;
use App\Services\BackgroundChecks\DiditVerifiedIdentityExtractor;
use App\Services\BackgroundChecks\SearchbugClient;
use App\Services\BackgroundChecks\VerificationLevelPromotionService;
use Carbon\CarbonImmutable;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'services.searchbug.enabled' => true,
        'services.searchbug.consent_version' => 'test-v1',
    ]);

    $this->seed(SubscriptionPlanSeeder::class);
    $this->seed(FeatureSeeder::class);
    VerificationLevel::create([
        'slug' => 'level_2_verified',
        'name' => 'Level 2 Verified',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

function diditPayload(string $idStatus = 'Approved', bool $withAddress = true): array
{
    return [
        'status' => 'Approved',
        'decision' => [
            'status' => 'Approved',
            'id_verifications' => [[
                'status' => $idStatus,
                'first_name' => 'Test',
                'last_name' => 'Member',
                'date_of_birth' => '1990-01-01',
                'parsed_address' => $withAddress ? [
                    'city' => 'Austin',
                    'region' => 'TX',
                    'postal_code' => '78701',
                    'country' => 'US',
                    'is_verified' => true,
                ] : [],
            ]],
        ],
    ];
}

function backgroundEligibleUser(): User
{
    $user = User::factory()->create([
        'kyc_status' => 'verified',
        'verification_level' => 'level1',
    ]);

    $plan = SubscriptionPlan::where('slug', 'premium')->firstOrFail();
    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'price' => $plan->monthly_price,
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'started_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);
    $user->update(['plan_id' => $plan->id, 'subscription_status' => 'active']);

    UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => fake()->uuid(),
        'didit_decision_status' => 'Approved',
        'didit_payload' => diditPayload(),
        'verification_level' => 1,
        'status' => 'approved',
    ]);

    return $user->fresh();
}

function consentToBackgroundCheck(User $user): UserConsent
{
    return UserConsent::create([
        'user_id' => $user->id,
        'consent_type' => UserConsent::CRIMINAL_BACKGROUND_CHECK,
        'version' => 'test-v1',
        'accepted' => true,
        'accepted_at' => now(),
    ]);
}

it('requires approved level one identity details and background-check consent', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/verification/background-status')
        ->assertOk()
        ->assertJsonPath('data.eligible', false)
        ->assertJsonPath('data.eligibilityReason', 'CONSENT_REQUIRED');

    $this->postJson('/api/v1/verification/background-consent', ['accepted' => true])
        ->assertCreated()
        ->assertJsonPath('data.accepted', true)
        ->assertJsonPath('data.version', 'test-v1');

    Queue::assertPushed(EvaluateBackgroundCheckEligibility::class);
});

it('does not queue a check when the Didit ID feature is not approved', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    $verification = UserVerification::where('user_id', $user->id)->firstOrFail();
    $verification->update(['didit_payload' => diditPayload('In Review')]);
    consentToBackgroundCheck($user);

    $result = app(BackgroundCheckService::class)->queueIfEligible($user->fresh());

    expect($result->reason)->toBe('DIDIT_ID_NOT_APPROVED');
    expect(BackgroundCheck::count())->toBe(0);
    Queue::assertNotPushed(SubmitSearchbugBackgroundCheck::class);
    Queue::assertPushed(RefreshDiditDecisionForBackgroundCheck::class);
});

it('blocks plans that do not include background verification', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    $basic = SubscriptionPlan::where('slug', 'basic')->firstOrFail();
    Subscription::where('user_id', $user->id)->where('status', 'active')->update([
        'plan_id' => $basic->id,
    ]);
    $user->update(['plan_id' => $basic->id]);
    consentToBackgroundCheck($user);

    $result = app(BackgroundCheckService::class)->queueIfEligible($user->fresh());

    expect($result->reason)->toBe('PLAN_NOT_ELIGIBLE');
    expect(BackgroundCheck::count())->toBe(0);
    Queue::assertNotPushed(SubmitSearchbugBackgroundCheck::class);
});

it('blocks missing verified address details and requests a Didit refresh', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    UserVerification::where('user_id', $user->id)->firstOrFail()->update([
        'didit_payload' => diditPayload(withAddress: false),
    ]);
    consentToBackgroundCheck($user);

    $result = app(BackgroundCheckService::class)->queueIfEligible($user->fresh());

    expect($result->reason)->toBe('VERIFIED_DETAILS_INCOMPLETE');
    expect($result->missingFields)->toContain('state');
    expect(BackgroundCheck::count())->toBe(0);
    Queue::assertPushed(RefreshDiditDecisionForBackgroundCheck::class);
});

it('creates only one provider request for repeated eligibility evaluations', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    consentToBackgroundCheck($user);

    $service = app(BackgroundCheckService::class);
    $first = $service->queueIfEligible($user->fresh());
    $second = $service->queueIfEligible($user->fresh());

    expect($first->reason)->toBe('CHECK_QUEUED');
    expect($second->reason)->toBe('CHECK_ALREADY_EXISTS');
    expect(BackgroundCheck::count())->toBe(1);
    Queue::assertPushed(SubmitSearchbugBackgroundCheck::class, 1);
});

it('allows an authenticated admin to explicitly queue an audited re-check', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    consentToBackgroundCheck($user);

    $original = app(BackgroundCheckService::class)->queueIfEligible($user->fresh())->existingCheck;
    $original->update([
        'status' => 'clear',
        'provider_status' => 'complete',
        'completed_at' => now(),
    ]);

    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'status' => true]);
    $admin = Admin::create([
        'name' => 'Background Check Admin',
        'email' => 'background-admin@example.test',
        'password' => 'test-password',
        'role_id' => $role->id,
        'status' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson("/users/{$user->id}/background-check/recheck", [
            'reason' => 'Annual compliance re-screening is required.',
        ])
        ->assertAccepted()
        ->assertJsonPath('data.queued', true)
        ->assertJsonPath('data.reason', 'RECHECK_QUEUED');

    $recheck = BackgroundCheck::whereKeyNot($original->id)->firstOrFail();
    expect($recheck->recheck_of_id)->toBe($original->id)
        ->and($recheck->requested_by_admin_id)->toBe($admin->id)
        ->and($recheck->recheck_reason)->toBe('Annual compliance re-screening is required.');
    Queue::assertPushed(SubmitSearchbugBackgroundCheck::class, 2);
});

it('stores a normalized clear result without exposing the raw response', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    consentToBackgroundCheck($user);
    $result = app(BackgroundCheckService::class)->queueIfEligible($user->fresh());
    $check = $result->existingCheck;

    app()->bind(CriminalBackgroundCheckProvider::class, fn () => new class implements CriminalBackgroundCheckProvider
    {
        public function submit(VerifiedIdentityData $identity, string $idempotencyKey): ProviderResult
        {
            return new ProviderResult('provider-reference', 'clear', 'clear', [
                'status' => 'clear',
                'sensitive_provider_detail' => 'encrypted-at-rest',
            ]);
        }

        public function retrieve(string $providerReference): ProviderResult
        {
            throw new LogicException('Not used by this test.');
        }
    });

    app(SubmitSearchbugBackgroundCheck::class, ['backgroundCheckId' => $check->id])->handle(
        app(CriminalBackgroundCheckProvider::class),
        app(DiditVerifiedIdentityExtractor::class),
        app(VerificationLevelPromotionService::class),
    );

    $check->refresh();
    expect($check->status)->toBe('clear');
    expect($check->result_classification)->toBe('clear');
    expect($check->provider_response['sensitive_provider_detail'])->toBe('encrypted-at-rest');
    $levelTwo = VerificationLevel::where('slug', 'level_2_verified')->firstOrFail();
    $user->refresh();
    expect($user->verification_level)->toBe('level2');
    expect($user->verification_level_id)->toBe($levelTwo->id);
    expect($user->trust_score)->toBe(67);

    Sanctum::actingAs($user);
    $response = $this->getJson('/api/v1/verification/background-status')->assertOk();
    expect($response->json('data.check'))->not->toHaveKey('providerResponse');
});

it('promotes a user when Searchbug completes with potential records', function () {
    Queue::fake();
    $user = backgroundEligibleUser();
    consentToBackgroundCheck($user);
    $check = app(BackgroundCheckService::class)->queueIfEligible($user->fresh())->existingCheck;

    app()->bind(CriminalBackgroundCheckProvider::class, fn () => new class implements CriminalBackgroundCheckProvider
    {
        public function submit(VerifiedIdentityData $identity, string $idempotencyKey): ProviderResult
        {
            return new ProviderResult('provider-reference', 'RESULTS', 'flagged', [
                'Status' => 'RESULTS',
                'Data' => [['potential_match' => true]],
                'Error' => null,
            ]);
        }

        public function retrieve(string $providerReference): ProviderResult
        {
            throw new LogicException('Not used by this test.');
        }
    });

    app(SubmitSearchbugBackgroundCheck::class, ['backgroundCheckId' => $check->id])->handle(
        app(CriminalBackgroundCheckProvider::class),
        app(DiditVerifiedIdentityExtractor::class),
        app(VerificationLevelPromotionService::class),
    );

    expect($check->fresh()->status)->toBe('flagged');
    $levelTwo = VerificationLevel::where('slug', 'level_2_verified')->firstOrFail();
    $user->refresh();
    expect($user->verification_level)->toBe('level2');
    expect($user->verification_level_id)->toBe($levelTwo->id);
    expect($user->trust_score)->toBe(67);
});

it('maps verified identity data through the configurable Searchbug adapter', function () {
    config([
        'services.searchbug.endpoint' => 'https://searchbug.test/criminal',
        'services.searchbug.co_code' => 'test-company',
        'services.searchbug.pass' => 'test-password',
        'services.searchbug.type' => 'api_crm',
    ]);

    Http::fake([
        'https://searchbug.test/criminal' => Http::response([
            'Status' => 'NORESULTS',
            'Data' => null,
            'Error' => null,
        ]),
    ]);

    $result = app(SearchbugClient::class)->submit(
        new VerifiedIdentityData(
            'Test',
            'Member',
            CarbonImmutable::parse('1990-01-01'),
            'Austin',
            'TX',
            '78701',
            'US',
        ),
        'idempotency-key',
    );

    expect($result->reference)->toStartWith('searchbug:');
    expect($result->providerStatus)->toBe('NORESULTS');
    expect($result->classification)->toBe('clear');

    Http::assertSent(function ($request): bool {
        $fields = collect($request->data())->mapWithKeys(
            fn (array $part): array => [$part['name'] => $part['contents']],
        );

        return $fields['CO_CODE'] === 'test-company'
            && $fields['PASS'] === 'test-password'
            && $fields['TYPE'] === 'api_crm'
            && $fields['FNAME'] === 'Test'
            && $fields['LNAME'] === 'Member'
            && $fields['DOB'] === '01/01/1990'
            && $fields['CITY'] === 'Austin'
            && $fields['STATE'] === 'TX'
            && $fields['FORMAT'] === 'JSON'
            && $fields['REF'] === 'idempotency-key';
    });
});

it('accepts unknown or missing Searchbug status when no explicit failure is returned', function () {
    config([
        'services.searchbug.endpoint' => 'https://searchbug.test/criminal',
        'services.searchbug.co_code' => 'test-company',
        'services.searchbug.pass' => 'test-password',
    ]);

    foreach ([
        ['Status' => 'UNRECOGNIZED', 'Data' => null, 'Error' => null],
        ['Data' => null, 'Error' => null],
    ] as $response) {
        Http::fake([
            'https://searchbug.test/criminal' => Http::response($response),
        ]);

        $result = app(SearchbugClient::class)->submit(
            new VerifiedIdentityData(
                'Test',
                'Member',
                CarbonImmutable::parse('1990-01-01'),
                'Austin',
                'TX',
                '78701',
                'US',
            ),
            'idempotency-key',
        );

        expect($result->classification)->toBe('verified');
    }
});

it('blocks Searchbug promotion signals on an explicit failure response', function () {
    config([
        'services.searchbug.endpoint' => 'https://searchbug.test/criminal',
        'services.searchbug.co_code' => 'test-company',
        'services.searchbug.pass' => 'test-password',
    ]);

    Http::fake([
        'https://searchbug.test/criminal' => Http::response([
            'Status' => 'REJECTED',
            'Data' => null,
            'Error' => null,
        ]),
    ]);

    expect(fn () => app(SearchbugClient::class)->submit(
        new VerifiedIdentityData(
            'Test',
            'Member',
            CarbonImmutable::parse('1990-01-01'),
            'Austin',
            'TX',
            '78701',
            'US',
        ),
        'idempotency-key',
    ))->toThrow(BackgroundCheckProviderException::class);
});
