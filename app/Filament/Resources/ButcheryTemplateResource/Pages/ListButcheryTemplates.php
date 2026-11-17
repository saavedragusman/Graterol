<?php

declare(strict_types=1);

namespace App\Filament\Resources\ButcheryTemplateResource\Pages;

use App\Filament\Resources\ButcheryTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListButcheryTemplates extends ListRecords
{
    protected static string $resource = ButcheryTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
