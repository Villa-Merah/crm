<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TargetKinerjaUser extends Model
{
    protected $table = 'target_kinerja_user';

    protected $primaryKey = 'target_kinerja_user_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_omzet' => 'decimal:2', 'target_siswa' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $allocation): void {
            $target = TargetKinerjaCabang::query()->findOrFail($allocation->target_kinerja_cabang_id);
            $user = User::query()->findOrFail($allocation->user_id);

            if ($target->status === 'Selesai') {
                throw ValidationException::withMessages(['target_kinerja_cabang_id' => 'Alokasi target yang sudah selesai tidak dapat diubah.']);
            }

            if (! $user->aktif || $user->cabang_id !== $target->cabang_id || ! in_array($user->role, [UserRole::AdminCabang, UserRole::Staff], true)) {
                throw ValidationException::withMessages(['user_id' => 'Pengguna harus aktif, berada di cabang target, dan memiliki role Admin Cabang atau Staff.']);
            }

            if ((float) $allocation->target_omzet < 0) {
                throw ValidationException::withMessages(['target_omzet' => 'Alokasi omzet tidak boleh negatif.']);
            }

            if ((int) $allocation->target_siswa < 0) {
                throw ValidationException::withMessages(['target_siswa' => 'Alokasi siswa tidak boleh negatif.']);
            }

            $otherAllocations = self::query()
                ->where('target_kinerja_cabang_id', $target->getKey())
                ->when($allocation->exists, fn (Builder $query) => $query->whereKeyNot($allocation->getKey()));

            $allocatedOmzet = (float) (clone $otherAllocations)->sum('target_omzet') + (float) $allocation->target_omzet;
            $allocatedSiswa = (int) (clone $otherAllocations)->sum('target_siswa') + (int) $allocation->target_siswa;

            if ($allocatedOmzet > (float) $target->target_omzet) {
                throw ValidationException::withMessages(['target_omzet' => 'Total alokasi omzet melebihi target omzet cabang.']);
            }

            if ($allocatedSiswa > (int) $target->target_siswa) {
                throw ValidationException::withMessages(['target_siswa' => 'Total alokasi siswa melebihi target siswa cabang.']);
            }
        });
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
