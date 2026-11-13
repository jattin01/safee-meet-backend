# Plan Change API

How the backend handles upgrades, downgrades and plan previews. Every sample below is an actual response, produced by running the code.

## How the responses are shaped

- Responses have **no `data` wrapper**. `code`, `message` and `available_from` always sit at the **top level**.
- The base path is `/api/v1`. Every endpoint below needs `Authorization: Bearer <token>` (Sanctum).
- Amounts are **strings with 2 decimals** in USD, for example `"60.11"`.
- Dates are **ISO 8601 in UTC**. Show them in the user's local timezone. There are two formats:
  - `available_from` and the `renews_at` from the preview look like `2027-10-13T12:31:00+00:00`.
  - Dates inside the `subscription` object (`renews_at`, `started_at`, …) look like `2027-10-13T12:31:00.000000Z`.

  A standard date parser reads both.

## Rules

| Current plan | User picks | Result |
|---|---|---|
| None, trial, expired or cancelled | Any plan | New checkout (same as before) |
| Monthly | A higher plan (monthly or yearly), or the same plan yearly | New checkout at full price. The days left on the current month are **not** carried over. |
| Monthly | A lower plan | `422 downgrade_not_allowed` |
| Yearly | A higher **yearly** plan | **Prorated upgrade**: only the difference is charged, the renewal date stays the same |
| Yearly | A higher **monthly** plan | `422 yearly_upgrade_yearly_only` |
| Yearly | A lower plan, or the same plan monthly | `422 downgrade_not_allowed` |
| Any | The same plan and the same cycle | `422 already_on_plan` |
| Monthly or yearly (paid) | The free-trial plan | `422 trial_not_available` (`available_from` is `null`) |

Plans are ranked by `sort_order`.

---

## 1. GET /subscriptions/plans: `plan_change`

Each plan now carries `plan_change`, worked out for the **logged-in user's current plan**. The route sits behind `auth:sanctum`, so this field is always present.

```json
"plan_change": {
  "monthly": {
    "allowed": false,
    "type": "blocked",
    "code": "yearly_upgrade_yearly_only",
    "message": "Your yearly plan can be upgraded to a higher yearly plan only. Monthly plans are available after it ends on 13 Oct 2027.",
    "available_from": "2027-10-13T12:31:00+00:00"
  },
  "yearly": {
    "allowed": true,
    "type": "prorated_upgrade",
    "code": null,
    "message": null,
    "available_from": null
  }
}
```

| Field | Values |
|---|---|
| `allowed` | `true` / `false`. Use this to enable or disable the buy button. |
| `type` | `new_checkout`, `prorated_upgrade` or `blocked` |
| `code` | `null`, `downgrade_not_allowed`, `yearly_upgrade_yearly_only`, `already_on_plan` or `trial_not_available` |
| `message` | Text you can show to the user (only when blocked) |
| `available_from` | The date the current plan ends (only when blocked) |

---

## 2. GET /subscriptions/change-preview

**Query:** `plan_slug`, `billing_cycle` (`monthly` | `yearly`)

Shows what buying the plan would charge right now. Call it before the confirm screen.

**Yearly → higher yearly (prorated):**

```json
{
  "change_type": "prorated_upgrade",
  "plan_price": "179.99",
  "amount_due_now": "60.11",
  "currency": "usd",
  "renews_at": "2027-10-13T12:31:00+00:00",
  "unused_days_lost": 0
}
```

**Monthly → higher plan (new checkout):**

```json
{
  "change_type": "new_checkout",
  "plan_price": "14.99",
  "amount_due_now": "14.99",
  "currency": "usd",
  "renews_at": null,
  "unused_days_lost": 20
}
```

- When `unused_days_lost > 0`, warn the user before they confirm, for example: "Your remaining 20 days on the current plan will not be carried over."
- For a blocked change, the preview returns the same **422** as in section 4.

---

## 3. POST /subscriptions/subscribe

The request is the same as before:

```json
{ "plan_slug": "premium", "billing_cycle": "yearly", "stripe_payment_method_id": "pm_..." }
```

For a prorated upgrade, `stripe_payment_method_id` is optional. Without it, the card on file is charged.

### 200: upgraded (charged right away, no confirmation needed)

```json
{
  "message": "Your plan has been upgraded.",
  "code": "upgraded",
  "change_type": "prorated_upgrade",
  "amount_charged": "60.11",
  "subscription": {
    "id": 368,
    "subscription_id": "01M7AE7VV8E04TX55NFP92JBGP",
    "price": "179.99",
    "billing_cycle": "yearly",
    "status": "active",
    "trial_days": null,
    "started_at": "2026-11-13T12:31:10.000000Z",
    "renews_at": "2027-10-13T12:31:00.000000Z",
    "cancelled_at": null,
    "stripe_customer_id": "cus_123",
    "stripe_subscription_id": "sub_123",
    "user_id": 112,
    "plan_id": 20,
    "plan": {
      "id": 20,
      "name": "Premium",
      "slug": "premium",
      "monthly_price": "14.99",
      "yearly_price": "179.99",
      "...": "full plan object"
    }
  }
}
```

**This response has no client secret.** Show the success screen directly. The user is already on the new plan.

### 201: payment needs confirmation (3DS, or the card was declined)

The shape is the same as a normal new checkout, plus `change_type` and `amount_due`:

```json
{
  "user_id": 112,
  "plan_id": 20,
  "price": "179.99",
  "billing_cycle": "yearly",
  "status": "incomplete",
  "change_type": "prorated_upgrade",
  "amount_due": "60.11",
  "stripe_customer_id": "cus_123",
  "stripe_subscription_id": "sub_123",
  "plan": { "...": "plan object" },
  "payment_intent_client_secret": "pi_..._secret_...",
  "payment_intent_status": "incomplete",
  "stripe_subscription_status": "active",
  "invoice_status": "open"
}
```

- Confirm `payment_intent_client_secret` with the Stripe SDK, exactly as you do for a new checkout.
- Until the payment succeeds, the user **stays on the current plan** and keeps their access.
- Once the payment succeeds, the backend switches the plan (via webhook). Refresh `GET /subscriptions/current`.
- If the user calls subscribe again for the same upgrade, the same pending payment comes back. No duplicate charge is created.

A monthly user upgrading still gets the normal new-checkout **201**, without `change_type`.

---

## 4. 422: change not allowed

Returned by both `subscribe` and `change-preview`:

```json
{
  "message": "You can switch to this plan after your current plan ends on 13 Oct 2027.",
  "code": "downgrade_not_allowed",
  "available_from": "2027-10-13T12:31:00+00:00"
}
```

| `code` | When |
|---|---|
| `downgrade_not_allowed` | A lower plan, or yearly → the same plan monthly |
| `yearly_upgrade_yearly_only` | A yearly user picked a higher **monthly** plan |
| `already_on_plan` | The same plan and the same cycle |
| `trial_not_available` | A user on a paid plan picked the free trial. `available_from` is `null`. |

Sample for `trial_not_available`:

```json
{
  "message": "The free trial is not available while you have an active paid plan.",
  "code": "trial_not_available",
  "available_from": null
}
```

The `message` can be shown to the user as is.

### Telling 422s apart

Laravel's validation 422 looks different. It has an `errors` object and **no `code`**:

```json
{
  "message": "The plan slug field is required.",
  "errors": { "plan_slug": ["The plan slug field is required."] }
}
```

To tell them apart, check whether `code` is present. Other subscribe errors with a `code` are unchanged:

| Status | `code` |
|---|---|
| 409 | `checkout_in_progress` |
| 409 | `upgrade_not_possible` (new: the current plan can't be upgraded right now) |
| 422 | Stripe card error code |
| 422 | `coupon_not_allowed_for_upgrade` (new: coupons can't be used on an upgrade) |
| 503 | `payment_service_unavailable` |
| 502 | `payment_error` |

---

## Suggested app flow

1. Plans screen: disable the buttons where `plan_change.<cycle>.allowed` is `false`, and show its `message`.
2. On tap, call `change-preview`. Show `amount_due_now`, plus the unused-days warning if there is one.
3. On confirm, call `subscribe`:
   - **200 `upgraded`**: show success.
   - **201**: confirm the client secret with Stripe, then refresh `current`.
   - **422 with `code`**: show `message`.
