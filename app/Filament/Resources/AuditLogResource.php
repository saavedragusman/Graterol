<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Settings\Models\AuditLog;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * S8/C15: vista de solo lectura de la auditoría inmutable.
 * Sin páginas de crear ni editar; autorización: AuditLogPolicy (audit.view).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    protected static ?string $navigationLabel = 'Auditoría';

    /** Tipos auditados hoy; un tipo desconocido se muestra tal cual. */
    public const TYPE_LABELS = [
        'purchase' => 'Compra', // VoidPurchaseAction.php:65
        'butchery_order' => 'Orden de despiece', // VoidButcheryOrderAction.php:92
        'setting' => 'Configuración', // UpdateSettingsAction.php:45
        'role' => 'Rol', // CreateRoleAction / UpdateRoleAction / DeleteRoleAction
        'user' => 'Usuario', // Create/UpdateUserAction y demás Actions de usuarios
    ];

    /** Acciones auditadas hoy; una acción desconocida se muestra tal cual. */
    public const ACTION_LABELS = [
        'purchase.void' => 'Anulación', // VoidPurchaseAction.php:66
        'butchery_order.void' => 'Anulación', // VoidButcheryOrderAction.php:93
        'updated' => 'Cambio', // UpdateSettingsAction.php:46
        'role.created' => 'Creación', // CreateRoleAction
        'role.updated' => 'Cambio', // UpdateRoleAction
        'role.deleted' => 'Borrado', // DeleteRoleAction
        'user.created' => 'Creación', // CreateUserAction
        'user.roles_updated' => 'Cambio de roles', // UpdateUserAction
        'user.deactivated' => 'Desactivación', // DeactivateUserAction
        'user.reactivated' => 'Reactivación', // ReactivateUserAction
        'user.password_reset' => 'Restablecimiento de contraseña', // ResetUserPasswordAction
        'user.deleted' => 'Borrado', // DeleteUserAction
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? (string) $type;
    }

    public static function actionLabel(?string $action): string
    {
        return self::ACTION_LABELS[$action] ?? (string) $action;
    }

    /** Referencia legible: la clave para ajustes; `#id` para el resto. */
    public static function reference(AuditLog $log): string
    {
        if ($log->auditable_type === 'setting') {
            $key = ($log->after['key'] ?? null) ?? ($log->before['key'] ?? null);

            if (is_string($key) && $key !== '') {
                return $key;
            }
        }

        return '#'.$log->auditable_id;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Fecha y hora')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Usuario')->default('Sistema'),
                TextColumn::make('auditable_type')->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => self::typeLabel($state)),
                TextColumn::make('auditable_id')->label('Referencia')
                    ->state(fn (AuditLog $record): string => self::reference($record)),
                TextColumn::make('action')->label('Acción')
                    ->formatStateUsing(fn (?string $state): string => self::actionLabel($state)),
                TextColumn::make('reason')->label('Motivo')->wrap(),
            ])
            ->filters([
                SelectFilter::make('auditable_type')->label('Tipo')
                    ->options(fn (): array => AuditLog::query()->distinct()->pluck('auditable_type')
                        ->mapWithKeys(fn (string $type): array => [$type => self::typeLabel($type)])->all()),
                SelectFilter::make('user_id')->label('Usuario')
                    ->options(fn (): array => User::query()
                        ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
                        ->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('created_range')->label('Fechas')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, string $d): Builder => $q->whereDate('created_at', '<=', $d))),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view')->label('Ver')->icon('heroicon-o-eye')
                    // C14: el detalle también pasa por la Policy, no solo el listado.
                    ->authorize('view')
                    ->modalHeading('Detalle de auditoría')
                    ->modalContent(fn (AuditLog $record) => view('filament.audit-log.detail', [
                        'log' => $record,
                        'rows' => self::diffRows($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ]);
    }

    /**
     * Una fila por campo (unión de claves de antes y después).
     *
     * @return list<array{field: string, before: string, after: string}>
     */
    public static function diffRows(AuditLog $log): array
    {
        $before = $log->before ?? [];
        $after = $log->after ?? [];
        $rows = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $rows[] = [
                'field' => (string) $key,
                'before' => self::format($before[$key] ?? null),
                'after' => self::format($after[$key] ?? null),
            ];
        }

        return $rows;
    }

    private static function format(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_array($value) => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
