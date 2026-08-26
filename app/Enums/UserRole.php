<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case AdminCabang = 'admin_cabang';
    case Staff = 'staff';
    case Direksi = 'direksi';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminCabang => 'Admin Cabang',
            self::Staff => 'Staff',
            self::Direksi => 'Direksi',
        };
    }

    public function hasGlobalBranchAccess(): bool
    {
        return in_array($this, [self::Superadmin, self::Direksi], true);
    }

    public function canManageOperationalData(): bool
    {
        return $this !== self::Direksi;
    }
}
