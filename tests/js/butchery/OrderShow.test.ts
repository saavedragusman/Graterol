import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import OrderShow from '@/pages/butchery/OrderShow.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { patch: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: { errors: {} } }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup: (props, { slots }) => () => h('a', { href: props.href }, slots.default?.()),
    }),
}));

const order = {
    id: 5,
    code: 'DSP-0005',
    status: 'open',
    input_weight_kg: '20.000',
    expected_shrinkage_percent: '1.00',
    batch: { code: 'L-0001', total_cost: '125.0000', product: { name: 'Pollo entero' } },
    butcher: { name: 'Carlos' },
    lines: [
        {
            id: 1,
            cut_product: { name: 'Filete' },
            expected_yield_percent: '99.00',
            expected_weight_kg: '19.800',
            actual_weight_kg: null,
            deviation_percent: null,
            allocated_cost: null,
        },
    ],
};

function mountScreen(inputCost: { usd: string | null; bs: string | null; rate: string | null }) {
    return mount(OrderShow, {
        props: { order, inputCost, deviationThresholdPercent: '2.00' },
    });
}

describe('OrderShow: costo de la entrada [DEC-07]', () => {
    it('muestra el costo de la entrada en $ y Bs, y el $ en la frase del cierre', () => {
        const wrapper = mountScreen({ usd: '$ 25,51', bs: 'Bs 2.181,11', rate: 'Bs 85,50' });

        expect(wrapper.text()).toContain('Costo lote');
        expect(wrapper.find('[data-line="usd"]').text()).toBe('$ 25,51');
        expect(wrapper.find('[data-line="bs"]').text()).toContain('Bs 2.181,11');
        expect(wrapper.text()).toContain('Al cerrar, el costo de $ 25,51 se reparte');
    });

    it('sin tasa solo muestra la moneda original', () => {
        const wrapper = mountScreen({ usd: null, bs: 'Bs 25,51', rate: null });

        expect(wrapper.find('[data-line="usd"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Al cerrar, el costo de Bs 25,51 se reparte');
    });

    it('muestra el % esperado en es-VE', () => {
        const wrapper = mountScreen({ usd: '$ 25,51', bs: null, rate: null });

        expect(wrapper.text()).toContain('99,00 %');
        expect(wrapper.text()).not.toContain('99.00 %');
    });
});
