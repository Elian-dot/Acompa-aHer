import PublicNavigation from '@/components/ruta/public-navigation';
import Mapa from '@/pages/mapa';

export default function PublicMap(props: Parameters<typeof Mapa>[0]) {
    return (
        <PublicNavigation>
            <Mapa {...props} />
        </PublicNavigation>
    );
}
