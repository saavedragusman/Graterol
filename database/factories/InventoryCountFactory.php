<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\InventoryCount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sesión de conteo (BR-16) con datos ficticios; por defecto en borrador.
 *
 * @extends Factory<InventoryCount>
 */
class InventoryCountFactory extends Factory
{
    protected $model = InventoryCount::class;

    public function definition(): array
    {
        return [
            'code' => 'C-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 4, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_by' => User::factory(),
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }
}
