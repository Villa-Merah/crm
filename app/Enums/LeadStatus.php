<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Cold = 'Cold';
    case Warm = 'Warm';
    case Hot = 'Hot';
    case Daftar = 'Daftar';
    case TidakBerminat = 'Tidak Berminat';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->value])
            ->all();
    }

    public static function color(string $status): string
    {
        return match ($status) {
            self::Cold->value => 'info',
            self::Warm->value => 'warning',
            self::Hot->value => 'danger',
            self::Daftar->value => 'success',
            default => 'gray',
        };
    }
}
