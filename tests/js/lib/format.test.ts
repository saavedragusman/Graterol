import { describe, expect, it } from 'vitest';
import { formatKg, formatPoints, normalizeDecimal } from '@/lib/format';

describe('formatKg', () => {
    it('muestra siempre 3 decimales con coma', () => {
        expect(formatKg('20.000')).toBe('20,000');
        expect(formatKg(0.6)).toBe('0,600');
        expect(formatKg('18.45')).toBe('18,450');
    });
});

describe('formatPoints', () => {
    it('usa el signo menos real y coma decimal', () => {
        expect(formatPoints('-0.25')).toBe('−0,25 pts');
    });

    it('antepone + a los positivos', () => {
        expect(formatPoints('2.5')).toBe('+2,5 pts');
    });

    it('no pone signo al cero', () => {
        expect(formatPoints('0')).toBe('0,0 pts');
        expect(formatPoints('-0.001')).toBe('0,0 pts');
    });
});

describe('normalizeDecimal', () => {
    it('cambia la coma por punto', () => {
        expect(normalizeDecimal('4,5')).toBe('4.5');
        expect(normalizeDecimal('4.5')).toBe('4.5');
    });
});
