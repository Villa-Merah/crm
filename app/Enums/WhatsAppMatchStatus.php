<?php

namespace App\Enums;

enum WhatsAppMatchStatus: string
{
    case Matched = 'Matched';
    case NewLead = 'NewLead';
    case Conflict = 'Conflict';
    case Unresolved = 'Unresolved';
    case Ignored = 'Ignored';
}
