<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

withDefaults(
    defineProps<{
        variant?: 'floor' | 'task';
        title: string;
        subtitle?: string;
        backHref?: string;
    }>(),
    { variant: 'task' },
);
</script>

<template>
    <header
        :class="[
            'px-4 pb-4 pt-3.5',
            variant === 'floor' ? 'bg-brand-strong text-surface' : 'border-b border-line bg-surface text-ink',
        ]"
    >
        <div class="mx-auto flex w-full max-w-xl flex-col gap-3.5">
            <div class="flex items-center gap-2.5">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    aria-label="Volver"
                    :class="[
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-[14px] focus-visible:outline-2 focus-visible:outline-offset-2',
                        variant === 'floor'
                            ? 'bg-white/15 text-surface outline-surface'
                            : 'bg-canvas text-ink outline-brand',
                    ]"
                >
                    <AppIcon name="arrow-left" />
                </Link>
                <div class="flex min-w-0 flex-1 flex-col">
                    <h1 class="truncate text-[22px] font-bold leading-tight">{{ title }}</h1>
                    <p v-if="subtitle" :class="['truncate text-[13px]', variant === 'floor' ? 'text-surface/85' : 'text-ink-muted']">
                        {{ subtitle }}
                    </p>
                </div>
                <slot name="trailing" />
            </div>
            <div v-if="$slots.default" class="grid grid-cols-3 gap-2 tabular-nums">
                <slot />
            </div>
        </div>
    </header>
</template>
