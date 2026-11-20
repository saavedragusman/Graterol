import { describe, expect, it } from 'vitest';
import { formatDate, formatTime } from '@/lib/format';

// S11/C13: ISO en UTC, mostrado en America/Caracas (UTC-4) con el estilo de design.md §4.
describe('formatDate / formatTime', () => {
    it('convierte UTC a la fecha de Caracas', () => {
        expect(formatDate('2026-11-20T03:30:00Z')).toBe('Jueves 19 de noviembre');
    });

    it('convierte UTC a la hora de Caracas', () => {
        expect(formatTime('2026-11-20T03:30:00Z')).toBe('11:30 p. m.');
        expect(formatTime('2026-11-09T11:30:00Z')).toBe('7:30 a. m.');
    });

    it('capitaliza el día como en el diseño', () => {
        expect(formatDate('2026-11-09T16:00:00Z')).toBe('Lunes 9 de noviembre');
    });
});
