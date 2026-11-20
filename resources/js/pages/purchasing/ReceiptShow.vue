<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import AppHeader from '../../components/AppHeader.vue';
import AppInput from '../../components/AppInput.vue';
import ListRow from '../../components/ListRow.vue';
import StickyActionBar from '../../components/StickyActionBar.vue';
import { formatKg, normalizeDecimal } from '../../lib/format';

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
                received_weight_kg: normalizeDecimal(weighed[line.id].received_weight_kg),
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
    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <AppHeader
            :title="purchase.supplier.name"
            :subtitle="`Factura ${purchase.supplier_invoice_number}`"
            back-href="/purchasing/receipts"
        />

        <main class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-3 px-4 pb-32 pt-3.5">
            <section v-for="line in purchase.lines" :key="line.id" class="rounded-[20px] bg-surface py-2">
                <ListRow
                    icon="package"
                    tone="brand"
                    :title="line.product.name"
                    :subtitle="`Facturado: ${formatKg(line.invoiced_weight_kg)} kg`"
                />
                <div class="flex gap-3 px-4 pb-2 pt-1">
                    <AppInput
                        v-model="weighed[line.id].received_weight_kg"
                        class="flex-1"
                        label="Kg recibidos"
                        numeric
                        inputmode="decimal"
                        placeholder="0,000"
                    />
                    <AppInput
                        v-if="line.invoiced_pieces !== null"
                        v-model="weighed[line.id].received_pieces"
                        class="w-28"
                        label="Piezas"
                        type="number"
                        numeric
                        inputmode="numeric"
                        step="1"
                        min="0"
                    />
                </div>
            </section>
        </main>

        <StickyActionBar>
            <AppButton variant="accent" size="lg" :disabled="!allWeighed || submitting" @click="confirm">
                Confirmar recepción
            </AppButton>
        </StickyActionBar>
    </div>
</template>
