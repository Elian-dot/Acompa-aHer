import { useState } from 'react';
import type { Coordinate } from '@/types/safety';

export function useGeolocation() {
    const [message, setMessage] = useState('');
    const [locating, setLocating] = useState(false);

    function locate(onSuccess: (point: Coordinate) => void) {
        if (!navigator.geolocation) {
            setMessage(
                'Este dispositivo no permite obtener la ubicación. Puedes seleccionarla manualmente.',
            );
            return;
        }
        setLocating(true);
        setMessage('Buscando tu ubicación…');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                onSuccess({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                });
                setMessage(
                    `Ubicación obtenida con una precisión aproximada de ${Math.round(position.coords.accuracy)} metros. Puedes ajustarla.`,
                );
                setLocating(false);
            },
            () => {
                setMessage(
                    'No pudimos obtener tu ubicación. Puedes seleccionarla manualmente.',
                );
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    }
    return { locate, message, locating };
}
