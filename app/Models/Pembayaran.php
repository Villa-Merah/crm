<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';
    protected $primaryKey = 'pembayaran_id';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_pembayaran' => 'datetime',
            'nominal' => 'decimal:2',
        ];
    }

    public function pendaftaran() { return $this->belongsTo(Pendaftaran::class, 'pendaftaran_id', 'pendaftaran_id'); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('pendaftaran', fn (Builder $registrationQuery) => $registrationQuery->visibleTo($user));
    }
}
