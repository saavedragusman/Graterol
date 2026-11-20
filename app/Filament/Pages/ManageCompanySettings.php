<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\UpdateSettingsAction;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\PermissionKey;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use UnitEnum;

/**
 * M1 / C6: identidad de la empresa, IVA de charcutería y umbral de despiece, editables desde la oficina.
 * La regla y la auditoría viven en UpdateSettingsAction; aquí solo se valida la forma.
 *
 * @property-read Schema $form
 */
class ManageCompanySettings extends Page
{
    /** Formulario => clave en settings. */
    private const FIELDS = [
        'name' => CompanySettings::NAME,
        'logo_path' => CompanySettings::LOGO_PATH,
        'favicon_path' => CompanySettings::FAVICON_PATH,
        'brand_color' => CompanySettings::BRAND_COLOR,
        'accent_color' => CompanySettings::ACCENT_COLOR,
        'charcuteria_vat_rate' => CompanySettings::CHARCUTERIA_VAT_RATE,
        'deviation_threshold_percent' => CompanySettings::DEVIATION_THRESHOLD_PERCENT,
    ];

    protected string $view = 'filament.pages.manage-company-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'General';

    protected static ?string $title = 'Configuración general';

    protected static ?string $slug = 'configuracion/general';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasPermission(PermissionKey::SettingsManage->value);
    }

    public function mount(): void
    {
        $state = app(CompanySettings::class)->toFormState();

        $this->form->fill(array_map(fn (string $key) => $state[$key], self::FIELDS));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Identidad')
                        ->description('Nombre, logo y colores con los que se ve el sistema en la oficina y en la PWA.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre comercial')
                                ->required()
                                ->maxLength(100)
                                ->columnSpanFull(),
                            FileUpload::make('logo_path')
                                ->label('Logo')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(1024)
                                ->disk('public')
                                ->directory(CompanySettings::LOGO_DIRECTORY)
                                ->visibility('public'),
                            FileUpload::make('favicon_path')
                                ->label('Favicon')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])
                                ->maxSize(256)
                                ->disk('public')
                                ->directory(CompanySettings::LOGO_DIRECTORY)
                                ->visibility('public'),
                            ColorPicker::make('brand_color')
                                ->label('Color de marca')
                                ->helperText('Navegación activa, encabezados y botón primario.')
                                ->required()
                                ->regex(CompanySettings::HEX_COLOR_PATTERN),
                            ColorPicker::make('accent_color')
                                ->label('Color de acción')
                                ->helperText('Solo para «Vender», «Cobrar», «Confirmar» y el siguiente paso.')
                                ->required()
                                ->regex(CompanySettings::HEX_COLOR_PATTERN),
                        ]),
                    Section::make('Impuestos')
                        ->schema([
                            TextInput::make('charcuteria_vat_rate')
                                ->label('Alícuota de IVA de charcutería (%)')
                                ->helperText('Valor por defecto para productos nuevos de charcutería (BR-05).')
                                ->required()
                                ->rule(self::percentRule()),
                        ]),
                    Section::make('Despiece')
                        ->schema([
                            TextInput::make('deviation_threshold_percent')
                                ->label('Umbral de desviación (puntos)')
                                ->helperText('Si un corte rinde más o menos que la plantilla por encima de este margen, se marca como alerta (BR-11).')
                                ->required()
                                ->rule(self::percentRule()),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Guardar')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    private static function percentRule(): Closure
    {
        return fn () => function (string $attribute, mixed $value, Closure $fail) {
            if (UpdateSettingsAction::normalizePercent(is_string($value) ? $value : null) === null) {
                $fail('Debe ser un número entre 0 y 100, con hasta dos decimales.');
            }
        };
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $values = [];
        foreach (self::FIELDS as $field => $key) {
            $value = $state[$field] ?? null;
            $values[$key] = is_string($value) ? $value : null;
        }

        try {
            app(UpdateSettingsAction::class)->execute($values, Auth::id());
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Configuración guardada')->send();
    }
}
