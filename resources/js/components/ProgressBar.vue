<script setup lang="ts">
// Barra segmentada. `label` es obligatorio: dice los valores a quien no ve la barra (design.md §3).
export interface Segment {
    percent: number;
    tone: 'brand' | 'neutral';
}

defineProps<{ segments: Segment[]; label: string }>();

const toneClass = { brand: 'bg-brand', neutral: 'bg-chart-neutral' };

function clamp(percent: number): number {
    return Math.min(100, Math.max(0, percent));
}
</script>

<template>
    <div role="img" :aria-label="label" class="flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-canvas">
        <div
            v-for="(segment, index) in segments"
            :key="index"
            :class="['h-full', toneClass[segment.tone]]"
            :style="{ width: `${clamp(segment.percent)}%` }"
        />
    </div>
</template>
