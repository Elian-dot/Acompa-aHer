import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import { apiErrorMessage } from '@/lib/api-errors';
import { current, finish, store } from '@/routes/api/v1/journeys';
import { store as activityStore } from '@/routes/api/v1/journeys/activity';
import { store as locationStore } from '@/routes/api/v1/journeys/locations';
import { destroy, renew } from '@/routes/api/v1/journeys/participants';
import type { Coordinate } from '@/types/safety';
import type { JourneyState, ShareEntry } from '@/types/journey';

export type StartPayload = {
    origin: Coordinate;
    destination: Coordinate;
    contact_ids: number[];
    inactivity_minutes: number;
    share_hours: number;
    consent: boolean;
};

export type LocationSample = Coordinate & {
    accuracy_meters: number;
    recorded_at: string;
};

type Envelope = { data: JourneyState; share?: ShareEntry[] };

/**
 * Estado del acompañamiento activo de la usuaria y las acciones que lo modifican.
 * `journey` es `undefined` mientras carga y `null` si no hay un trayecto activo.
 */
export function useJourney({ pollMs = 15000 }: { pollMs?: number } = {}) {
    const poll = useHttp<Record<string, never>, { data: JourneyState | null }>(
        {},
    );
    const ping = useHttp<
        Record<string, never>,
        Envelope & { meta: { counted_as_activity: boolean } }
    >({});
    const action = useHttp<Record<string, never>, Envelope>({});
    const [journey, setJourney] = useState<JourneyState | null | undefined>(
        undefined,
    );
    const [share, setShare] = useState<ShareEntry[]>([]);
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);

    const refresh = useEffectEvent(async () => {
        try {
            const response = await poll.get(current.url());
            setJourney(response.data);
        } catch (error) {
            const text = apiErrorMessage(error);
            if (text) setMessage(text);
        }
    });
    const cancelPoll = useEffectEvent(() => poll.cancel());

    useEffect(() => {
        void refresh();
        const id = setInterval(() => void refresh(), pollMs);
        return () => {
            clearInterval(id);
            cancelPoll();
        };
    }, [pollMs]);

    async function run(request: () => Promise<Envelope>): Promise<boolean> {
        setBusy(true);
        setMessage('');
        try {
            const response = await request();
            setJourney(response.data);
            if (response.share) {
                setShare((previous) => [
                    ...previous.filter(
                        (entry) =>
                            !response.share?.some(
                                (fresh) =>
                                    fresh.participant_id ===
                                    entry.participant_id,
                            ),
                    ),
                    ...(response.share ?? []),
                ]);
            }
            return true;
        } catch (error) {
            setMessage(apiErrorMessage(error));
            return false;
        } finally {
            setBusy(false);
        }
    }

    const start = (payload: StartPayload) => {
        action.transform(() => ({ ...payload }));
        return run(() => action.post(store.url()));
    };

    const confirm = (pauseMinutes?: number) => {
        if (!journey) return Promise.resolve(false);
        action.transform(() => ({ pause_minutes: pauseMinutes ?? null }));
        return run(() => action.post(activityStore.url(journey.id)));
    };

    const close = async (outcome: 'completed' | 'cancelled') => {
        if (!journey) return false;
        action.transform(() => ({ outcome }));
        const done = await run(() => action.post(finish.url(journey.id)));
        if (done) {
            setJourney(null);
            setShare([]);
        }
        return done;
    };

    const revoke = (participantId: number) => {
        if (!journey) return Promise.resolve(false);
        action.transform(() => ({}));
        return run(() =>
            action.delete(
                destroy.url({
                    journey: journey.id,
                    participant: participantId,
                }),
            ),
        );
    };

    const renewLink = (participantId: number) => {
        if (!journey) return Promise.resolve(false);
        action.transform(() => ({}));
        return run(() =>
            action.post(
                renew.url({ journey: journey.id, participant: participantId }),
            ),
        );
    };

    async function sendLocation(sample: LocationSample): Promise<boolean> {
        if (!journey) return false;
        ping.transform(() => ({ ...sample }));
        try {
            const response = await ping.post(locationStore.url(journey.id));
            setJourney(response.data);
            return true;
        } catch (error) {
            const text = apiErrorMessage(error);
            if (text) setMessage(text);
            return false;
        }
    }

    return {
        journey,
        share,
        message,
        busy,
        refresh: () => refresh(),
        start,
        confirm,
        close,
        revoke,
        renewLink,
        sendLocation,
    };
}
