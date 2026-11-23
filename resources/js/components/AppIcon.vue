<script setup lang="ts">
// Íconos en SVG en línea, estilo Lucide, trazo de 2 px. Sin librería ni CDN (BR-26).
import { computed } from 'vue';

export type IconName = 'arrow-left' | 'alert-triangle' | 'info' | 'chevron-right' | 'package' | 'truck' | 'scale';

const paths: Record<IconName, string[]> = {
    'arrow-left': ['M19 12H5', 'M11 18l-6-6 6-6'],
    'alert-triangle': ['M12 3 2 20h20z', 'M12 10v4', 'M12 17h.01'],
    info: ['M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0', 'M12 11v5', 'M12 8h.01'],
    'chevron-right': ['m9 18 6-6-6-6'],
    package: ['M21 8l-9-5-9 5v8l9 5 9-5z', 'M3.3 7.5 12 12.5l8.7-5', 'M12 22V12'],
    truck: [
        'M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2',
        'M15 18H9',
        'M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14',
        'M15 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
        'M5 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
    ],
    scale: ['M5 21h14', 'M12 3v18', 'M5 7h14', 'M5 7l-3 7a3 3 0 0 0 6 0z', 'M19 7l-3 7a3 3 0 0 0 6 0z'],
};

const props = withDefaults(defineProps<{ name: IconName; size?: number }>(), { size: 22 });

const shapes = computed(() => paths[props.name]);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="shrink-0"
    >
        <path v-for="d in shapes" :key="d" :d="d" />
    </svg>
</template>
