<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\WhatsAppAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserWhatsAppAccount extends Model
{
    protected $table = 'user_whatsapp_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppAccountStatus::class,
            'aktif' => 'boolean',
            'connected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalBranchAccess()) {
            return $query;
        }

        $query->where('cabang_id', $user->cabang_id);

        return $user->hasRole(UserRole::Staff)
            ? $query->where('user_id', $user->id)
            : $query;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'cabang_id');
    }

    public function conversations()
    {
        return $this->hasMany(WhatsAppConversation::class, 'whatsapp_account_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_account_id');
    }
}
