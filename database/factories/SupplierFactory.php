<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => 'Proveedor de prueba '.$this->faker->unique()->numberBetween(1, 100000),
            'tax_id' => 'J-'.$this->faker->unique()->numberBetween(10000000, 99999999).'-'.$this->faker->numberBetween(0, 9),
            'phone' => $this->faker->numerify('0412#######'),
            'is_active' => true,
        ];
    }
}
