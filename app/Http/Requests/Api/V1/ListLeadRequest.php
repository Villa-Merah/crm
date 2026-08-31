<?php

namespace App\Http\Requests\Api\V1;

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
            'sumber' => ['nullable', 'string', 'max:100', Rule::exists('sumber_leads', 'nama')->where('aktif', true)],
            'sumber_lead_id' => ['nullable', 'integer', Rule::exists('sumber_leads', 'sumber_lead_id')->where('aktif', true)],
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
