<?php

declare(strict_types=1);

namespace App\Domain\Settings\Guards;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * S4: un usuario solo se borra si no aparece en ninguna tabla.
 *
 * Lista ÚNICA de las claves foráneas a `users.id` que cuentan como historial.
 * `role_user` no cuenta (es solo la asignación de roles y se borra en cascada).
 * Un test lee information_schema y falla si una migración agrega otra clave
 * foránea a users que no esté aquí.
 */
class UserHistory
{
    /**
     * tabla => columnas que apuntan a users.id (y la migración que las crea).
     *
     * @var array<string, list<string>>
     */
    public const FOREIGN_KEYS = [
        // 2026_11_17_000004_create_butchery_orders_table.php
        'butchery_orders' => ['butcher_id', 'voided_by'],
        // 2026_11_12_000005_create_purchases_table.php
        'purchases' => ['created_by', 'received_by', 'voided_by'],
        // 2026_11_10_000006_create_inventory_movements_table.php
        // 2026_11_23_000001_add_indexes_and_responsible_to_inventory_movements.php (responsible_user_id)
        'inventory_movements' => ['user_id', 'responsible_user_id'],
        // 2026_11_23_000002_create_inventory_counts_table.php
        'inventory_counts' => ['created_by', 'confirmed_by'],
        // 2026_11_23_000003_create_inventory_count_lines_table.php
        'inventory_count_lines' => ['counted_by', 'responsible_user_id'],
        // 2026_11_12_000008_create_audit_logs_table.php
        'audit_logs' => ['user_id'],
        // 2026_11_17_000002_create_butchery_templates_table.php
        'butchery_templates' => ['created_by'],
        // 2026_11_12_000001_create_exchange_rates_table.php
        'exchange_rates' => ['registered_by'],
    ];

    public function exists(User $user): bool
    {
        foreach (self::FOREIGN_KEYS as $table => $columns) {
            $query = DB::table($table)->where(function ($q) use ($columns, $user): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, $user->id);
                }
            });

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}
