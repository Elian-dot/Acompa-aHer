<?php

namespace App\Enums;

enum AlertKind: string
{
    case Inactivity = 'inactivity';
    case Emergency = 'emergency';
}
