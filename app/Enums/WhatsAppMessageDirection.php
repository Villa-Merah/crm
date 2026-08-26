<?php

namespace App\Enums;

enum WhatsAppMessageDirection: string
{
    case Inbound = 'Inbound';
    case Outbound = 'Outbound';
}
