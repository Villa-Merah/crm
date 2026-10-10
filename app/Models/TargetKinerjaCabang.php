<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TargetKinerjaCabang extends Model
{
    protected $table = 'target_kinerja_cabang';

    protected $primaryKey = 'target_kinerja_cabang_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'bulan' => 'integer', 'target_omzet' => 'decimal:2', 'target_siswa' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $target): void {
            if (! in_array($target->status, ['Draft', 'Aktif', 'Selesai'], true)) {
                throw ValidationException::withMessages(['status' => 'Status target tidak valid.']);
            }

            if ($target->tahun < 2020 || $target->tahun > 2100) {
                throw ValidationException::withMessages(['tahun' => 'Tahun target harus berada antara 2020 dan 2100.']);
            }

            if ($target->bulan < 1 || $target->bulan > 12) {
                throw ValidationException::withMessages(['bulan' => 'Bulan target tidak valid.']);
            }

            if ((float) $target->target_omzet < 0 || (int) $target->target_siswa < 0) {
                throw ValidationException::withMessages([
                    'target_omzet' => 'Target omzet tidak boleh negatif.',
                    'target_siswa' => 'Target siswa tidak boleh negatif.',
                ]);
            }

            if (in_array($target->status, ['Aktif', 'Selesai'], true)) {
                $errors = [];

                if ((float) $target->target_omzet <= 0) {
                    $errors['target_omzet'] = 'Target omzet harus lebih dari nol ketika target aktif atau selesai.';
                }

                if ((int) $target->target_siswa <= 0) {
                    $errors['target_siswa'] = 'Target siswa harus lebih dari nol ketika target aktif atau selesai.';
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            }

            if (! $target->exists) {
                return;
            }

            if ($target->getOriginal('status') === 'Selesai' && $target->isDirty()) {
                throw ValidationException::withMessages(['status' => 'Target yang sudah selesai dikunci dan tidak dapat diubah.']);
            }

            if ($target->isDirty('cabang_id')) {
                throw ValidationException::withMessages(['cabang_id' => 'Cabang target tidak dapat diubah.']);
            }

            $allocatedOmzet = (float) $target->targetsUser()->sum('target_omzet');
            $allocatedSiswa = (int) $target->targetsUser()->sum('target_siswa');

            if ($allocatedOmzet > (float) $target->target_omzet) {
                throw ValidationException::withMessages(['target_omzet' => 'Target omzet tidak boleh lebih kecil dari total alokasi pengguna.']);
            }

            if ($allocatedSiswa > (int) $target->target_siswa) {
                throw ValidationException::withMessages(['target_siswa' => 'Target siswa tidak boleh lebih kecil dari total alokasi pengguna.']);
            }
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->hasGlobalBranchAccess(), fn (Builder $builder) => $builder->where('cabang_id', $user->cabang_id));
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'cabang_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetsUser()
    {
        return $this->hasMany(TargetKinerjaUser::class, 'target_kinerja_cabang_id', 'target_kinerja_cabang_id');
    }
}
