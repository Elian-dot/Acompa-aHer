import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    Clock,
    FilePenLine,
    Flame,
    HeartHandshake,
    Home,
    Lock,
    Route,
    ShieldAlert,
    User,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppUrls } from '@/hooks/use-app-urls';
import { contacts, moderation } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const urls = useAppUrls();
    const permissions = usePage().props.auth.permissions;

    const inicio: NavItem[] = [
        { title: 'Inicio', href: urls.dashboard, icon: Home },
    ];

    const planear: NavItem[] = [
        { title: 'Rutas seguras', href: urls.rutas, icon: Route },
        { title: 'Mapa de calor', href: urls.mapa, icon: Flame },
        { title: 'Horarios seguros', href: urls.horarios, icon: Clock },
    ];

    const acompanar: NavItem[] = [
        { title: 'Botón SOS', href: urls.sos, icon: ShieldAlert },
        {
            title: 'Acompañamiento',
            href: urls.acompanamiento,
            icon: HeartHandshake,
        },
        {
            title: 'Alerta por inactividad',
            href: urls.inactividad,
            icon: BellRing,
        },
    ];

    const comunidad: NavItem[] = [
        {
            title: 'Reportar un incidente',
            href: urls.reportes,
            icon: FilePenLine,
        },
        {
            title: 'Dashboard comunitario',
            href: urls.comunidad,
            icon: BarChart3,
        },
    ];

    if (permissions.includes('emergency-contact:manage')) {
        acompanar.push({
            title: 'Contactos de emergencia',
            href: contacts(),
            icon: User,
        });
    }
    if (permissions.includes('report:moderate')) {
        comunidad.push({
            title: 'Moderar reportes',
            href: moderation(),
            icon: FilePenLine,
        });
    }

    const footerNavItems: NavItem[] = [
        { title: 'Mi perfil', href: editProfile(), icon: User },
        { title: 'Privacidad y seguridad', href: editSecurity(), icon: Lock },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={urls.dashboard} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={inicio} label="Principal" />
                <NavMain items={planear} label="Planear mi trayecto" />
                <NavMain items={acompanar} label="Acompañarme" />
                <NavMain items={comunidad} label="Comunidad" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
