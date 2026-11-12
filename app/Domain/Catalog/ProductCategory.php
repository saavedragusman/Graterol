<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * BR-05: categoría del producto para decidir el IVA.
 * Carnes naturales exentas; charcutería y el resto, gravadas a la tasa configurada.
 */
enum ProductCategory: string
{
    case NaturalMeat = 'natural_meat';
    case Charcuteria = 'charcuteria';
    case Other = 'other';
}
