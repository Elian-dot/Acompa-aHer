<?php

namespace App\Enums;

enum AlertStatus: string
{
    case Preliminary = 'preliminary';
    case Escalated = 'escalated';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
