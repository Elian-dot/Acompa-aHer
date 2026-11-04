<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class VapidPushSender implements PushSender
{
    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $configuration = config('safety.webpush');

        if (! is_string($configuration['public_key'] ?? null) || ! is_string($configuration['private_key'] ?? null)
            || $configuration['public_key'] === '' || $configuration['private_key'] === '') {
            return new PushResult(false, reason: 'push_not_configured');
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => $configuration['subject'],
                'publicKey' => $configuration['public_key'],
                'privateKey' => $configuration['private_key'],
            ]], ['TTL' => 3600, 'urgency' => 'high'], 10);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            );
        } catch (Throwable $exception) {
            report($exception);

            return new PushResult(false, reason: 'push_exception');
        }

        return new PushResult(
            $report->isSuccess(),
            $report->isSubscriptionExpired(),
            $report->isSuccess() ? null : 'push_rejected',
        );
    }
}
