# Subscription Flow (Current Behaviour)

This document describes how subscriptions currently work in SafeeMeet, end to end: from signup to trial expiry, paid purchase, renewal, failure and cancellation.

---

## 1. Key pieces

| Piece | File | Role |
|---|---|---|
| Plan catalog | `subscription_plans` table, seeded by `database/seeders/SubscriptionPlanSeeder.php` | Plans, prices, trial days, Stripe price ids, account type |
| Subscription API | `app/Http/Controllers/Api/SubscriptionController.php` | plans, comparison, current, subscribe, cancel |
| Subscription service | `app/Services/SubscriptionService.php` | `startFreeTrial()` and `activate()` |
| Stripe wrapper | `app/Services/StripeService.php` | Customers, subscriptions, price sync, cancel |
| Stripe webhook | `app/Http/Controllers/Api/StripeWebhookController.php` | Activates paid plans, renewals, failures, deletions |
| Trial expiry job | `app/Console/Commands/ProcessSubscriptionTrials.php` (runs daily) | Expires ended trials |
| Entitlements | `app/Services/PlanEntitlements.php` | Decides whether the user has an active plan |

### Price columns

Each plan has two prices per billing cycle:

| Column | Meaning |
|---|---|
| `monthly_price` / `yearly_price` | The **actual (discounted) price**. This is what the user is charged, what the Stripe Price is synced to, and what coupons are applied to. |
| `monthly_original_price` / `yearly_original_price` | Optional **flat price (MRP)**, shown struck-through next to the actual price. `null` means no discount is shown. Admin validation requires it to be greater than or equal to the actual price. |
| `monthly_discount_percent` / `yearly_discount_percent` | Not stored. Calculated by the `SubscriptionPlan` model and added to every API response for the "XX% OFF" badge. `null` when there is no original price or it is not higher than the actual price. |

So the final amount the user pays is: **actual (discounted) price - coupon discount**. The original price is display-only and never charged.

### Plan catalog (live database, as of 2026-11-11)

| sort_order | Slug | Account type | Monthly (actual) | Monthly (original) | Yearly (actual) | Yearly (original) | Discount | Trial days |
|---|---|---|---|---|---|---|---|---|
| 5 | `100days_free_trial` | normal | 0.99 | - | 11.88 | - | - | 100 |
| 6 | `basic_limited` | normal | 4.99 | - | 59.88 | - | - | - |
| 7 | `basic_unlimited` | normal | 9.99 | - | 119.88 | - | - | - |
| 8 | `premium` | normal | **14.99** | ~~19.99~~ | **179.99** | ~~239.88~~ | 25% | - |
| 9 | `professionalbusinesses` | employer | **24.99** | ~~29.99~~ | **299.87** | ~~359.88~~ | 17% | - |

All five plans are active. Each plan includes everything in the plan below it. Prices are managed by admins from the admin panel (`SubscriptionsController`), so these values can change.

> The seeder (`SubscriptionPlanSeeder.php`) has older values and slugs (`free_trial`, `basic`, `professional`, no original prices). The live database no longer matches it.

### Tables involved

- `subscriptions`: one row per subscription the user has had (history is kept, old rows get `cancelled`).
- `user_subscriptions`: a frozen snapshot of the plan's features at purchase time. Its status, `renews_at` and `cancelled_at` are kept in sync with the parent `subscriptions` row by the `Subscription` model's `updated` hook.
- `payments`: one row per Stripe invoice (succeeded or failed).
- `coupon_redemptions`: one row per coupon use.
- `users`: `plan_id`, `subscription_status` and `stripe_customer_id` mirror the current state.

### Subscription statuses

| Status | Meaning |
|---|---|
| `trial` | Free trial is running |
| `active` | Paid plan is running |
| `incomplete` | Used by the legacy payment_intent flow |
| `expired` | Trial ended, or a payment failed |
| `cancelled` | Cancelled by the user, replaced by another plan, or deleted on Stripe |

A user counts as "subscribed" when they have a `trial` or `active` subscription (`User::activeSubscription()`, `PlanEntitlements::subscriptionActive()`).

---

## 2. Signup: automatic free trial

1. When a user is created, `User` model's `created` event calls `SubscriptionService::startFreeTrial()`.
2. If the `free_trial` plan exists and the user has no subscription yet, a subscription is created:
   - `status = trial`
   - `billing_cycle = monthly`
   - `renews_at = now + trial_days` (100 days by default)
3. The user is updated: `plan_id = free_trial`, `subscription_status = trial`.
4. No Stripe call is made and no card is required.

> **Important:** `startFreeTrial()` looks up the plan by the hard-coded slug `free_trial`. In the live database the trial plan's slug is `100days_free_trial`, so the lookup finds nothing and the method silently returns. **New users currently get no subscription at all at signup** (no trial, `subscription_status` not set), until they subscribe to a plan themselves.

---

## 3. Browsing plans

All routes below require a Sanctum token.

- `GET /subscriptions/plans`: active plans ordered by `sort_order`, filtered by the user's `account_type` (plans marked `both` are shown to everyone). Each plan includes the actual price, original price and discount percent for both cycles. Each feature is enriched with its catalog id, slug, type and value.
- `GET /subscriptions/comparison`: a feature matrix with plans as columns and feature groups as rows. Missing cells mean "not included". Plans here only include `monthly_price` and `yearly_price` (actual prices); original prices and discount percent are not selected.
- `GET /subscriptions/current`: the user's latest `trial` or `active` subscription with its plan features. Returns 404 if none.

---

## 4. Subscribing: `POST /subscriptions/subscribe`

Request: `plan_slug`, `billing_cycle` (`monthly` or `yearly`), `stripe_payment_method_id` (needed for paid plans), `coupon_code` (optional).

### 4.1 Common checks

1. Validate input. The plan must exist and be active.
2. If the plan's `account_type` is not `both`, it must match the user's `account_type`. Otherwise 422.
3. If a coupon is given, it must exist and pass `Coupon::eligibilityError()` for this user, plan and cycle. Otherwise 422.
4. Price = plan's actual (discounted) `monthly_price` or `yearly_price`, minus the coupon discount. The original (MRP) price is not used. Example: Premium monthly = 14.99 (not 19.99), and a 10% coupon makes it 13.49.
5. The user's current plan is **not** checked. Any plan can be chosen, including the same plan, a lower plan or the trial plan.

### 4.2 Path A: trial or free plan (`trial_days > 0` or final price is 0)

1. `SubscriptionService::activate()` is called immediately (see section 6).
2. Status is `trial` for trial plans, otherwise `active`.
3. `renews_at` = now + trial days, or + 1 year / + 1 month.
4. The response returns the new subscription with its plan (HTTP 201).
5. No Stripe call and no payment.

### 4.3 Path B: paid plan

1. `stripe_payment_method_id` is required. Otherwise 422.
2. `StripeService::syncPlanPrice()` makes sure the Stripe Price matches the admin-set actual (discounted) price. If the amount changed, a new Price is created, saved on the plan, and the old one is archived.
3. `resolvePriceId()` gets the monthly or yearly Stripe price id. If missing, 422.
4. `createOrGetCustomer()` reuses `users.stripe_customer_id` (or an older subscription's customer id) or creates a new Stripe customer.
5. `cancelIncompleteSubscriptions()` cancels any unpaid Stripe subscriptions left over from earlier attempts.
6. `createSubscription()`:
   - attaches the payment method and makes it the customer's default;
   - creates the Stripe subscription with `payment_behavior = default_incomplete`;
   - stores `user_id`, `plan_id`, `billing_cycle`, `price`, `coupon_id` and `coupon_discount` in the Stripe subscription metadata;
   - if there is a coupon, creates a one-time Stripe coupon for the discount amount (first invoice only). Renewals are charged the full actual (discounted) price without the coupon.
7. **Nothing is written to the local database.** The user's current plan stays unchanged.
8. The response (HTTP 201) contains `status = incomplete`, the Stripe customer and subscription ids, the plan, and `payment_intent_client_secret`.

---

## 5. Payment confirmation in the app

1. The app confirms the payment with the Stripe SDK using `payment_intent_client_secret` (3DS happens here if needed).
2. If the user closes the payment sheet, nothing changes locally. The unpaid Stripe subscription is cancelled on the next subscribe attempt, or expires on Stripe's side.
3. On success, Stripe sends the `invoice.paid` webhook.

---

## 6. Plan activation: `SubscriptionService::activate()`

Used by Path A directly, and by the `invoice.paid` webhook for the first payment of a paid plan.

1. Runs in a DB transaction and locks the user row, so concurrent webhooks are handled one at a time.
2. If a subscription with the same `stripe_subscription_id` already exists, it is returned (idempotent for repeated webhooks).
3. All of the user's `trial`, `active` and `incomplete` subscriptions are set to `cancelled` (rows are kept as history).
4. A new `subscriptions` row is created for the new plan.
5. If a coupon was used, a `coupon_redemptions` row is created and `times_redeemed` is incremented.
6. A `user_subscriptions` snapshot is created with the plan's current features (later admin edits do not affect it).
7. The user's `plan_id` and `subscription_status` are updated.
8. After the transaction commits, the Stripe subscriptions of the replaced plans are cancelled on Stripe. Errors are ignored (for example, already cancelled).

The old plan ends immediately. Unused days on it are not refunded or credited.

---

## 7. Stripe webhook: `POST /webhook`

No auth middleware. The request is trusted only after `Stripe\Webhook::constructEvent()` verifies the signature. An invalid payload or signature returns 400.

### 7.1 `invoice.paid`

1. Reads the Stripe subscription id from the invoice (`parent.subscription_details.subscription`, falling back to `subscription`).
2. **Renewal**: a local subscription with that id exists.
   - Set `status = active`.
   - Set `renews_at` = now + 1 month or + 1 year.
   - Set `users.subscription_status = active`.
3. **First payment**: no local subscription exists yet.
   - `activateFromStripe()` retrieves the Stripe subscription and reads its metadata (user, plan, cycle, price, coupon).
   - If the user or plan cannot be found, an error is logged and processing stops.
   - Otherwise `activate()` is called with `status = active` and the Stripe ids (section 6).
4. A `payments` row is created or updated, keyed by `stripe_invoice_id` (amount paid, currency, `succeeded`, `paid_at`).
5. A push notification "Subscription purchase successful" is sent on the first payment only.
6. An invoice email (`SubscriptionInvoiceMail`) is queued on every paid invoice.

### 7.2 `invoice.payment_failed`

1. Finds the local subscription by Stripe subscription id.
2. Sets `status = expired`.
3. Sets the user's status to `expired`, unless the user has another `trial` or `active` subscription.
4. Creates or updates a `payments` row with `status = failed`.

If Stripe later retries and the payment succeeds, `invoice.paid` sets the subscription back to `active`.

### 7.3 `customer.subscription.deleted`

1. Finds the local subscription by Stripe subscription id.
2. If it is already `cancelled` (for example, replaced by an upgrade), nothing happens.
3. Otherwise sets `status = cancelled` and `cancelled_at`, and updates the user's status unless another live plan exists.

### 7.4 Legacy `payment_intent.*` events

Looked up by `payments.stripe_payment_intent_id`:

| Event | Effect |
|---|---|
| `payment_intent.created` / `processing` | Subscription `incomplete` |
| `payment_intent.succeeded` | Subscription and user `active`, payment `succeeded`, push notification, invoice email |
| `payment_intent.payment_failed` | Subscription `expired`, payment `failed` |
| `payment_intent.canceled` | Subscription `cancelled` |

Other event types are logged and ignored.

---

## 8. Cancelling: `POST /subscriptions/cancel`

1. Finds the user's active (`trial` or `active`) subscription. Returns 404 if none.
2. If it has a Stripe subscription, it is cancelled on Stripe **immediately**.
3. In a transaction:
   - subscription: `status = cancelled`, `cancelled_at = now`;
   - snapshot (`user_subscriptions`): same;
   - user: `subscription_status = cancelled`.
4. Access ends at once, even if the current billing period has days left.

---

## 9. Trial expiry: `subscriptions:process-trials` (daily)

1. Finds subscriptions with `status = trial` and `renews_at <= now`.
2. For each one, in a transaction: subscription `expired`, user `subscription_status = expired`.
3. Trials are **not** charged. The `0.99` monthly / `11.88` yearly price on the trial plan is never billed. After expiry, search and chat are disabled until the user buys a paid plan.

---

## 10. Lifecycle summary

```
Signup
  └─> free_trial (status: trial, 100 days)
        ├─ trial ends (daily cron) ──────────────> expired
        ├─ subscribe trial/free plan ────────────> activate() immediately
        └─ subscribe paid plan
              └─> Stripe subscription (incomplete, nothing local)
                    ├─ user backs out ───────────> no change (cleaned up next attempt)
                    └─ payment succeeds
                          └─> invoice.paid ──────> activate()
                                                    old plan cancelled (local + Stripe)
                                                    new plan active

Active paid plan
  ├─ invoice.paid (renewal) ─────────────────────> active, renews_at extended
  ├─ invoice.payment_failed ─────────────────────> expired
  ├─ customer.subscription.deleted ──────────────> cancelled
  ├─ POST /subscriptions/cancel ─────────────────> cancelled immediately (Stripe + local)
  └─ subscribe another plan ─────────────────────> same as above (any plan allowed)
```

---

## 11. Current behaviour to be aware of

- **Signup trial is broken in the live database.** `startFreeTrial()` looks for slug `free_trial`, but the live slug is `100days_free_trial`, so new users get no trial.
- **No upgrade or downgrade rules.** `subscribe()` does not compare the new plan with the current one. A user on a paid plan can switch to a lower plan, the same plan again, or the trial plan.
- **Switching to the trial plan** goes through Path A: the paid plan is cancelled immediately without payment, and a new 100-day trial starts. This can be repeated.
- **Plan changes take effect immediately.** Unused days on the old plan are lost. There is no refund, credit or proration.
- **Cancellation is immediate**, not at the end of the paid period.
- **A failed payment expires the plan right away**, even while Stripe may still retry the charge.
