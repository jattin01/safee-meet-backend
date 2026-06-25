<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'      => ['required', 'string', 'in:google,apple,phone,email'],
            'providerToken' => ['required', 'string', 'min:10'],
            'loginType'     => ['nullable', 'string', 'in:social,password,otp'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required'      => 'Auth provider is required.',
            'provider.in'            => 'Provider must be one of: google, apple, phone, email.',
            'providerToken.required' => 'Provider token is required.',
        ];
    }
}
