<?php

namespace App\Http\Requests\Api\V1;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ListStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['Aktif', 'Nonaktif'])],
            'search' => ['nullable', 'string', 'max:100'],
            'cabang_id' => ['nullable', 'integer', 'exists:cabang,cabang_id'],
            'page' => ['nullable', 'integer', 'min:1'],
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
