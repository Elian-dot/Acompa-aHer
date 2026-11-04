const clock = new Intl.DateTimeFormat('es-CO', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'America/Bogota',
});

export function formatClock(iso: string): string {
    return clock.format(new Date(iso));
}

/** "hace 3 min", "hace unos segundos"... a partir de dos instantes. */
export function ago(iso: string, nowMs: number): string {
    const seconds = Math.max(
        0,
        Math.round((nowMs - new Date(iso).getTime()) / 1000),
    );
    if (seconds < 20) return 'hace unos segundos';
    if (seconds < 90) return `hace ${seconds} s`;
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    return `hace ${hours} h ${minutes % 60} min`;
}

/** "04:07" para cuentas regresivas. */
export function countdown(milliseconds: number): string {
    const total = Math.max(0, Math.ceil(milliseconds / 1000));
    const minutes = Math.floor(total / 60);
    return `${String(minutes).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}
