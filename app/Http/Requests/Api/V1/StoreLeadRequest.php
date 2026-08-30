<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\CrmSetting;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'no_wa' => ['required', 'string', 'max:20'],
            'kota_asal' => ['nullable', 'string', 'max:100'],
            'kelas' => ['nullable', 'string', 'max:60', Rule::in(CrmSetting::getArray('program_class_levels'))],
            'cabang_id' => ['nullable', 'integer', 'exists:cabang,cabang_id'],
            'sekolah_id' => ['nullable', 'integer', 'exists:sekolah,sekolah_id'],
            'minat_program_id' => ['nullable', 'integer', 'exists:program_bimbel,program_bimbel_id'],
            'sumber_lead' => ['nullable', Rule::enum(LeadSource::class)],
            'status_lead' => ['sometimes', Rule::enum(LeadStatus::class)],
            'diserahkan_ke_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'tanggal_masuk' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ApiResponse::error(
            $this,
            'validation_failed',
            'Data yang diberikan tidak valid.',
            422,
            $validator->errors()->toArray(),
        ));
    }
}
