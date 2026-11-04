import { Skeleton } from '@/components/ui/skeleton';

export default function LoadingBlock({ label }: { label: string }) {
    return (
        <div role="status" aria-label={label} className="space-y-3">
            <Skeleton className="h-6 w-1/3" />
            <Skeleton className="h-48 w-full" />
            <span className="sr-only">{label}</span>
        </div>
    );
}
