<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case Processed = 'Processed';
    case Pending = 'Pending';
    case Sent = 'Sent';
    case Delivered = 'Delivered';
    case Read = 'Read';
    case Played = 'Played';
    case Failed = 'Failed';

    public function label(): string
    {
        return match ($this) {
            self::Processed => 'Diterima',
            self::Pending => 'Menunggu',
            self::Sent => 'Terkirim',
            self::Delivered => 'Diterima perangkat',
            self::Read => 'Dibaca',
            self::Played => 'Diputar',
            self::Failed => 'Gagal',
        };
    }
}
