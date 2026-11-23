<script setup lang="ts">
// Barra fija inferior: una acción principal (slot por defecto) y, como mucho, una secundaria.
// El slot `notice` muestra avisos justo encima de las acciones.
</script>

<template>
    <div class="fixed inset-x-0 bottom-0 border-t border-line bg-surface px-4 pb-5 pt-3">
        <div class="mx-auto flex w-full max-w-xl flex-col gap-3">
            <slot name="notice" />
            <div :class="['grid gap-2.5', $slots.secondary ? 'grid-cols-2' : 'grid-cols-1']">
                <slot name="secondary" />
                <slot />
            </div>
        </div>
    </div>
</template>
