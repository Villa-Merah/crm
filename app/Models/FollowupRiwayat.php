<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Model;

class FollowupRiwayat extends Model
{
    protected $table = 'followup_riwayat';

    protected $primaryKey = 'followup_riwayat_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_followup' => 'datetime',
            'tanggal_followup_berikutnya' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $followup): void {
            $followup->lead()
                ->where('status_lead', LeadStatus::Cold->value)
                ->update(['status_lead' => LeadStatus::Warm->value]);
        });
    }
}
