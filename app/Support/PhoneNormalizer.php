<?php

namespace App\Support;

class PhoneNormalizer
{
    public function normalize(?string $value): ?string
    {
        if (blank($value) || str_contains($value, '@lid')) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', str($value)->before('@')->before(':')->toString());

        if (blank($phone)) {
            return null;
        }

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62'.$phone;
        }

        return preg_match('/^[1-9][0-9]{7,14}$/', $phone) === 1 ? $phone : null;
    }

    public function fromChatId(?string $chatId): ?string
    {
        if (blank($chatId) || str_contains($chatId, '@g.us') || str_contains($chatId, '@newsletter') || str_contains($chatId, 'status@broadcast')) {
            return null;
        }

        return $this->normalize($chatId);
    }
}
