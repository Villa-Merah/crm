<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pendaftaran extends Model
{
    protected $table = 'pendaftaran';

    protected $primaryKey = 'pendaftaran_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal_daftar' => 'date'];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    public function programBimbel()
    {
        return $this->belongsTo(ProgramBimbel::class, 'program_bimbel_id', 'program_bimbel_id');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'cabang_id');
    }

    public function payments()
    {
        return $this->hasMany(Pembayaran::class, 'pendaftaran_id', 'pendaftaran_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalFinanceScope()) {
            return $query;
        }

        $query->where('cabang_id', $user->cabang_id);

        return $user->hasRole(UserRole::Staff)
            ? $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->visibleTo($user))
            : $query;
    }

    protected static function booted(): void
    {
        static::saved(function (self $registration): void {
            if ($registration->status_pendaftaran === 'Aktif') {
                $registration->lead()->update(['status_lead' => LeadStatus::Daftar->value]);
            }
        });
    }
}
