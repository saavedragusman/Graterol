<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppButton from '../../components/AppButton.vue';
import AppHeader from '../../components/AppHeader.vue';
import AppIcon from '../../components/AppIcon.vue';
import AppInput from '../../components/AppInput.vue';
import AppNotice from '../../components/AppNotice.vue';
import DeviationIndicator from '../../components/DeviationIndicator.vue';
import MoneyAmount from '../../components/MoneyAmount.vue';
import HeaderStat from '../../components/HeaderStat.vue';
import ProgressBar from '../../components/ProgressBar.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import StickyActionBar from '../../components/StickyActionBar.vue';
import { isAboveThreshold } from '../../lib/deviation';
import { formatKg, formatPercent, normalizeDecimal } from '../../lib/format';

interface DualMoney {
    usd: string | null;
    bs: string | null;
    rate: string | null;
}

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
    expected_shrinkage_percent: string;
    batch: Batch;
    butcher: Butcher;
    lines: OrderLine[];
}

const props = defineProps<{ order: Order; inputCost: DualMoney; deviationThresholdPercent: string }>();

const page = usePage<{ errors?: Record<string, string> }>();
const errors = computed(() => page.props.errors ?? {});

// BR-09: el carnicero pesa cada corte; puede ser 0.
const weightInput = reactive<Record<number, string>>(
    Object.fromEntries(props.order.lines.map((line) => [line.id, ''])),
);
const registering = reactive<Record<number, boolean>>({});
const lastAttemptedLine = ref<number | null>(null);
const closing = ref(false);

const weighedCount = computed(() => props.order.lines.filter((line) => line.actual_weight_kg !== null).length);
const allWeighed = computed(() => weighedCount.value === props.order.lines.length);
const isOpen = computed(() => props.order.status === 'open');

// Solo presentación (C5): el costo y los rendimientos definitivos los calcula el servidor.
const inputKg = computed(() => Number(props.order.input_weight_kg));
const registeredKg = computed(() =>
    props.order.lines.reduce((sum, line) => sum + (line.actual_weight_kg !== null ? Number(line.actual_weight_kg) : 0), 0),
);
const expectedShrinkageKg = computed(() => (inputKg.value * Number(props.order.expected_shrinkage_percent)) / 100);
const percentOfInput = (kg: number): number => (inputKg.value > 0 ? (kg / inputKg.value) * 100 : 0);

const progressSegments = computed(() => [
    { percent: percentOfInput(registeredKg.value), tone: 'brand' as const },
    { percent: percentOfInput(expectedShrinkageKg.value), tone: 'neutral' as const },
]);
const progressLabel = computed(
    () =>
        `Registrado ${formatKg(registeredKg.value)} de ${formatKg(inputKg.value)} kg; ` +
        `merma esperada ${formatKg(expectedShrinkageKg.value)} kg`,
);

const statusLabel = computed(() => (props.order.status === 'open' ? 'En proceso' : props.order.status === 'closed' ? 'Cerrada' : 'Anulada'));
const statusTone = computed(() => (props.order.status === 'open' ? 'accent' : props.order.status === 'closed' ? 'success' : 'neutral'));

function registerWeight(lineId: number): void {
    const weight = weightInput[lineId];
    if (weight === '' || registering[lineId]) {
        return;
    }

    registering[lineId] = true;
    lastAttemptedLine.value = lineId;

    router.patch(
        `/butchery/orders/${props.order.id}/lines/${lineId}`,
        { actual_weight_kg: normalizeDecimal(weight) },
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

function lineError(lineId: number): string | undefined {
    return lastAttemptedLine.value === lineId ? errors.value.actual_weight_kg : undefined;
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <AppHeader
            variant="floor"
            :title="order.code"
            :subtitle="`Lote ${order.batch.code} · ${order.batch.product.name}`"
            back-href="/butchery/orders"
        >
            <template #trailing>
                <StatusBadge :tone="statusTone">{{ statusLabel }}</StatusBadge>
            </template>
            <HeaderStat label="Entrada" :value="`${formatKg(order.input_weight_kg)} kg`" />
            <HeaderStat label="Costo lote">
                <MoneyAmount :usd="inputCost.usd" :bs="inputCost.bs" :rate="inputCost.rate" tone="on-dark" />
            </HeaderStat>
            <HeaderStat label="Carnicero" :value="order.butcher.name" />
        </AppHeader>

        <main class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-3 px-4 pb-32 pt-3.5">
            <section class="flex flex-col gap-2 rounded-[20px] bg-surface px-4 py-3.5 tabular-nums">
                <div class="flex items-baseline justify-between">
                    <span class="text-[14px] font-semibold">Registrado</span>
                    <span class="text-[14px]">
                        <strong class="text-[22px] font-bold">{{ formatKg(registeredKg) }}</strong>
                        <span class="text-ink-muted"> de {{ formatKg(order.input_weight_kg) }} kg</span>
                    </span>
                </div>
                <ProgressBar :segments="progressSegments" :label="progressLabel" />
                <div class="flex justify-between text-[13px] text-ink-muted">
                    <span>{{ weighedCount }} de {{ order.lines.length }} cortes pesados</span>
                    <span>Merma esperada {{ formatKg(expectedShrinkageKg) }} kg</span>
                </div>
            </section>

            <h2 class="mx-0.5 mt-1 text-[20px] font-bold">Cortes · real frente a plantilla</h2>

            <section class="flex flex-col rounded-[20px] bg-surface py-1 tabular-nums">
                <div
                    v-for="line in order.lines"
                    :key="line.id"
                    :class="[
                        'flex min-h-16 flex-col justify-center gap-2 border-b border-line-soft px-4 py-2 last:border-b-0',
                        isAboveThreshold(line.deviation_percent, deviationThresholdPercent) ? 'bg-warning-soft' : '',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex min-w-0 flex-1 flex-col">
                            <strong class="text-[15px]">{{ line.cut_product.name }}</strong>
                            <span class="text-[13px] text-ink-muted">
                                Esperado {{ formatKg(line.expected_weight_kg) }} kg · {{ formatPercent(line.expected_yield_percent) }}
                            </span>
                        </span>
                        <span v-if="line.actual_weight_kg !== null" class="flex flex-col items-end gap-0.5">
                            <strong class="text-[17px]">{{ formatKg(line.actual_weight_kg) }}</strong>
                            <DeviationIndicator :deviation="line.deviation_percent" :threshold="deviationThresholdPercent" />
                        </span>
                    </div>

                    <div v-if="line.actual_weight_kg === null && isOpen" class="flex items-end gap-3 pb-1">
                        <AppInput
                            v-model="weightInput[line.id]"
                            class="flex-1"
                            label="Kg reales"
                            numeric
                            inputmode="decimal"
                            placeholder="0,000"
                            :aria-label="`Peso real de ${line.cut_product.name} en kg`"
                            :error="lineError(line.id)"
                        />
                        <AppButton
                            variant="accent"
                            :disabled="weightInput[line.id] === '' || registering[line.id]"
                            class="mb-px"
                            @click="registerWeight(line.id)"
                        >
                            Registrar
                        </AppButton>
                    </div>
                </div>
            </section>

            <section class="flex items-center gap-3 rounded-[20px] bg-surface px-4 py-3.5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-info-soft text-info">
                    <AppIcon name="info" :size="20" />
                </span>
                <p v-if="order.status === 'closed'" class="text-[14px] leading-snug">
                    Orden cerrada. El costo se repartió por valor de mercado de cada corte; la merma no lleva costo.
                </p>
                <p v-else class="text-[14px] leading-snug">
                    Al cerrar, el costo de {{ inputCost.usd ?? inputCost.bs }} se reparte por valor de mercado de cada corte. La merma no lleva costo.
                </p>
            </section>
        </main>

        <StickyActionBar v-if="isOpen">
            <template v-if="errors.close" #notice>
                <AppNotice tone="warning">{{ errors.close }}</AppNotice>
            </template>
            <AppButton variant="accent" size="lg" :disabled="!allWeighed || closing" @click="closeOrder">
                Cerrar orden
            </AppButton>
        </StickyActionBar>
    </div>
</template>
