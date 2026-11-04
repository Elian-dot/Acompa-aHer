import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="bg-purple text-warm-white flex aspect-square size-8 items-center justify-center rounded-xl shadow-sm">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate font-serif text-base leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
