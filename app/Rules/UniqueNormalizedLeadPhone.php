<?php

namespace App\Rules;

use App\Models\Lead;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueNormalizedLeadPhone implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreLeadId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = app(PhoneNormalizer::class)->normalize(is_scalar($value) ? (string) $value : null);

        if (! $normalized) {
            $fail('Format nomor WhatsApp tidak valid. Gunakan 8–15 digit dan boleh diawali tanda +.');

            return;
        }

        $duplicateExists = Lead::query()
            ->where('no_wa_normalized', $normalized)
            ->when($this->ignoreLeadId, fn ($query) => $query->whereKeyNot($this->ignoreLeadId))
            ->exists();

        if ($duplicateExists) {
            $fail('Nomor WhatsApp sudah digunakan oleh lead lain. Periksa data lead yang sudah ada.');
        }
    }
}
