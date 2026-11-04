export type NivelRiesgo = 'bajo' | 'medio' | 'alto' | 'muy-alto';

export type NivelInfo = {
    etiqueta: string;
    descripcion: string;
    color: string;
    patron: string;
};

/**
 * Escala independiente del mapa de calor. Cada nivel combina color,
 * texto y patrón para no depender solo del color.
 */
export const NIVELES: Record<NivelRiesgo, NivelInfo> = {
    bajo: {
        etiqueta: 'Menos reportes',
        descripcion: 'Pocos reportes en el periodo',
        color: '#2e8b57',
        patron: '',
    },
    medio: {
        etiqueta: 'Reportes moderados',
        descripcion: 'Cantidad intermedia de reportes',
        color: '#f2c94c',
        patron: 'pattern-dots',
    },
    alto: {
        etiqueta: 'Muchos reportes',
        descripcion: 'Cantidad alta de reportes',
        color: '#f2994a',
        patron: 'pattern-stripes',
    },
    'muy-alto': {
        etiqueta: 'Muchísimos reportes',
        descripcion: 'Cantidad muy alta de reportes',
        color: '#d64545',
        patron: 'pattern-cross',
    },
};

export const nivelPorConteo = (conteo: number): NivelRiesgo => {
    if (conteo >= 30) {
        return 'muy-alto';
    }

    if (conteo >= 18) {
        return 'alto';
    }

    if (conteo >= 8) {
        return 'medio';
    }

    return 'bajo';
};

export const FRANJAS = [
    { id: 'todas', etiqueta: 'Toda la noche (6 p. m. – 5 a. m.)', factor: 1 },
    { id: 'tarde', etiqueta: '6 p. m. – 9 p. m.', factor: 0.3 },
    { id: 'noche', etiqueta: '9 p. m. – 12 a. m.', factor: 0.4 },
    { id: 'madrugada', etiqueta: '12 a. m. – 5 a. m.', factor: 0.3 },
] as const;

export const TIPOS_INCIDENTE = [
    { id: 'todos', etiqueta: 'Todos los tipos', factor: 1 },
    { id: 'iluminacion', etiqueta: 'Poca iluminación', factor: 0.35 },
    { id: 'acoso', etiqueta: 'Acoso verbal', factor: 0.4 },
    { id: 'seguimiento', etiqueta: 'Seguimiento o persecución', factor: 0.15 },
    { id: 'robo', etiqueta: 'Robo o intento de robo', factor: 0.1 },
] as const;

export const PERIODOS = [
    { id: '30', etiqueta: 'Últimos 30 días', factor: 1 },
    { id: '90', etiqueta: 'Últimos 90 días', factor: 2.6 },
] as const;

export type ZonaEjemplo = {
    id: string;
    nombre: string;
    reportes: number;
    /** Posición en la cuadrícula ilustrativa del mapa. */
    col: number;
    fila: number;
};

/** Datos de ejemplo para el prototipo. No representan zonas reales. */
export const ZONAS_EJEMPLO: ZonaEjemplo[] = [
    { id: 'z1', nombre: 'Zona Centro', reportes: 34, col: 1, fila: 1 },
    { id: 'z2', nombre: 'Zona Norte', reportes: 12, col: 2, fila: 0 },
    { id: 'z3', nombre: 'Zona Universitaria', reportes: 9, col: 0, fila: 0 },
    { id: 'z4', nombre: 'Zona Terminal', reportes: 22, col: 2, fila: 2 },
    { id: 'z5', nombre: 'Zona Occidental', reportes: 5, col: 0, fila: 1 },
    { id: 'z6', nombre: 'Zona Sur', reportes: 19, col: 1, fila: 2 },
    { id: 'z7', nombre: 'Zona Parques', reportes: 3, col: 2, fila: 1 },
    { id: 'z8', nombre: 'Zona Ribera', reportes: 14, col: 0, fila: 2 },
    { id: 'z9', nombre: 'Zona Comercial', reportes: 7, col: 1, fila: 0 },
];

export const FUENTE_EJEMPLO = 'Datos de ejemplo del prototipo';
export const ACTUALIZACION_EJEMPLO = '4 de noviembre de 2026';

export const CONTACTOS_EJEMPLO = [
    { id: 'c1', nombre: 'Mamá', vinculo: 'Familia', iniciales: 'MA' },
    { id: 'c2', nombre: 'Laura R.', vinculo: 'Amiga', iniciales: 'LR' },
    {
        id: 'c3',
        nombre: 'Camila G.',
        vinculo: 'Compañera de casa',
        iniciales: 'CG',
    },
];
