import { Link } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppUrls } from '@/hooks/use-app-urls';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const urls = useAppUrls();

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Link
                href={urls.sos}
                className="inline-flex items-center gap-2 rounded-full bg-[#b42336] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#9a1d2e]"
                aria-label="Abrir el botón SOS"
            >
                <ShieldAlert className="size-4" aria-hidden="true" />
                SOS
            </Link>
        </header>
    );
}
