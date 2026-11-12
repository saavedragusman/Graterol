<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * BR-04: unidad de venta del producto.
 */
enum Unit: string
{
    case Kilogram = 'kg';
    case Piece = 'piece';
}
