<?php

namespace App\Enums;

enum AlertStage: string
{
    case Preliminary = 'preliminary';
    case Escalated = 'escalated';
}
