<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TargetKinerjaCabang extends Model
{
    protected $table = 'target_kinerja_cabang';
    protected $primaryKey = 'target_kinerja_cabang_id';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'bulan' => 'integer', 'target_omzet' => 'decimal:2', 'target_siswa' => 'integer'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->hasGlobalBranchAccess(), fn (Builder $builder) => $builder->where('cabang_id', $user->cabang_id));
    }

    public function cabang() { return $this->belongsTo(Cabang::class, 'cabang_id', 'cabang_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function targetsUser() { return $this->hasMany(TargetKinerjaUser::class, 'target_kinerja_cabang_id', 'target_kinerja_cabang_id'); }
}
