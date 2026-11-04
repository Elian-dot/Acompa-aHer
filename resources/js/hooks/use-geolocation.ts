import { useEffect, useRef, useState } from 'react';
import type { Coordinate } from '@/types/safety';

export function useGeolocation() {
    const [message, setMessage] = useState('');
    const [locating, setLocating] = useState(false);
    const request = useRef(0);

    useEffect(
        () => () => {
            request.current += 1;
        },
        [],
    );

    function locate(onSuccess: (point: Coordinate) => void) {
        if (!window.isSecureContext) {
            setMessage(
                'Para usar tu ubicación, abre esta página mediante HTTPS. El navegador bloquea la ubicación en conexiones no seguras.',
            );
            return;
        }
        if (!navigator.geolocation) {
            setMessage(
                'Este dispositivo no permite obtener la ubicación. Puedes seleccionarla manualmente.',
            );
            return;
        }
        setLocating(true);
        setMessage(
            'Permite el acceso a tu ubicación cuando el navegador lo solicite. Buscando tu ubicación…',
        );
        const currentRequest = ++request.current;

        function success(position: GeolocationPosition) {
            if (currentRequest !== request.current) return;
            onSuccess({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
            });
            setMessage(
                `Ubicación obtenida con una precisión aproximada de ${Math.round(position.coords.accuracy)} metros. Puedes ajustarla.`,
            );
            setLocating(false);
        }

        function failure(error: GeolocationPositionError) {
            if (currentRequest !== request.current) return;
            const messages: Record<number, string> = {
                1: 'El acceso a tu ubicación está bloqueado. Abre los permisos de este sitio en el navegador y permite la ubicación. Comprueba también el permiso de ubicación del sistema.',
                2: 'El dispositivo no pudo determinar tu ubicación. Activa la ubicación del sistema y comprueba tu conexión. Puedes volver a intentarlo o seleccionar un punto en el mapa.',
                3: 'La ubicación está tardando demasiado. Comprueba que la ubicación del sistema esté activada y vuelve a intentarlo. También puedes seleccionarla en el mapa.',
            };
            setMessage(
                messages[error.code] ??
                    'No pudimos obtener tu ubicación. Puedes volver a intentarlo o seleccionarla en el mapa.',
            );
            setLocating(false);
        }

        navigator.geolocation.getCurrentPosition(
            success,
            (error) => {
                if (currentRequest !== request.current) return;
                if (error.code === 2 || error.code === 3) {
                    setMessage(
                        'Buscando una ubicación aproximada con la conexión del dispositivo…',
                    );
                    navigator.geolocation.getCurrentPosition(success, failure, {
                        enableHighAccuracy: false,
                        timeout: 15000,
                        maximumAge: 30000,
                    });
                    return;
                }
                failure(error);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    }
    return { locate, message, locating };
}
