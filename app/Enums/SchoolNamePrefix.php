<?php

namespace App\Enums;

enum SchoolNamePrefix: string
{
    case Sman = 'SMAN';
    case Smas = 'SMAS';
    case Smkn = 'SMKN';
    case Smks = 'SMKS';
    case Man = 'MAN';
    case AlumniGapyear = 'ALUMNI/GAPYEAR';

    public function label(): string
    {
        return match ($this) {
            self::Sman => 'SMAN - SMA Negeri',
            self::Smas => 'SMAS - SMA Swasta',
            self::Smkn => 'SMKN - SMK Negeri',
            self::Smks => 'SMKS - SMK Swasta',
            self::Man => 'MAN - Madrasah Aliyah Negeri',
            self::AlumniGapyear => 'Alumni/Gapyear',
        };
    }

    public function schoolStatus(): string
    {
        return match ($this) {
            self::Sman, self::Smkn, self::Man => 'Negeri',
            self::Smas, self::Smks => 'Swasta',
            self::AlumniGapyear => 'Lainnya',
        };
    }

    public function requiresSchoolName(): bool
    {
        return $this !== self::AlumniGapyear;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
