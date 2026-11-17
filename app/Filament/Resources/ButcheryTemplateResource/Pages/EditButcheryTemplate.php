<?php

declare(strict_types=1);

namespace App\Filament\Resources\ButcheryTemplateResource\Pages;

use App\Domain\Butchery\Actions\UpdateButcheryTemplateAction;
use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Filament\Resources\ButcheryTemplateResource;
use App\Filament\Resources\ButcheryTemplateResource\Pages\Concerns\MapsButcheryTemplateLines;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditButcheryTemplate extends EditRecord
{
    use MapsButcheryTemplateLines;

    protected static string $resource = ButcheryTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ButcheryTemplate $template */
        $template = $this->getRecord();

        $data['lines'] = $template->lines->map(fn ($line): array => [
            'cut_product_id' => $line->cut_product_id,
            'expected_yield_percent' => $line->expected_yield_percent,
        ])->all();

        return $data;
    }

    /**
     * C3/DEC-03: edición en el sitio; la regla vive en la Action.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ButcheryTemplate $record */
        try {
            return app(UpdateButcheryTemplateAction::class)->execute(
                templateId: $record->id,
                name: (string) $data['name'],
                expectedShrinkagePercent: (string) $data['expected_shrinkage_percent'],
                lines: $this->mapLines($data['lines'] ?? []),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('No se pudo guardar la plantilla')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
