<?php

namespace App\Http\Requests\Admin;

use App\Models\JobTitle;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateJobTitleRequest extends StoreJobTitleRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        /** @var JobTitle $jobTitle */
        $jobTitle = $this->route('jobTitle');

        return [
            'name' => ['required', 'string', 'max:100'],
            'normalized_name' => [
                'required',
                Rule::unique('job_titles', 'normalized_name')->ignore($jobTitle),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
