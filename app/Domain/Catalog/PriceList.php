<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * BR-04: listas de precio del catálogo.
 */
enum PriceList: string
{
    case Detail = 'detail';
    case Wholesale = 'wholesale';
}
