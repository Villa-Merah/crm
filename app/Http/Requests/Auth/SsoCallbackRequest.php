<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SsoCallbackRequest extends FormRequest
{
    protected $redirectRoute = 'filament.admin.auth.login';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['state' => ['required', 'string', 'max:256'], 'code' => ['required', 'string', 'max:4096']];
    }

    public function messages(): array
    {
        return ['state.required' => 'Sesi login SSO tidak valid. Silakan coba kembali.', 'code.required' => 'Login SSO dibatalkan atau tidak dapat diproses.'];
    }
}
