<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\PushNotificationService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Stripe\Customer;
use Stripe\Subscription as StripeSubscription;

function paidFlowPlan(string $slug, float $monthly, int $trialDays = 0): SubscriptionPlan
{
    return SubscriptionPlan::create([
        'name' => str($slug)->headline(),
        'slug' => $slug,
        'monthly_price' => $monthly,
        'monthly_original_price' => $monthly > 0 ? $monthly + 5 : null,
        'yearly_price' => $monthly * 12,
        'trial_days' => $trialDays,
        'features' => [],
        'sort_order' => 1,
        'is_active' => true,
        'monthly_stripe_price_id' => $monthly > 0 ? 'price_test_monthly' : null,
    ]);
}

/** A trial user who is about to pick a paid plan. */
function userOnTrial(): array
{
    $user = User::factory()->create();
    $trialPlan = paidFlowPlan('trial_plan', 0, 7);

    Sanctum::actingAs($user);
    test()->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $trialPlan->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    return [$user->fresh(), $trialPlan];
}

function signedStripeWebhook(array $payload): \Illuminate\Testing\TestResponse
{
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test');

    return test()->call('POST', '/api/v1/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

it('writes nothing locally and keeps the current plan when a paid subscribe is not paid', function () {
    // Mocked before the first request — the route caches its controller.
    $this->mock(StripeService::class, function ($mock) {
        $mock->shouldReceive('syncPlanPrice')->once();
        $mock->shouldReceive('resolvePriceId')->andReturn('price_test_monthly');
        $mock->shouldReceive('createOrGetCustomer')->andReturn(Customer::constructFrom(['id' => 'cus_test']));
        // Nothing already paid on Stripe, so a normal checkout starts.
        $mock->shouldReceive('paidUnrecordedSubscription')->once()->andReturnNull();
        // No pending checkout to resume, so a new one is created.
        $mock->shouldReceive('reusableIncompleteSubscription')->once()->andReturnNull();
        $mock->shouldReceive('cancelIncompleteSubscriptions')->once();
        $mock->shouldReceive('createSubscription')
            ->withArgs(fn ($customer, $priceId, $pm, $metadata) => $priceId === 'price_test_monthly'
                && $metadata['price'] === 14.99)
            ->andReturn(StripeSubscription::constructFrom([
                'id' => 'sub_test',
                'status' => 'incomplete',
                'latest_invoice' => ['confirmation_secret' => ['client_secret' => 'pi_test_secret_abc']],
            ]));
    });

    [$user, $trialPlan] = userOnTrial();
    $premium = paidFlowPlan('premium', 14.99);

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $premium->slug,
        'billing_cycle' => 'monthly',
        'stripe_payment_method_id' => 'pm_card_visa',
    ])->assertCreated()
        ->assertJsonPath('payment_intent_client_secret', 'pi_test_secret_abc')
        ->assertJsonPath('status', 'incomplete')
        ->assertJsonPath('price', '14.99');

    // User backs out here — nothing about the paid plan exists locally.
    expect(Subscription::where('plan_id', $premium->id)->exists())->toBeFalse()
        ->and(UserSubscription::where('plan_id', $premium->id)->exists())->toBeFalse()
        ->and(Subscription::where('user_id', $user->id)->sole()->status)->toBe('trial')
        ->and($user->fresh()->plan_id)->toBe($trialPlan->id)
        ->and($user->fresh()->subscription_status)->toBe('trial');
});

it('activates the paid plan only when invoice.paid arrives, once', function () {
    Mail::fake();
    $this->mock(PushNotificationService::class)->shouldReceive('sendToUser')->once();

    [$user, $trialPlan] = userOnTrial();
    $premium = paidFlowPlan('premium', 14.99);

    $this->mock(StripeService::class, function ($mock) use ($user, $premium) {
        $mock->shouldReceive('retrieveSubscription')->with('sub_test')->andReturn(StripeSubscription::constructFrom([
            'id' => 'sub_test',
            'customer' => 'cus_test',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $premium->id,
                'billing_cycle' => 'monthly',
                'price' => '14.99',
            ],
        ]));
        // New subscriptions are put on the plan's current Price for renewals.
        $mock->shouldReceive('resolvePriceId')->once()->andReturn('price_test_monthly');
        $mock->shouldReceive('moveSubscriptionToPrice')->once()->with('sub_test', 'price_test_monthly')->andReturn(false);
    });

    $event = [
        'id' => 'evt_test',
        'object' => 'event',
        'type' => 'invoice.paid',
        'data' => ['object' => [
            'id' => 'in_test',
            'object' => 'invoice',
            'amount_paid' => 1499,
            'currency' => 'usd',
            'parent' => ['subscription_details' => ['subscription' => 'sub_test']],
        ]],
    ];

    signedStripeWebhook($event)->assertOk();
    signedStripeWebhook($event)->assertOk(); // Stripe retry — must not duplicate

    $paid = Subscription::where('stripe_subscription_id', 'sub_test')->sole();

    expect($paid->status)->toBe('active')
        ->and((float) $paid->price)->toBe(14.99)
        ->and($paid->userSubscription->status)->toBe('active')
        ->and(Subscription::where('plan_id', $trialPlan->id)->sole()->status)->toBe('cancelled')
        ->and($user->fresh()->plan_id)->toBe($premium->id)
        ->and($user->fresh()->subscription_status)->toBe('active')
        ->and(Payment::where('stripe_invoice_id', 'in_test')->sole()->amount)->toBe(1499);
});
