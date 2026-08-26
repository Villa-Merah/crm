<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\WhatsAppMatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'match_status' => WhatsAppMatchStatus::class,
            'first_message_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalBranchAccess()) {
            return $query;
        }

        $query->whereHas('account', fn (Builder $account) => $account->where('cabang_id', $user->cabang_id));

        return $user->hasRole(UserRole::Staff)
            ? $query->whereHas('account', fn (Builder $account) => $account->where('user_id', $user->id))
            : $query;
    }

    public function account()
    {
        return $this->belongsTo(UserWhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(WhatsAppMessage::class, 'conversation_id')->latestOfMany('sent_at');
    }
}
