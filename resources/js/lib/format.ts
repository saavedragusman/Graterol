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

const percentFormatter = new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Porcentaje es-VE con 2 decimales y espacio antes del %: "99,00 %". */
export function formatPercent(value: string | number): string {
    return `${percentFormatter.format(Number(value))} %`;
}

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

/** Zona de presentación fija (C13): se guarda en UTC y se muestra en Caracas. */
export const DISPLAY_TIMEZONE = 'America/Caracas';

const dateFormatter = new Intl.DateTimeFormat('es-VE', {
    timeZone: DISPLAY_TIMEZONE,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const timeFormatter = new Intl.DateTimeFormat('es-VE', {
    timeZone: DISPLAY_TIMEZONE,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

function capitalize(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Fecha en Caracas, estilo design.md §4: "Jueves 19 de noviembre". */
export function formatDate(value: string | Date): string {
    const text = dateFormatter.format(new Date(value)).replace(',', '');

    return capitalize(text);
}

/** Hora en Caracas, estilo design.md §4: "7:30 a. m.". */
export function formatTime(value: string | Date): string {
    return timeFormatter.format(new Date(value)).replace(/\u202f|\u00a0/g, ' ');
}
