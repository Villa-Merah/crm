<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'cabang_id', 'role', 'aktif'];

    protected $hidden = ['password', 'remember_token'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->aktif && $this->role instanceof UserRole;
    }

    public function hasRole(UserRole|string $role): bool
    {
        return $this->role === ($role instanceof UserRole ? $role : UserRole::tryFrom($role));
    }

    public function hasGlobalBranchAccess(): bool
    {
        return $this->role->hasGlobalBranchAccess();
    }

    public function canManageOperationalData(): bool
    {
        return $this->role->canManageOperationalData();
    }

    public function canViewSalesData(): bool
    {
        return $this->role->canViewSalesData();
    }

    public function canManageRegistrations(): bool
    {
        return $this->role->canManageRegistrations();
    }

    public function canRecordPayments(): bool
    {
        return $this->role->canRecordPayments();
    }

    public function canVerifyPayments(): bool
    {
        return $this->role->canVerifyPayments();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'cabang_id');
    }

    public function leadsDitugaskan()
    {
        return $this->hasMany(Lead::class, 'diserahkan_ke_user_id');
    }

    public function targetKinerja()
    {
        return $this->hasMany(TargetKinerjaUser::class);
    }

    public function targetCabangDibuat()
    {
        return $this->hasMany(TargetKinerjaCabang::class, 'created_by');
    }

    public function whatsappAccount()
    {
        return $this->hasOne(UserWhatsAppAccount::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'aktif' => 'boolean',
        ];
    }
}
