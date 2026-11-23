<script setup lang="ts">
import AppHeader from '../../components/AppHeader.vue';
import ListRow from '../../components/ListRow.vue';

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
    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <AppHeader title="Recepciones pendientes" subtitle="Compras listas para pesar y recibir." />

        <main class="mx-auto w-full max-w-xl flex-1 px-4 pb-8 pt-3.5">
            <p v-if="purchases.length === 0" class="rounded-[20px] bg-surface p-6 text-center text-[14px] text-ink-muted">
                No hay compras pendientes de recepción.
            </p>

            <ul v-else class="rounded-[20px] bg-surface py-1">
                <li v-for="purchase in purchases" :key="purchase.id" class="border-b border-line-soft last:border-b-0">
                    <ListRow
                        :href="`/purchasing/receipts/${purchase.id}`"
                        icon="truck"
                        tone="info"
                        :title="purchase.supplier.name"
                        :subtitle="`Factura ${purchase.supplier_invoice_number} · ${purchase.lines_count} línea(s)`"
                    />
                </li>
            </ul>
        </main>
    </div>
</template>
