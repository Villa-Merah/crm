<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case AdminCabang = 'admin_cabang';
    case Staff = 'staff';
    case Keuangan = 'keuangan';
    case Direksi = 'direksi';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminCabang => 'Admin Cabang',
            self::Staff => 'Staff',
            self::Keuangan => 'Keuangan',
            self::Direksi => 'Direksi',
        };
    }

    public function hasGlobalBranchAccess(): bool
    {
        return in_array($this, [self::Superadmin, self::Direksi], true);
    }

    public function canManageOperationalData(): bool
    {
        return in_array($this, [self::AdminCabang, self::Staff], true);
    }

    public function canViewSalesData(): bool
    {
        return $this !== self::Keuangan;
    }

    public function canManageRegistrations(): bool
    {
        return $this === self::Staff;
    }

    public function canRecordPayments(): bool
    {
        return in_array($this, [self::Staff, self::Keuangan], true);
    }

    public function canVerifyPayments(): bool
    {
        return $this === self::Keuangan;
    }
}
