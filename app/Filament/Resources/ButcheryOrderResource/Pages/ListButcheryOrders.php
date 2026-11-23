<?php

declare(strict_types=1);

namespace App\Filament\Resources\ButcheryOrderResource\Pages;

use App\Filament\Resources\ButcheryOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListButcheryOrders extends ListRecords
{
    protected static string $resource = ButcheryOrderResource::class;
}
