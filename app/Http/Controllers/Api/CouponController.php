<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    /**
     * POST /api/coupons/validate — checked from the checkout screen before
     * calling subscribe(), so the app can show the discounted price up front.
     * Does not redeem anything; redemption only happens inside subscribe().
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'plan_slug' => ['required', 'string', Rule::exists('subscription_plans', 'slug')],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $validated['plan_slug'])->firstOrFail();

        $coupon = Coupon::where('code', strtoupper($validated['code']))->first();

        if (! $coupon) {
            return response()->json(['message' => 'Invalid coupon code.'], 422);
        }

        $error = $coupon->eligibilityError($user->id, $plan, $validated['billing_cycle']);

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $price = $validated['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        $discount = $coupon->discountFor((float) $price);

        return response()->json([
            'valid' => true,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'original_price' => (float) $price,
            'discount_amount' => $discount,
            'final_price' => round((float) $price - $discount, 2),
        ]);
    }
}
