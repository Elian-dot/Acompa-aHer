import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { useOnline } from '@/hooks/use-online';
import type { LocationSample } from '@/hooks/use-journey';

export type GpsState =
    | 'idle'
    | 'waiting'
    | 'ok'
    | 'denied'
    | 'unavailable'
    | 'insecure';

const SEND_EVERY_MS = 10_000;
const MAX_QUEUED_AGE_MS = 8 * 60_000;

/**
 * Comparte la posición mientras haya un acompañamiento activo: envía la última
 * muestra cada pocos segundos, la conserva si no hay conexión y mantiene la pantalla
 * encendida para que el navegador no detenga el GPS.
 */
export function useLocationSharing(
    active: boolean,
    send: (sample: LocationSample) => Promise<boolean>,
) {
    const online = useOnline();
    const [gps, setGps] = useState<GpsState>('idle');
    const [accuracy, setAccuracy] = useState<number | null>(null);
    const [lastSentAt, setLastSentAt] = useState<number | null>(null);
    const [queued, setQueued] = useState(false);
    const latest = useRef<(LocationSample & { at: number }) | null>(null);
    const sending = useRef(false);

    const flush = useEffectEvent(async () => {
        const sample = latest.current;
        if (!sample || sending.current || !navigator.onLine) return;
        if (Date.now() - sample.at > MAX_QUEUED_AGE_MS) {
            latest.current = null;
            setQueued(false);
            return;
        }
        sending.current = true;
        const { at, ...body } = sample;
        const sent = await send(body);
        sending.current = false;
        if (sent) {
            if (latest.current?.at === at) latest.current = null;
            setQueued(latest.current !== null);
            setLastSentAt(Date.now());
        } else {
            setQueued(true);
        }
    });

    useEffect(() => {
        if (!active) {
            setGps('idle');
            return;
        }
        if (!window.isSecureContext) {
            setGps('insecure');
            return;
        }
        if (!navigator.geolocation) {
            setGps('unavailable');
            return;
        }
        setGps('waiting');
        let first = true;
        const watch = navigator.geolocation.watchPosition(
            (position) => {
                setGps('ok');
                setAccuracy(position.coords.accuracy);
                latest.current = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy_meters: position.coords.accuracy,
                    recorded_at: new Date(position.timestamp).toISOString(),
                    at: position.timestamp,
                };
                setQueued(true);
                if (first) {
                    first = false;
                    void flush();
                }
            },
            (error) => setGps(error.code === 1 ? 'denied' : 'unavailable'),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 30_000 },
        );
        const timer = setInterval(() => void flush(), SEND_EVERY_MS);
        return () => {
            navigator.geolocation.clearWatch(watch);
            clearInterval(timer);
        };
    }, [active]);

    useEffect(() => {
        if (active && online) void flush();
    }, [active, online]);

    useEffect(() => {
        if (!active || !('wakeLock' in navigator)) return;
        let lock: WakeLockSentinel | null = null;
        const request = async () => {
            try {
                lock = await navigator.wakeLock.request('screen');
            } catch {
                // El navegador puede negarlo (por ejemplo, con batería baja).
            }
        };
        const onVisible = () => {
            if (document.visibilityState === 'visible') void request();
        };
        void request();
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            document.removeEventListener('visibilitychange', onVisible);
            void lock?.release();
        };
    }, [active]);

    return { gps, accuracy, lastSentAt, queued, online };
}
