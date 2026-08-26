<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ListLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'sumber' => ['nullable', Rule::enum(LeadSource::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'cabang_id' => ['nullable', 'integer', 'exists:cabang,cabang_id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('public_api.max_per_page')],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ApiResponse::error(
            $this,
            'validation_failed',
            'Parameter query tidak valid.',
            422,
            $validator->errors()->toArray(),
        ));
    }
}
