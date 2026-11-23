<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos ficticios (AGENTS §6): nunca costos ni precios reales.
 *
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'code' => 'lote-prueba-'.$this->faker->unique()->numberBetween(1, 1000000),
            'source_type' => 'purchase_line',
            'unit' => 'kg',
            'unit_cost' => '1.0000',
            'total_cost' => '100.00',
            'currency' => 'VES',
            'exchange_rate' => '1.00',
            'received_at' => now(),
        ];
    }
}
