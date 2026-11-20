<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * S6/S8 (M1): los MONTOS de dinero y la tasa congelada pasan de numeric(18,4)
     * a numeric(18,2) (C9, BR-01, BR-02). Primero se redondean las filas
     * existentes en sitio (half-up, `round(numeric, 2)` de PostgreSQL, sin
     * tipos de punto flotante) y se reparte el residuo de centavos para que lo
     * que debe cuadrar siga cuadrando; después se cambia el tipo.
     *
     * Columnas que pasan a 2 decimales:
     *   purchase_lines: line_amount, allocated_freight, allocated_other_costs
     *   purchases:      freight_amount, other_costs_amount, exchange_rate
     *   batches:        total_cost, exchange_rate
     *   butchery_order_lines: allocated_cost
     *   exchange_rates: rate
     *
     * NO se tocan (S5, valores unitarios que conservan 4 decimales):
     * batches.unit_cost, inventory_movements.unit_cost, product_prices.amount y
     * butchery_order_lines.reference_price_per_kg. Tampoco pesos ni porcentajes.
     *
     * `inventory_movements` es el kardex inmutable (C10): esta migración no lo
     * actualiza ni toca su trigger. Por eso `batches.unit_cost` y el costo del
     * movimiento quedan como estaban aunque `total_cost` cambie hasta unos
     * centavos al redondear.
     *
     * Reconciliaciones (se calculan a partir de los valores ya redondeados):
     *  1. Por compra: Σ purchase_lines.allocated_freight = purchases.freight_amount
     *     y Σ allocated_other_costs = other_costs_amount (ambos redondeados). El
     *     residuo va a la línea de mayor peso recibido, la de menor id en empate
     *     (misma regla que AllocateCostsAction).
     *  2. Por orden de despiece: Σ butchery_order_lines.allocated_cost = el costo de
     *     entrada de la orden, que es la suma ya asignada a sus cortes redondeada
     *     al centavo. El residuo va al corte de mayor valor (peso real × precio de
     *     referencia), el de menor id en empate (misma regla que
     *     AllocateButcheryCostsAction).
     *  3. batches.total_cost de un lote de compra = line_amount + allocated_freight +
     *     allocated_other_costs de su línea, ya redondeados.
     *  4. batches.total_cost de un lote de salida de despiece = allocated_cost
     *     redondeado de su línea.
     *
     * Límite: el objetivo de (1) y (2) sale de los datos existentes. Si una fila
     * vieja ya no sumaba su total (no debería), el residuo absorbe esa diferencia
     * completa en una sola línea en vez de solo centavos de redondeo.
     */
    public function up(): void
    {
        // Tasas congeladas y de la tabla de tasas: half-up a 2 decimales (BR-02).
        DB::statement('UPDATE exchange_rates SET rate = round(rate, 2)');
        DB::statement('UPDATE purchases SET exchange_rate = round(exchange_rate, 2)');
        DB::statement('UPDATE batches SET exchange_rate = round(exchange_rate, 2) WHERE exchange_rate IS NOT NULL');

        // Montos de la cabecera de compra y de las líneas.
        DB::statement('UPDATE purchases SET freight_amount = round(freight_amount, 2), other_costs_amount = round(other_costs_amount, 2)');
        DB::statement('UPDATE purchase_lines SET line_amount = round(line_amount, 2)');

        // Reparto de flete y otros costos: cada parte redondeada y el residuo al de mayor peso recibido.
        $this->reconcilePurchaseAllocation('allocated_freight', 'freight_amount');
        $this->reconcilePurchaseAllocation('allocated_other_costs', 'other_costs_amount');

        // Costo asignado del despiece: cada corte redondeado y el residuo al de mayor valor.
        DB::statement(<<<'SQL'
            WITH legacy AS (
                SELECT order_id, round(SUM(allocated_cost), 2) AS target
                FROM butchery_order_lines
                WHERE allocated_cost IS NOT NULL
                GROUP BY order_id
            ), base AS (
                SELECT l.id,
                       l.order_id,
                       round(l.allocated_cost, 2) AS rounded,
                       legacy.target,
                       row_number() OVER (
                           PARTITION BY l.order_id
                           ORDER BY l.actual_weight_kg * l.reference_price_per_kg DESC NULLS LAST, l.id
                       ) AS position
                FROM butchery_order_lines l
                JOIN legacy ON legacy.order_id = l.order_id
                WHERE l.allocated_cost IS NOT NULL
            ), fixed AS (
                SELECT id,
                       rounded + CASE
                           WHEN position = 1 THEN target - SUM(rounded) OVER (PARTITION BY order_id)
                           ELSE 0
                       END AS amount
                FROM base
            )
            UPDATE butchery_order_lines
            SET allocated_cost = fixed.amount
            FROM fixed
            WHERE butchery_order_lines.id = fixed.id
        SQL);

        // Costo total de los lotes, calculado desde las partes ya redondeadas.
        DB::statement(<<<'SQL'
            UPDATE batches
            SET total_cost = purchase_lines.line_amount
                + COALESCE(purchase_lines.allocated_freight, 0)
                + COALESCE(purchase_lines.allocated_other_costs, 0)
            FROM purchase_lines
            WHERE batches.source_type = 'purchase_line'
              AND batches.source_id = purchase_lines.id
              AND batches.total_cost IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE batches
            SET total_cost = butchery_order_lines.allocated_cost
            FROM butchery_order_lines
            WHERE batches.source_type = 'butchery_order_line'
              AND batches.source_id = butchery_order_lines.id
              AND butchery_order_lines.allocated_cost IS NOT NULL
        SQL);

        // Los datos ya están a 2 decimales: solo cambia el tipo (default y nulabilidad se conservan).
        $this->setScale(2);
    }

    /**
     * Solo ensancha la escala a (18,4): los decimales que se perdieron al
     * redondear en `up()` no se pueden recuperar.
     */
    public function down(): void
    {
        $this->setScale(4);
    }

    /**
     * Redondea cada parte repartida de una compra y deja el residuo de centavos
     * en la línea de mayor peso recibido, de modo que Σ partes = monto de la compra.
     */
    private function reconcilePurchaseAllocation(string $lineColumn, string $purchaseColumn): void
    {
        DB::statement(<<<SQL
            WITH base AS (
                SELECT l.id,
                       l.purchase_id,
                       round(l.{$lineColumn}, 2) AS rounded,
                       round(p.{$purchaseColumn}, 2) AS target,
                       row_number() OVER (
                           PARTITION BY l.purchase_id
                           ORDER BY l.received_weight_kg DESC NULLS LAST, l.id
                       ) AS position
                FROM purchase_lines l
                JOIN purchases p ON p.id = l.purchase_id
                WHERE l.{$lineColumn} IS NOT NULL
            ), fixed AS (
                SELECT id,
                       rounded + CASE
                           WHEN position = 1 THEN target - SUM(rounded) OVER (PARTITION BY purchase_id)
                           ELSE 0
                       END AS amount
                FROM base
            )
            UPDATE purchase_lines
            SET {$lineColumn} = fixed.amount
            FROM fixed
            WHERE purchase_lines.id = fixed.id
        SQL);
    }

    private function setScale(int $scale): void
    {
        $columns = [
            'purchase_lines' => ['line_amount', 'allocated_freight', 'allocated_other_costs'],
            'purchases' => ['freight_amount', 'other_costs_amount', 'exchange_rate'],
            'batches' => ['total_cost', 'exchange_rate'],
            'butchery_order_lines' => ['allocated_cost'],
            'exchange_rates' => ['rate'],
        ];

        foreach ($columns as $table => $names) {
            $alterations = array_map(
                static fn (string $name): string => "ALTER COLUMN {$name} TYPE numeric(18,{$scale})",
                $names,
            );

            DB::statement("ALTER TABLE {$table} ".implode(', ', $alterations));
        }
    }
};
