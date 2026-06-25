<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['nullable', 'string', 'max:150'],
            'provider'        => ['required', 'string', 'in:google,apple,phone,email'],
            'providerToken'   => ['required', 'string', 'min:10'],
            'consentAccepted' => ['required', 'boolean', 'accepted'],
            'accountType'     => ['nullable', 'string', 'in:normal,employer'],
            'companyName'     => ['nullable', 'string', 'max:200'],
            'employerId'      => ['nullable', 'string', 'max:100'],
            'jobTitle'        => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required'        => 'Auth provider is required.',
            'provider.in'              => 'Provider must be one of: google, apple, phone, email.',
            'providerToken.required'   => 'Provider token is required.',
            'consentAccepted.accepted' => 'You must accept the terms to register.',
        ];
    }
}
