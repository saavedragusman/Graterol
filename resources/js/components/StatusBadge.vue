<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'accent' | 'success' | 'warning' | 'info' | 'neutral' }>(), { tone: 'neutral' });

const toneClass = {
    accent: 'bg-accent text-on-accent',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
    neutral: 'bg-canvas text-ink-muted',
};
</script>

<template>
    <span :class="['inline-flex shrink-0 items-center rounded-full px-2.5 py-1.5 text-[12px] font-bold', toneClass[tone]]">
        <slot />
    </span>
</template>
