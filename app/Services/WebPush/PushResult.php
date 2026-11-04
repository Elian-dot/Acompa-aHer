<?php

namespace App\Services\WebPush;

/**
 * Resultado de enviar una notificación a una suscripción.
 */
final readonly class PushResult
{
    public function __construct(
        public bool $successful,
        public bool $subscriptionExpired = false,
        public ?string $reason = null,
    ) {}
}
