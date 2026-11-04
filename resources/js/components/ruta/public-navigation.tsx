import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { home, login, register } from '@/routes';
import { mapa, rutas } from '@/routes/public';

export default function PublicNavigation({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <>
            <header className="border-b bg-card px-4 py-4">
                <nav
                    aria-label="Navegación pública"
                    className="mx-auto flex max-w-6xl flex-wrap items-center gap-5"
                >
                    <Link
                        href={home()}
                        className="mr-auto flex items-center gap-2"
                    >
                        <AppLogo />
                    </Link>
                    <Link href={mapa()} className="text-sm font-semibold">
                        Mapa
                    </Link>
                    <Link href={rutas()} className="text-sm font-semibold">
                        Rutas peatonales
                    </Link>
                    <Link href={login()} className="text-sm font-semibold">
                        Iniciar sesión
                    </Link>
                    <Link
                        href={register()}
                        className="text-sm font-semibold underline"
                    >
                        Crear cuenta
                    </Link>
                </nav>
            </header>
            <main>{children}</main>
        </>
    );
}
