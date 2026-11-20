// Formato de presentación es-VE (docs/design.md §1.6). Solo muestra: el cálculo definitivo
// vive en el backend (C5).

const MINUS = '−';

const kgFormatter = new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
});

const pointsFormatter = new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
});

/** Kg con exactamente 3 decimales: "20,000". */
export function formatKg(value: string | number): string {
    return kgFormatter.format(Number(value));
}

/** Puntos con signo real: "−0,25 pts", "+2,5 pts", "0,0 pts". */
export function formatPoints(value: string | number): string {
    const number = Number(value);
    const text = pointsFormatter.format(Math.abs(number));
    const isZero = Number(text.replace(',', '.')) === 0;

    if (isZero) {
        return `${text} pts`;
    }

    return `${number < 0 ? MINUS : '+'}${text} pts`;
}

/** Los teclados es-VE escriben "4,5": se envía al servidor con punto. */
export function normalizeDecimal(value: string): string {
    return value.trim().replace(',', '.');
}
