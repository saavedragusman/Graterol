<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

interface Product {
    name: string;
}

interface PurchaseLine {
    id: number;
    product: Product;
    invoiced_weight_kg: string;
    invoiced_pieces: number | null;
}

interface Supplier {
    name: string;
}

interface Purchase {
    id: number;
    supplier: Supplier;
    supplier_invoice_number: string;
    lines: PurchaseLine[];
}

const props = defineProps<{ purchase: Purchase }>();

const weighed = reactive<Record<number, { received_weight_kg: string; received_pieces: string }>>(
    Object.fromEntries(
        props.purchase.lines.map((line) => [
            line.id,
            { received_weight_kg: '', received_pieces: line.invoiced_pieces !== null ? String(line.invoiced_pieces) : '' },
        ]),
    ),
);

// BR-06: toda recepción se pesa; no se confirma hasta que todas las líneas tengan peso.
const allWeighed = computed(() =>
    props.purchase.lines.every((line) => weighed[line.id].received_weight_kg.trim() !== ''),
);

const submitting = ref(false);

function confirm(): void {
    if (!allWeighed.value || submitting.value) {
        return;
    }

    submitting.value = true;

    router.post(
        `/purchasing/receipts/${props.purchase.id}/confirm`,
        {
            lines: props.purchase.lines.map((line) => ({
                id: line.id,
                received_weight_kg: weighed[line.id].received_weight_kg,
                received_pieces: weighed[line.id].received_pieces === '' ? null : Number(weighed[line.id].received_pieces),
            })),
        },
        {
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas">
        <header class="flex items-center gap-3 border-b border-line bg-surface px-4 py-3">
            <Link href="/purchasing/receipts" class="text-sm text-ink-muted">‹ Volver</Link>
            <div>
                <h1 class="text-[15px] font-bold text-ink">{{ purchase.supplier.name }}</h1>
                <p class="text-[13px] text-ink-muted">Factura {{ purchase.supplier_invoice_number }}</p>
            </div>
        </header>

        <main class="flex-1 space-y-3 overflow-y-auto p-4 pb-28">
            <div
                v-for="line in purchase.lines"
                :key="line.id"
                class="rounded-md border border-line bg-surface p-4"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-[15px] font-bold text-ink">{{ line.product.name }}</span>
                    <span class="text-[13px] text-ink-muted">Facturado: {{ line.invoiced_weight_kg }} kg</span>
                </div>

                <div class="flex gap-3">
                    <label class="flex-1 text-[13px] text-ink-muted">
                        Kg recibidos
                        <input
                            v-model="weighed[line.id].received_weight_kg"
                            type="number"
                            step="0.001"
                            min="0.001"
                            inputmode="decimal"
                            class="mt-1 block w-full rounded-md border border-line px-3 py-2 text-[16px] text-ink focus:border-2 focus:border-brand focus:outline-none"
                            placeholder="0.000"
                        >
                    </label>
                    <label v-if="line.invoiced_pieces !== null" class="w-28 text-[13px] text-ink-muted">
                        Piezas
                        <input
                            v-model="weighed[line.id].received_pieces"
                            type="number"
                            step="1"
                            min="0"
                            inputmode="numeric"
                            class="mt-1 block w-full rounded-md border border-line px-3 py-2 text-[16px] text-ink focus:border-2 focus:border-brand focus:outline-none"
                        >
                    </label>
                </div>
            </div>
        </main>

        <div class="fixed inset-x-0 bottom-0 border-t border-line bg-surface p-4">
            <button
                type="button"
                :disabled="!allWeighed || submitting"
                class="min-h-[56px] w-full rounded-md bg-accent font-bold text-on-accent disabled:bg-canvas disabled:text-ink-muted"
                @click="confirm"
            >
                Confirmar recepción
            </button>
        </div>
    </div>
</template>
