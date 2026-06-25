<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CheckUserExistsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['nullable', 'email'],
            'phone'       => ['nullable', 'string'],
            'providerUid' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (!$this->email && !$this->phone && !$this->providerUid) {
                $v->errors()->add('identifier', 'At least one of email, phone, or providerUid is required.');
            }
        });
    }
}
