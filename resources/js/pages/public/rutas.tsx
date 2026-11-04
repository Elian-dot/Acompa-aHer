import PublicNavigation from '@/components/ruta/public-navigation';
import Rutas from '@/pages/rutas';

export default function PublicRoutes(props: Parameters<typeof Rutas>[0]) {
    return (
        <PublicNavigation>
            <Rutas {...props} />
        </PublicNavigation>
    );
}
