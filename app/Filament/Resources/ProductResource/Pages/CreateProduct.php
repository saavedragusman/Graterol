<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Domain\Catalog\Actions\CreateProductWithPricesAction;
use App\Domain\Catalog\ProductCategory;
use App\Domain\Catalog\ProductType;
use App\Domain\Catalog\Unit;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return (new CreateProductWithPricesAction)->execute(
            (string) $data['name'],
            ProductType::from((string) $data['product_type']),
            Unit::from((string) $data['unit']),
            ProductCategory::from((string) $data['product_category']),
            $data['detail_price'] ?? null,
            $data['wholesale_price'] ?? null,
            filled($data['price_currency'] ?? null) ? (string) $data['price_currency'] : null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
