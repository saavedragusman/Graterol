<script setup lang="ts">
// Casilla de dato dentro del encabezado (Entrada, Costo lote, Carnicero).
// Lleva `value` en texto o, si el dato es un doble monto, un slot con MoneyAmount.
defineProps<{ label: string; value?: string }>();
</script>

<template>
    <div class="flex min-w-0 flex-col rounded-[14px] bg-white/10 px-3 py-2.5">
        <span class="text-[12px] text-surface/85">{{ label }}</span>
        <slot>
            <strong class="truncate text-[17px]">{{ value }}</strong>
        </slot>
    </div>
</template>
