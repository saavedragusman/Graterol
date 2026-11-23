<script setup lang="ts">
import AppIcon from './AppIcon.vue';

withDefaults(defineProps<{ tone?: 'warning' | 'error' | 'success' }>(), { tone: 'warning' });

const toneClass = {
    warning: 'bg-warning-soft text-warning',
    error: 'bg-brand-soft text-brand',
    success: 'bg-success-soft text-success',
};
</script>

<template>
    <div role="alert" :class="['flex items-start gap-2.5 rounded-[14px] px-3.5 py-3 text-[14px] font-semibold', toneClass[tone]]">
        <AppIcon name="alert-triangle" :size="20" />
        <p><slot /></p>
    </div>
</template>
