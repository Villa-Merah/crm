<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cabang extends Model
{
    protected $table = 'cabang';
    protected $primaryKey = 'cabang_id';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function users() { return $this->hasMany(User::class, 'cabang_id', 'cabang_id'); }
    public function leads() { return $this->hasMany(Lead::class, 'cabang_id', 'cabang_id'); }
    public function targetKinerja() { return $this->hasMany(TargetKinerjaCabang::class, 'cabang_id', 'cabang_id'); }
}
