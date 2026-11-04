import { FlaskConical } from 'lucide-react';

export default function SimulationBanner({ children }: { children: string }) {
    return (
        <div
            role="note"
            className="flex items-start gap-3 rounded-2xl border border-gold/60 bg-gold/15 p-4 text-sm"
        >
            <FlaskConical
                className="mt-0.5 size-4 shrink-0 text-warning-text"
                aria-hidden="true"
            />
            <p>
                <strong className="font-semibold">Modo prototipo. </strong>
                {children}
            </p>
        </div>
    );
}
