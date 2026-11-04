<?php

namespace App\Actions\Journeys;

use App\Models\JourneyParticipant;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StorePushSubscription
{
    private const MAX_PER_OWNER = 5;

    /**
     * Registra el dispositivo de una usuaria o de un contacto. Un mismo endpoint pertenece
     * a un solo destinatario: si cambia de dueña se reasigna.
     *
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string|null, expirationTime?: int|float|null}  $data
     */
    public function handle(array $data, ?User $user, ?JourneyParticipant $participant): PushSubscription
    {
        return DB::transaction(function () use ($data, $user, $participant): PushSubscription {
            $subscription = PushSubscription::query()->firstOrNew(['endpoint_hash' => hash('sha256', $data['endpoint'])]);
            $subscription->forceFill([
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'expires_at' => isset($data['expirationTime']) ? CarbonImmutable::createFromTimestampMs((int) $data['expirationTime']) : null,
                'user_id' => $user?->id,
                'journey_participant_id' => $participant?->id,
            ])->save();

            $owned = PushSubscription::query()
                ->when($user, fn ($query) => $query->where('user_id', $user->id))
                ->when($participant, fn ($query) => $query->where('journey_participant_id', $participant->id));
            $surplus = (clone $owned)->orderByDesc('id')->skip(self::MAX_PER_OWNER)->take(PHP_INT_MAX)->pluck('id');
            PushSubscription::query()->whereIn('id', $surplus)->delete();

            return $subscription;
        });
    }
}
