import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReceiptShow from '@/pages/purchasing/ReceiptShow.vue';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { post: (...args: unknown[]) => post(...args) },
    usePage: () => ({ props: { errors: {} } }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup: (props, { slots }) => () => h('a', { href: props.href }, slots.default?.()),
    }),
}));

const purchase = {
    id: 7,
    supplier: { name: 'Proveedor de prueba' },
    supplier_invoice_number: 'F-0001',
    lines: [
        { id: 11, product: { name: 'Canal de res' }, invoiced_weight_kg: '100.000', invoiced_pieces: 2 },
    ],
};

function mountScreen() {
    const errors: unknown[] = [];
    const wrapper = mount(ReceiptShow, {
        props: { purchase },
        global: {
            config: {
                errorHandler: (error) => {
                    errors.push(error);
                },
            },
        },
    });

    return { wrapper, errors };
}

describe('ReceiptShow [BR-06]', () => {
    beforeEach(() => {
        post.mockClear();
    });

    it('[BR-06] no confirma mientras falte el peso de una línea', () => {
        const { wrapper } = mountScreen();

        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    });

    it('[BR-06] escribir el peso no rompe la pantalla y habilita la confirmación', async () => {
        const { wrapper, errors } = mountScreen();

        await wrapper.get('input[inputmode="decimal"]').setValue('98.450');

        expect(errors).toEqual([]);
        expect(wrapper.text()).toContain('Canal de res');
        expect(wrapper.get('button').attributes('disabled')).toBeUndefined();
    });

    it('[BR-06] envía el peso como texto, sin pasar por float', async () => {
        const { wrapper } = mountScreen();

        await wrapper.get('input[inputmode="decimal"]').setValue('98.450');
        await wrapper.get('button').trigger('click');

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/purchasing/receipts/7/confirm');
        expect(post.mock.calls[0][1]).toEqual({
            lines: [{ id: 11, received_weight_kg: '98.450', received_pieces: 2 }],
        });
    });
});

describe('ReceiptShow: coma decimal', () => {
    it('normaliza "98,450" a "98.450" antes de enviar', async () => {
        post.mockClear();
        const { wrapper } = mountScreen();

        await wrapper.get('input[inputmode="decimal"]').setValue('98,450');
        await wrapper.get('button').trigger('click');

        expect(post.mock.calls[0][1]).toEqual({
            lines: [{ id: 11, received_weight_kg: '98.450', received_pieces: 2 }],
        });
    });
});
