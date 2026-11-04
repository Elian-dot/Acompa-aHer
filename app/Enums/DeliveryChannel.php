<?php

namespace App\Enums;

enum DeliveryChannel: string
{
    case Websocket = 'websocket';
    case Push = 'push';
}
