<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Domain\Catalog\Actions\UpdateProductAction;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\PriceList;
use App\Domain\Settings\CompanySettings;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->getRecord();
        $detail = $product->priceFor(PriceList::Detail->value);
        $wholesale = $product->priceFor(PriceList::Wholesale->value);

        $data['detail_price'] = $detail?->amount;
        $data['wholesale_price'] = $wholesale?->amount;
        $data['price_currency'] = $detail->currency ?? $wholesale->currency ?? app(CompanySettings::class)->defaultCurrency();

        return $data;
    }

    /**
     * Solo se editan nombre, estado y precios; tipo, unidad y categoría quedan fijos (BR-05).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        return (new UpdateProductAction)->execute(
            $record,
            (string) $data['name'],
            (bool) ($data['active'] ?? $record->active),
            $data['detail_price'] ?? null,
            $data['wholesale_price'] ?? null,
            filled($data['price_currency'] ?? null) ? (string) $data['price_currency'] : null,
        );
    }
}
