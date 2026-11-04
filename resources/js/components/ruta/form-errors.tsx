export default function FormErrors({
    errors,
    message,
}: {
    errors?: Record<string, string | string[] | undefined>;
    message?: string;
}) {
    const entries = Object.entries(errors ?? {}).filter(([, value]) =>
        Boolean(value),
    );
    if (!message && entries.length === 0) return null;
    return (
        <div
            role="alert"
            className="rounded-2xl border border-destructive/50 bg-destructive/10 p-4 text-sm"
        >
            {message && <p className="font-semibold">{message}</p>}
            {entries.length > 0 && (
                <ul className="mt-2 list-disc space-y-1 pl-5">
                    {entries.map(([key, value]) => (
                        <li key={key}>
                            {Array.isArray(value) ? value.join(' ') : value}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
