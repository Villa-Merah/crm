<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sekolah extends Model
{
    protected $table = 'sekolah';

    protected $primaryKey = 'sekolah_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'api_last_synced_at' => 'datetime',
        ];
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'sekolah_id', 'sekolah_id');
    }
}
