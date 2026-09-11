<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('code'));

        $this->merge([
            'code' => Str::upper($code),
            'plan_id' => $this->input('plan_id') ?: null,
            'billing_cycle' => $this->input('billing_cycle') ?: null,
            'max_redemptions' => $this->input('max_redemptions') ?: null,
            'starts_at' => $this->input('starts_at') ?: null,
            'expires_at' => $this->input('expires_at') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('coupons', 'code')],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'required', 'numeric', 'min:0.01',
                Rule::when($this->input('discount_type') === 'percentage', ['max:100']),
            ],
            'plan_id' => ['nullable', Rule::exists('subscription_plans', 'id')],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_redemptions_per_user' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A coupon with this code already exists.',
            'code.alpha_dash' => 'Coupon code may only contain letters, numbers, dashes and underscores.',
        ];
    }
}
