<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppIcon, { type IconName } from './AppIcon.vue';

withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        icon?: IconName;
        tone?: 'brand' | 'accent' | 'success' | 'warning' | 'info';
        href?: string;
    }>(),
    { tone: 'brand' },
);

const toneClass = {
    brand: 'bg-brand-soft text-brand',
    accent: 'bg-accent-soft text-on-accent-soft',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    info: 'bg-info-soft text-info',
};
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        :class="[
            'flex min-h-14 items-center gap-3 px-4 py-2',
            href ? 'outline-brand focus-visible:outline-2 focus-visible:-outline-offset-2' : '',
        ]"
    >
        <span v-if="icon" :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', toneClass[tone]]">
            <AppIcon :name="icon" :size="20" />
        </span>
        <span class="flex min-w-0 flex-1 flex-col">
            <strong class="text-[15px] font-bold text-ink">{{ title }}</strong>
            <span v-if="subtitle" class="text-[13px] text-ink-muted">{{ subtitle }}</span>
        </span>
        <slot />
        <AppIcon v-if="href" name="chevron-right" :size="20" class="text-ink-muted" />
    </component>
</template>
