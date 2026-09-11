<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    public function rules(): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->route('coupon');

        $rules = parent::rules();
        $rules['code'] = ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon)];

        return $rules;
    }
}
