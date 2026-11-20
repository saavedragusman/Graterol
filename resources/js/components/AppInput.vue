<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        label: string;
        error?: string;
        // Pesos y cantidades: alineado a la derecha, cifras tabulares.
        numeric?: boolean;
        inputmode?: 'decimal' | 'numeric' | 'text';
        type?: string;
    }>(),
    { numeric: false, inputmode: 'text', type: 'text' },
);

const model = defineModel<string>({ default: '' });
const id = useId();
const attrs = useAttrs();
// La clase del padre (ancho, flex) va a la raíz; el resto de atributos, al campo.
const inputAttrs = computed(() => {
    const { class: _class, ...rest } = attrs;

    return rest;
});
</script>

<template>
    <div :class="['flex flex-col gap-1', attrs.class]">
        <label :for="id" class="text-[14px] font-semibold text-ink-muted">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            v-bind="inputAttrs"
            :type="type"
            :inputmode="inputmode"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : undefined"
            :class="[
                'h-[52px] w-full rounded-[14px] border-2 bg-surface px-3 text-[18px] text-ink focus:border-brand focus:outline-none',
                error ? 'border-brand' : 'border-line',
                numeric ? 'text-right font-bold tabular-nums' : '',
            ]"
        >
        <p v-if="error" :id="`${id}-error`" role="alert" class="text-[14px] text-brand">{{ error }}</p>
    </div>
</template>
