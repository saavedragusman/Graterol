<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * BR-04: tipos de producto del catálogo.
 */
enum ProductType: string
{
    case RawMaterial = 'raw_material';
    case Derived = 'derived';
    case Resale = 'resale';
}
