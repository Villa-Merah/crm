<?php

namespace App\Enums;

enum LeadSource: string
{
    case Iklan = 'Iklan';
    case Instagram = 'Instagram';
    case Kunjungan = 'Kunjungan';
    case Saudara = 'Saudara';
    case Facebook = 'Facebook';
    case Tiktok = 'Tiktok';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [$source->value => $source->value])
            ->all();
    }

    public static function color(?string $source): string
    {
        return match ($source) {
            self::Iklan->value => 'warning',
            self::Instagram->value => 'danger',
            self::Kunjungan->value => 'success',
            self::Saudara->value => 'info',
            self::Facebook->value => 'primary',
            self::Tiktok->value => 'gray',
            default => 'gray',
        };
    }
}
