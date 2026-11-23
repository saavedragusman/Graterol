<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import AppHeader from '../../components/AppHeader.vue';
import AppInput from '../../components/AppInput.vue';
import ListRow from '../../components/ListRow.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { formatKg, normalizeDecimal } from '../../lib/format';

interface Product {
    name: string;
}

interface Batch {
    id: number;
    code: string;
    product: Product;
}

interface Butcher {
    name: string;
}

interface OpenOrder {
    id: number;
    code: string;
    batch: Batch;
    butcher: Butcher;
    input_weight_kg: string;
}

interface AvailableBatch {
    id: number;
    code: string;
    product_name: string;
    available_weight_kg: string;
}

defineProps<{ openOrders: OpenOrder[]; availableBatches: AvailableBatch[] }>();

const page = usePage<{ errors?: Record<string, string> }>();
const errors = computed(() => page.props.errors ?? {});

const inputWeight = reactive<Record<number, string>>({});
const submitting = reactive<Record<number, boolean>>({});
const lastAttemptedBatch = ref<number | null>(null);

function openOrder(batchId: number): void {
    const weight = inputWeight[batchId];
    if (!weight || submitting[batchId]) {
        return;
    }

    submitting[batchId] = true;
    lastAttemptedBatch.value = batchId;

    router.post(
        '/butchery/orders',
        { batch_id: batchId, input_weight_kg: normalizeDecimal(weight) },
        { onFinish: () => { submitting[batchId] = false; } },
    );
}

function batchError(batchId: number): string | undefined {
    return lastAttemptedBatch.value === batchId ? errors.value.batch_id : undefined;
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <AppHeader variant="floor" title="Despiece" subtitle="Órdenes y lotes disponibles" />

        <main class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 px-4 pb-8 pt-3.5">
            <section v-if="openOrders.length > 0" class="flex flex-col gap-2">
                <h2 class="mx-0.5 text-[20px] font-bold">Órdenes en proceso</h2>
                <ul class="rounded-[20px] bg-surface py-1">
                    <li v-for="order in openOrders" :key="order.id" class="border-b border-line-soft last:border-b-0">
                        <ListRow
                            :href="`/butchery/orders/${order.id}`"
                            icon="scale"
                            tone="accent"
                            :title="order.code"
                            :subtitle="`Lote ${order.batch.code} · ${order.batch.product.name}`"
                        >
                            <StatusBadge tone="accent">En proceso</StatusBadge>
                        </ListRow>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="mx-0.5 text-[20px] font-bold">Lotes disponibles</h2>
                <p v-if="availableBatches.length === 0" class="rounded-[20px] bg-surface p-5 text-center text-[14px] text-ink-muted">
                    No hay lotes de materia prima con saldo disponible.
                </p>
                <ul class="flex flex-col gap-3">
                    <li v-for="batch in availableBatches" :key="batch.id" class="rounded-[20px] bg-surface py-2">
                        <ListRow
                            icon="package"
                            tone="brand"
                            :title="batch.product_name"
                            :subtitle="`Lote ${batch.code} · Disponible ${formatKg(batch.available_weight_kg)} kg`"
                        />
                        <div class="flex items-end gap-3 px-4 pb-2 pt-1">
                            <AppInput
                                v-model="inputWeight[batch.id]"
                                class="flex-1"
                                label="Kg de entrada"
                                numeric
                                inputmode="decimal"
                                placeholder="0,000"
                                :error="batchError(batch.id)"
                            />
                            <AppButton
                                variant="primary"
                                :disabled="!inputWeight[batch.id] || submitting[batch.id]"
                                class="mb-px"
                                @click="openOrder(batch.id)"
                            >
                                Abrir orden
                            </AppButton>
                        </div>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
