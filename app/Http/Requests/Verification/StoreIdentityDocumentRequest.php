<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class StoreIdentityDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'front' => ['required', 'image', 'max:10240'],
            'back' => ['required', 'image', 'max:10240'],
            'documentType' => ['nullable', 'string', 'in:passport,drivers_license,national_id,other'],
            'issuingCountryCode' => ['nullable', 'string', 'size:2'],
        ];
    }
}
