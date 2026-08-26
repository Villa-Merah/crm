<?php

namespace App\Enums;

enum LeadEntryChannel: string
{
    case Manual = 'Manual';
    case Csv = 'CSV';
    case WhatsApp = 'WhatsApp';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->value])->all();
    }
}
