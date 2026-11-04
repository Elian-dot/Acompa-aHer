import { CircleHelp } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    eyebrow: string;
    title: string;
    description: string;
    ayuda?: ReactNode;
};

export default function PageHeader({
    eyebrow,
    title,
    description,
    ayuda,
}: Props) {
    return (
        <header className="space-y-3">
            <p className="text-purple text-xs font-bold tracking-[0.18em] uppercase dark:text-lila">
                {eyebrow}
            </p>
            <h1 className="text-3xl leading-tight font-semibold text-balance md:text-4xl">
                {title}
            </h1>
            <p className="max-w-2xl text-base text-muted-foreground">
                {description}
            </p>
            {ayuda && (
                <details className="group max-w-2xl rounded-2xl border bg-secondary/60 px-4 py-3 text-sm">
                    <summary className="flex cursor-pointer list-none items-center gap-2 font-medium">
                        <CircleHelp
                            className="text-purple size-4 dark:text-lila"
                            aria-hidden="true"
                        />
                        ¿Cómo funciona esta sección?
                    </summary>
                    <div className="mt-2 space-y-2 text-muted-foreground">
                        {ayuda}
                    </div>
                </details>
            )}
        </header>
    );
}
