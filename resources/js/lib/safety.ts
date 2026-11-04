export function requestErrorMessage(error: unknown): string {
    if (typeof error === 'object' && error !== null && 'code' in error && error.code === 'ERR_CANCELED') {
        return '';
    }
    const response =
        typeof error === 'object' && error !== null && 'response' in error
            ? (error.response as {
                  status?: number;
                  data?: { message?: string };
              })
            : undefined;
    switch (response?.status) {
        case 401:
            return 'Tu sesión terminó. Inicia sesión para continuar.';
        case 403:
            return 'Tu cuenta no tiene permiso para realizar esta acción.';
        case 404:
            return 'El recurso solicitado no está disponible.';
        case 409:
            return 'Otra persona ya actualizó este reporte. Actualiza la bandeja.';
        case 419:
            return 'Tu sesión caducó. Recarga la página y vuelve a intentarlo.';
        case 422:
            return 'Revisa los campos señalados antes de continuar.';
        case 429:
            return 'Has realizado varias solicitudes. Espera un momento y vuelve a intentarlo.';
        case 503:
            return 'El servicio no está disponible en este momento. Inténtalo de nuevo.';
        default:
            return 'No pudimos completar la solicitud. Revisa tu conexión e inténtalo de nuevo.';
    }
}

export const reportStatusLabel = {
    pending: 'Pendiente',
    verified: 'Verificado',
    discarded: 'Descartado',
};

export function localIncidentTimestamp(value: string): string {
    return `${value}:00-05:00`;
}
