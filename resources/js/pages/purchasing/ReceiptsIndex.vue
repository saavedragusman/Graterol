<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Supplier {
    name: string;
}

interface Purchase {
    id: number;
    supplier: Supplier;
    supplier_invoice_number: string;
    lines_count: number;
}

defineProps<{ purchases: Purchase[] }>();
</script>

<template>
    <div class="min-h-screen bg-canvas p-4">
        <header class="mb-4">
            <h1 class="text-lg font-bold text-ink">Recepciones pendientes</h1>
            <p class="text-sm text-ink-muted">Compras listas para pesar y recibir.</p>
        </header>

        <p v-if="purchases.length === 0" class="rounded-md bg-surface p-6 text-center text-sm text-ink-muted">
            No hay compras pendientes de recepción.
        </p>

        <ul v-else class="space-y-2">
            <li v-for="purchase in purchases" :key="purchase.id">
                <Link
                    :href="`/purchasing/receipts/${purchase.id}`"
                    class="flex min-h-[56px] items-center justify-between rounded-md border border-line bg-surface px-4 py-3"
                >
                    <span>
                        <span class="block text-[15px] font-bold text-ink">{{ purchase.supplier.name }}</span>
                        <span class="block text-[13px] text-ink-muted">Factura {{ purchase.supplier_invoice_number }} · {{ purchase.lines_count }} línea(s)</span>
                    </span>
                    <span class="text-ink-muted">›</span>
                </Link>
            </li>
        </ul>
    </div>
</template>
