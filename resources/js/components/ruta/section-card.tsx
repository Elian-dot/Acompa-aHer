import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    headingId?: string;
    action?: ReactNode;
};

export default function SectionCard({
    title,
    description,
    children,
    className,
    headingId,
    action,
}: Props) {
    return (
        <section
            aria-labelledby={headingId}
            className={cn(
                'rounded-3xl border bg-card p-5 shadow-sm md:p-6',
                className,
            )}
        >
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 id={headingId} className="text-xl font-semibold">
                        {title}
                    </h2>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
