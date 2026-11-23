<script setup lang="ts">
import { reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';

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

const inputWeight = reactive<Record<number, string>>({});
const submitting = reactive<Record<number, boolean>>({});

function openOrder(batchId: number): void {
    const weight = inputWeight[batchId];
    if (!weight || submitting[batchId]) {
        return;
    }

    submitting[batchId] = true;

    router.post(
        '/butchery/orders',
        { batch_id: batchId, input_weight_kg: weight },
        { onFinish: () => { submitting[batchId] = false; } },
    );
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas">
        <header class="border-b border-line bg-surface px-4 py-4">
            <h1 class="text-[22px] font-bold text-ink">Despiece</h1>
        </header>

        <main class="flex-1 space-y-4 overflow-y-auto p-4 pb-8">
            <section v-if="openOrders.length > 0">
                <h2 class="mb-2 text-[15px] font-bold text-ink">Órdenes en proceso</h2>
                <ul class="space-y-2">
                    <li
                        v-for="order in openOrders"
                        :key="order.id"
                        class="rounded-md border border-line bg-surface p-4"
                    >
                        <Link :href="`/butchery/orders/${order.id}`" class="flex items-center justify-between">
                            <span>
                                <span class="block text-[15px] font-bold text-ink">{{ order.code }}</span>
                                <span class="block text-[13px] text-ink-muted">
                                    Lote {{ order.batch.code }} · {{ order.batch.product.name }}
                                </span>
                            </span>
                            <span class="rounded-full bg-accent-soft px-3 py-1 text-[12px] font-bold text-on-accent-soft">
                                En proceso
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section>
                <h2 class="mb-2 text-[15px] font-bold text-ink">Lotes disponibles</h2>
                <p v-if="availableBatches.length === 0" class="text-[13px] text-ink-muted">
                    No hay lotes de materia prima con saldo disponible.
                </p>
                <ul class="space-y-2">
                    <li
                        v-for="batch in availableBatches"
                        :key="batch.id"
                        class="rounded-md border border-line bg-surface p-4"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-[15px] font-bold text-ink">{{ batch.product_name }}</span>
                            <span class="text-[13px] text-ink-muted">Lote {{ batch.code }}</span>
                        </div>
                        <p class="mb-2 text-[13px] text-ink-muted">
                            Disponible: {{ batch.available_weight_kg }} kg
                        </p>
                        <div class="flex gap-3">
                            <input
                                v-model="inputWeight[batch.id]"
                                type="text"
                                inputmode="decimal"
                                placeholder="Kg de entrada"
                                class="flex-1 rounded-md border border-line px-3 py-2 text-[16px] text-ink focus:border-2 focus:border-brand focus:outline-none"
                            >
                            <button
                                type="button"
                                :disabled="!inputWeight[batch.id] || submitting[batch.id]"
                                class="rounded-md bg-accent px-4 font-bold text-on-accent disabled:bg-canvas disabled:text-ink-muted"
                                @click="openOrder(batch.id)"
                            >
                                Abrir orden
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
