<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

interface Product {
    name: string;
}

interface Batch {
    code: string;
    total_cost: string;
    product: Product;
}

interface Butcher {
    name: string;
}

interface OrderLine {
    id: number;
    cut_product: Product;
    expected_yield_percent: string;
    expected_weight_kg: string;
    actual_weight_kg: string | null;
    deviation_percent: string | null;
    allocated_cost: string | null;
}

interface Order {
    id: number;
    code: string;
    status: string;
    input_weight_kg: string;
    batch: Batch;
    butcher: Butcher;
    lines: OrderLine[];
}

const props = defineProps<{ order: Order; batchTotalCostDisplay: string; deviationThresholdPercent: string }>();

// BR-09: el carnicero pesa cada corte; puede ser 0.
const weightInput = reactive<Record<number, string>>(
    Object.fromEntries(props.order.lines.map((line) => [line.id, ''])),
);
const registering = reactive<Record<number, boolean>>({});
const closing = ref(false);

const weighedCount = computed(() => props.order.lines.filter((line) => line.actual_weight_kg !== null).length);
const allWeighed = computed(() => weighedCount.value === props.order.lines.length);
const isOpen = computed(() => props.order.status === 'open');

function registerWeight(lineId: number): void {
    const weight = weightInput[lineId];
    if (weight === '' || registering[lineId]) {
        return;
    }

    registering[lineId] = true;

    router.patch(
        `/butchery/orders/${props.order.id}/lines/${lineId}`,
        { actual_weight_kg: weight },
        { onFinish: () => { registering[lineId] = false; } },
    );
}

function closeOrder(): void {
    if (!allWeighed.value || closing.value) {
        return;
    }

    closing.value = true;

    router.post(
        `/butchery/orders/${props.order.id}/close`,
        {},
        { onFinish: () => { closing.value = false; } },
    );
}

// BR-11: se alerta cuando un corte se desvía más que el umbral configurado.
function deviationColor(deviationPercent: string | null): string {
    if (deviationPercent === null) {
        return 'text-ink-muted';
    }

    const magnitude = Math.abs(Number(deviationPercent));

    return magnitude > Number(props.deviationThresholdPercent) ? 'text-warning' : 'text-success';
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas">
        <header class="flex flex-col gap-3 border-b border-line bg-surface px-4 py-4">
            <div class="flex items-center gap-3">
                <Link href="/butchery/orders" class="text-sm text-ink-muted">‹ Volver</Link>
                <div class="flex-1">
                    <h1 class="text-[22px] font-bold text-ink">{{ order.code }}</h1>
                    <p class="text-[13px] text-ink-muted">Lote {{ order.batch.code }} · {{ order.batch.product.name }}</p>
                </div>
                <span class="rounded-full bg-accent px-3 py-1 text-[12px] font-bold text-on-accent">
                    {{ order.status === 'open' ? 'En proceso' : order.status === 'closed' ? 'Cerrada' : 'Anulada' }}
                </span>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-md bg-canvas p-3">
                    <span class="block text-[12px] text-ink-muted">Entrada</span>
                    <strong class="text-[17px] text-ink">{{ order.input_weight_kg }} kg</strong>
                </div>
                <div class="rounded-md bg-canvas p-3">
                    <span class="block text-[12px] text-ink-muted">Costo lote</span>
                    <strong class="text-[17px] text-ink">{{ batchTotalCostDisplay }}</strong>
                </div>
                <div class="rounded-md bg-canvas p-3">
                    <span class="block text-[12px] text-ink-muted">Carnicero</span>
                    <strong class="text-[17px] text-ink">{{ order.butcher.name }}</strong>
                </div>
            </div>
        </header>

        <main class="flex-1 space-y-3 overflow-y-auto p-4 pb-28">
            <div class="rounded-md border border-line bg-surface p-4">
                <div class="mb-2 flex items-center justify-between text-[14px]">
                    <span class="font-bold text-ink">Registrado</span>
                    <span class="text-ink-muted">{{ weighedCount }} de {{ order.lines.length }} cortes pesados</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-canvas">
                    <div
                        class="h-full bg-brand"
                        :style="{ width: `${order.lines.length > 0 ? (weighedCount / order.lines.length) * 100 : 0}%` }"
                    />
                </div>
            </div>

            <h2 class="text-[15px] font-bold text-ink">Cortes · real frente a plantilla</h2>

            <div class="divide-y divide-line rounded-md border border-line bg-surface">
                <div v-for="line in order.lines" :key="line.id" class="flex items-center gap-3 p-4">
                    <div class="flex-1">
                        <strong class="block text-[15px] text-ink">{{ line.cut_product.name }}</strong>
                        <span class="block text-[13px] text-ink-muted">
                            Esperado {{ line.expected_weight_kg }} kg · {{ line.expected_yield_percent }} %
                        </span>
                    </div>

                    <template v-if="line.actual_weight_kg !== null">
                        <div class="text-right">
                            <strong class="block text-[17px] text-ink">{{ line.actual_weight_kg }}</strong>
                            <span v-if="line.deviation_percent !== null" :class="['block text-[12px] font-bold', deviationColor(line.deviation_percent)]">
                                {{ line.deviation_percent }} pts
                            </span>
                        </div>
                    </template>
                    <template v-else-if="isOpen">
                        <input
                            v-model="weightInput[line.id]"
                            type="text"
                            inputmode="decimal"
                            placeholder="0.000"
                            class="w-28 rounded-md border-2 border-brand px-3 py-2 text-right text-[16px] font-bold text-ink focus:outline-none"
                        >
                        <button
                            type="button"
                            :disabled="weightInput[line.id] === '' || registering[line.id]"
                            class="rounded-md bg-accent px-3 py-2 text-[13px] font-bold text-on-accent disabled:bg-canvas disabled:text-ink-muted"
                            @click="registerWeight(line.id)"
                        >
                            Registrar
                        </button>
                    </template>
                </div>
            </div>

            <div v-if="order.status === 'closed'" class="rounded-md border border-line bg-surface p-4 text-[14px] text-ink">
                Orden cerrada. El costo se repartió por valor de mercado de cada corte; la merma no lleva costo.
            </div>
        </main>

        <div v-if="isOpen" class="fixed inset-x-0 bottom-0 border-t border-line bg-surface p-4">
            <button
                type="button"
                :disabled="!allWeighed || closing"
                class="min-h-[56px] w-full rounded-md bg-accent font-bold text-on-accent disabled:bg-canvas disabled:text-ink-muted"
                @click="closeOrder"
            >
                Cerrar orden
            </button>
        </div>
    </div>
</template>
