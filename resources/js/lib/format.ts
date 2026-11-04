export function formatHour(hour: number): string {
    const suffix = hour < 12 ? 'a. m.' : 'p. m.';
    const twelve = hour % 12 === 0 ? 12 : hour % 12;
    return `${twelve} ${suffix}`;
}

export function formatHourRange(from: number, to: number): string {
    return from === to
        ? `${formatHour(from)} (una hora)`
        : `de ${formatHour(from)} a ${formatHour((to + 1) % 24)}`;
}

const dateFormat = new Intl.DateTimeFormat('es-CO', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'America/Bogota',
});

export function formatDate(iso: string): string {
    return dateFormat.format(new Date(iso));
}

export const confidenceLabel: Record<string, string> = {
    insufficient: 'insuficiente',
    low: 'baja',
    medium: 'media',
    high: 'alta',
};
