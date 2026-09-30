<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TargetKinerjaUser extends Model
{
    protected $table = 'target_kinerja_user';

    protected $primaryKey = 'target_kinerja_user_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_omzet' => 'decimal:2', 'target_siswa' => 'integer'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('targetCabang', fn (Builder $builder) => $builder->visibleTo($user))
            ->when($user->hasRole(UserRole::Staff), fn (Builder $builder) => $builder->where('user_id', $user->id));
    }

    public function targetCabang()
    {
        return $this->belongsTo(TargetKinerjaCabang::class, 'target_kinerja_cabang_id', 'target_kinerja_cabang_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
