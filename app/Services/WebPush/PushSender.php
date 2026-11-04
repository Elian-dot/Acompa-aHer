<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;

interface PushSender
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushResult;
}
