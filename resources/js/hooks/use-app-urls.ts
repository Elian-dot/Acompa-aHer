import { usePage } from '@inertiajs/react';
import {
    acompanamiento,
    comunidad,
    dashboard,
    horarios,
    inactividad,
    mapa,
    reportes,
    rutas,
    sos,
} from '@/routes';

type Resolver = (slug: string) => { url: string };

/**
 * Direcciones de los módulos de la aplicación para el equipo actual.
 */
export function useAppUrls() {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug;

    const resolve = (route: Resolver): string => (slug ? route(slug).url : '/');

    return {
        dashboard: resolve(dashboard),
        rutas: resolve(rutas),
        mapa: resolve(mapa),
        horarios: resolve(horarios),
        reportes: resolve(reportes),
        sos: resolve(sos),
        acompanamiento: resolve(acompanamiento),
        inactividad: resolve(inactividad),
        comunidad: resolve(comunidad),
    };
}
