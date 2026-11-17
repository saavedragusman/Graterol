<?php

use App\Domain\Catalog\Models\Product;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso de prueba
|--------------------------------------------------------------------------
|
| Las pruebas de Feature se vinculan a Tests\TestCase (arranca la aplicación).
| Las de Architecture se vinculan al TestCase de PHPUnit porque solo leen
| archivos fuente y no necesitan la aplicación levantada.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->extend(PHPUnit\Framework\TestCase::class)
    ->in('Architecture');

/*
|--------------------------------------------------------------------------
| Helpers de dominio (Butchery)
|--------------------------------------------------------------------------
*/

function createRawMaterial(string $name = 'Pollo entero'): Product
{
    return Product::create([
        'name' => $name,
        'product_type' => 'raw_material',
        'unit' => 'kg',
        'product_category' => 'natural_meat',
        'tax_rate' => 0,
        'is_tax_exempt' => true,
        'active' => true,
    ]);
}

function createCut(string $name): Product
{
    return Product::create([
        'name' => $name,
        'product_type' => 'derived',
        'unit' => 'kg',
        'product_category' => 'natural_meat',
        'tax_rate' => 0,
        'is_tax_exempt' => true,
        'active' => true,
    ]);
}
