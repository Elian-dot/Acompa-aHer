import { requestErrorMessage } from '@/lib/safety';

type ErrorBody = { message?: string; errors?: Record<string, string[]> };

/**
 * Prefiere el mensaje concreto de validación del servidor (por ejemplo, "Ya tienes un
 * acompañamiento activo") sobre el texto genérico por código de estado.
 */
export function apiErrorMessage(error: unknown): string {
    const data =
        typeof error === 'object' && error !== null && 'response' in error
            ? ((error.response as { data?: ErrorBody }).data ?? undefined)
            : undefined;
    const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
    return first ?? requestErrorMessage(error);
}
