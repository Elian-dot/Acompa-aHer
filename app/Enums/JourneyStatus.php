<?php

namespace App\Enums;

enum JourneyStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
