<script setup lang="ts">
import { computed } from 'vue';
import AppIcon, { type IconName } from './AppIcon.vue';

const props = withDefaults(
    defineProps<{
        variant?: 'accent' | 'primary' | 'outline' | 'neutral' | 'ghost';
        size?: 'md' | 'lg';
        icon?: IconName;
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button', disabled: false },
);

const variantClass = computed(
    () =>
        ({
            accent: 'border-transparent bg-accent text-on-accent hover:bg-accent/90',
            primary: 'border-transparent bg-brand text-surface hover:bg-brand-strong',
            outline: 'border-brand bg-surface text-brand hover:bg-brand-soft',
            neutral: 'border-line bg-surface text-ink hover:bg-canvas',
            ghost: 'border-transparent bg-transparent text-ink hover:bg-canvas',
        })[props.variant],
);

const sizeClass = computed(() => (props.size === 'lg' ? 'h-14 rounded-2xl text-[17px]' : 'h-12 rounded-[14px] text-[16px]'));
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="[
            'inline-flex items-center justify-center gap-2 border-2 px-4 font-bold outline-brand focus-visible:outline-2 focus-visible:outline-offset-2',
            'disabled:cursor-not-allowed disabled:border-line disabled:bg-canvas disabled:text-ink-muted disabled:hover:bg-canvas',
            variantClass,
            sizeClass,
        ]"
    >
        <AppIcon v-if="icon" :name="icon" />
        <slot />
    </button>
</template>
