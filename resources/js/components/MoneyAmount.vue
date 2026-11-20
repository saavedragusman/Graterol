<script setup lang="ts">
import { computed } from 'vue';

// Doble monto (docs/design.md §3): divisa arriba, Bs debajo en `ink-muted`.
// Solo muestra: los textos ya llegan formateados y convertidos del servidor (C5).
// Si no hay tasa, `bs` llega null y solo se ve la línea de la divisa.
const props = withDefaults(
    defineProps<{
        usd: string | null;
        bs: string | null;
        rate?: string | null;
        size?: 'md' | 'lg';
        /** `on-dark` para usarlo sobre el encabezado `brand-strong`. */
        tone?: 'default' | 'on-dark';
    }>(),
    { rate: null, size: 'md', tone: 'default' },
);

const mainClass = computed(() => (props.size === 'lg' ? 'text-[22px]' : 'text-[17px]'));
const secondaryClass = computed(() => (props.tone === 'on-dark' ? 'text-surface/85' : 'text-ink-muted'));
</script>

<template>
    <span class="flex min-w-0 flex-col tabular-nums">
        <strong v-if="usd !== null" data-line="usd" :class="['font-bold', mainClass]">{{ usd }}</strong>
        <span v-if="bs !== null" data-line="bs" :class="[usd === null ? ['font-bold', mainClass] : ['text-[13px]', secondaryClass]]">
            {{ bs }}<template v-if="rate && usd !== null"> · tasa {{ rate }}</template>
        </span>
    </span>
</template>
