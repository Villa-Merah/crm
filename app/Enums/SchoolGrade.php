<?php

namespace App\Enums;

enum SchoolGrade: string
{
    case A = 'Grade A';
    case B = 'Grade B';
    case C = 'Grade C';
    case D = 'Grade D';
    case E = 'Grade E';

    public function description(): ?string
    {
        return match ($this) {
            self::A => 'Sekolah sering menyumbang siswa terbanyak, walaupun bukan sekolah favorit menurut catatan Kementerian Pendidikan.',
            self::B => 'Sekolah sedikit atau jarang menyumbang siswa dan merupakan sekolah favorit menurut catatan Kementerian Pendidikan.',
            self::C => 'Sekolah tidak pernah menyumbang siswa dan merupakan sekolah favorit menurut catatan Kementerian Pendidikan.',
            self::D, self::E => null,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::A => 'success',
            self::B => 'info',
            self::C => 'warning',
            self::D => 'danger',
            self::E => 'gray',
        };
    }

    public static function options(): array
    {
        return array_combine(self::values(), self::values());
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
