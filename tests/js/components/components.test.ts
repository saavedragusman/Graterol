import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppButton from '@/components/AppButton.vue';
import DeviationIndicator from '@/components/DeviationIndicator.vue';
import ProgressBar from '@/components/ProgressBar.vue';

vi.mock('@inertiajs/vue3', () => ({ Link: {}, usePage: () => ({ props: { errors: {} } }) }));

describe('DeviationIndicator [BR-11]', () => {
    it('dentro del umbral es success y sin ícono de alerta', () => {
        const wrapper = mount(DeviationIndicator, { props: { deviation: '-0.25', threshold: '2' } });

        expect(wrapper.attributes('data-tone')).toBe('success');
        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.text()).toBe('−0,25 pts');
    });

    it('por encima del umbral es warning con ícono de alerta', () => {
        const wrapper = mount(DeviationIndicator, { props: { deviation: '-2.9', threshold: '2' } });

        expect(wrapper.attributes('data-tone')).toBe('warning');
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('el umbral viene por prop', () => {
        const wrapper = mount(DeviationIndicator, { props: { deviation: '-2.9', threshold: '5' } });

        expect(wrapper.attributes('data-tone')).toBe('success');
    });
});

describe('ProgressBar', () => {
    it('expone los valores en aria-label', () => {
        const wrapper = mount(ProgressBar, {
            props: { segments: [{ percent: 92, tone: 'brand' }], label: 'Registrado 18,450 de 20,000 kg' },
        });

        expect(wrapper.attributes('aria-label')).toBe('Registrado 18,450 de 20,000 kg');
    });
});

describe('AppButton', () => {
    it('deshabilitado no emite click', async () => {
        const wrapper = mount(AppButton, { props: { disabled: true }, slots: { default: 'Cerrar orden' } });

        await wrapper.trigger('click');

        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.emitted('click')).toBeUndefined();
    });
});
