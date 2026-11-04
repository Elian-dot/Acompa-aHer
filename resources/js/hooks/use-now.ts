import { useEffect, useState } from 'react';

/** Reloj que se actualiza cada segundo, para cuentas regresivas. */
export function useNow(intervalMs = 1000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), intervalMs);
        return () => clearInterval(id);
    }, [intervalMs]);

    return now;
}
