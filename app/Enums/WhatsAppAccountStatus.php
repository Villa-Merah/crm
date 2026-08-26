<?php

namespace App\Enums;

enum WhatsAppAccountStatus: string
{
    case Disconnected = 'Disconnected';
    case Connecting = 'Connecting';
    case Working = 'Working';
    case Failed = 'Failed';
}
