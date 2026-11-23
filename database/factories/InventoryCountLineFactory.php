<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\InventoryCountLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Línea de conteo (BR-16): por defecto sin pesar; `weighed()` agrega peso, saldo,
 * diferencia y responsable (DEC-05). Pesos como strings de 3 decimales (C9).
 *
 * @extends Factory<InventoryCountLine>
 */
class InventoryCountLineFactory extends Factory
{
    protected $model = InventoryCountLine::class;

    public function definition(): array
    {
        return [
            'count_id' => InventoryCount::factory(),
            'batch_id' => Batch::factory(),
        ];
    }

    public function weighed(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_quantity_kg' => '10.000',
            'counted_weight_kg' => '9.500',
            'difference_kg' => '-0.500',
            'counted_by' => User::factory(),
            'counted_at' => now(),
            'responsible_user_id' => User::factory(),
        ]);
    }
}
