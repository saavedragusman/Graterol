<script setup lang="ts">
// BR-11: dentro del umbral -> success; por encima -> warning con ícono de alerta.
// El umbral llega por prop (configuración), nunca fijo.
import { computed } from 'vue';
import { isAboveThreshold } from '../lib/deviation';
import { formatPoints } from '../lib/format';
import AppIcon from './AppIcon.vue';

const props = defineProps<{ deviation: string | null; threshold: string | number }>();

const above = computed(() => isAboveThreshold(props.deviation, props.threshold));
</script>

<template>
    <span
        v-if="deviation !== null"
        :data-tone="above ? 'warning' : 'success'"
        :class="['inline-flex items-center gap-1 text-[12px] font-bold tabular-nums', above ? 'text-warning' : 'text-success']"
    >
        <AppIcon v-if="above" name="alert-triangle" :size="14" />
        {{ formatPoints(deviation) }}
    </span>
</template>
