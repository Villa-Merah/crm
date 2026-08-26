<?php

namespace App\Models;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'direction' => WhatsAppMessageDirection::class,
            'processing_status' => WhatsAppMessageStatus::class,
            'has_media' => 'boolean',
            'from_me' => 'boolean',
            'sent_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function account()
    {
        return $this->belongsTo(UserWhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'lead_id');
    }
}
