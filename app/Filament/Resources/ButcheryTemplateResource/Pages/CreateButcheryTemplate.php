<?php

declare(strict_types=1);

namespace App\Filament\Resources\ButcheryTemplateResource\Pages;

use App\Domain\Butchery\Actions\CreateButcheryTemplateAction;
use App\Filament\Resources\ButcheryTemplateResource;
use App\Filament\Resources\ButcheryTemplateResource\Pages\Concerns\MapsButcheryTemplateLines;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateButcheryTemplate extends CreateRecord
{
    use MapsButcheryTemplateLines;

    protected static string $resource = ButcheryTemplateResource::class;

    /**
     * C3: la regla (suma 100 %, materia prima, DEC-02) vive en la Action.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateButcheryTemplateAction::class)->execute(
                rawMaterialProductId: (int) $data['raw_material_product_id'],
                name: (string) $data['name'],
                expectedShrinkagePercent: (string) $data['expected_shrinkage_percent'],
                lines: $this->mapLines($data['lines'] ?? []),
                createdByUserId: (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('No se pudo crear la plantilla')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
