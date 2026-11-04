import { Link } from '@inertiajs/react';
import { Lock, MapPinned, Users } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { usePage } from '@inertiajs/react';

const PUNTOS = [
    {
        icono: MapPinned,
        texto: 'Información de rutas y zonas para decidir con más calma.',
    },
    {
        icono: Users,
        texto: 'Acompañamiento solo con las personas que tú elijas.',
    },
    { icono: Lock, texto: 'Tu ubicación y tus datos, bajo tu control.' },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[1.05fr_1fr]">
            <a
                href="#contenido"
                className="focus:bg-purple focus:text-warm-white sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:px-4 focus:py-2"
            >
                Saltar al contenido
            </a>

            <aside className="bg-brand-gradient text-warm-white relative hidden flex-col justify-between p-12 lg:flex">
                <Link
                    href={home()}
                    className="flex items-center gap-3 font-serif text-2xl font-semibold"
                >
                    <span className="bg-purple flex size-10 items-center justify-center rounded-xl">
                        <AppLogoIcon className="size-6 fill-current" />
                    </span>
                    {name}
                </Link>

                <div className="max-w-md space-y-8">
                    <h2 className="font-serif text-4xl leading-tight font-semibold text-balance">
                        Un camino de regreso a casa, con{' '}
                        <em className="text-lila not-italic">información.</em>
                    </h2>
                    <ul className="space-y-4">
                        {PUNTOS.map(({ icono: Icono, texto }) => (
                            <li
                                key={texto}
                                className="text-warm-white/90 flex items-start gap-3"
                            >
                                <span className="bg-warm-white/10 mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full">
                                    <Icono
                                        className="size-4 text-gold"
                                        aria-hidden="true"
                                    />
                                </span>
                                {texto}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="text-warm-white/70 max-w-md text-sm">
                    Esta herramienta orienta; no promete protección absoluta ni
                    reemplaza a las autoridades. Si hay peligro inmediato, llama
                    al 123.
                </p>
            </aside>

            <main
                id="contenido"
                className="flex items-center justify-center p-6 md:p-10"
            >
                <div className="w-full max-w-md space-y-8">
                    <Link
                        href={home()}
                        className="flex items-center justify-center gap-2 font-serif text-xl font-semibold lg:hidden"
                    >
                        <span className="bg-purple text-warm-white flex size-9 items-center justify-center rounded-xl">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
                    </Link>

                    <div className="space-y-2 text-center lg:text-left">
                        <h1 className="text-3xl font-semibold text-balance">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    <div className="rounded-3xl border bg-card p-6 shadow-sm md:p-8">
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
