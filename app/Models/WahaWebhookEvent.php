<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WahaWebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
