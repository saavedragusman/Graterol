<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * C6 + C15: guarda la configuración de la empresa y audita cada valor que cambia.
 * Valida todo antes de escribir: una entrada inválida no cambia nada.
 */
class UpdateSettingsAction
{
    /**
     * @param  array<string, string|null>  $values  clave de CompanySettings => valor; null borra el valor.
     */
    public function execute(array $values, ?int $userId): void
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$key] = $this->normalize($key, $value);
        }

        DB::transaction(function () use ($normalized, $userId) {
            foreach ($normalized as $key => $value) {
                $setting = Setting::firstOrNew(['key' => $key]);
                $before = $setting->exists ? $setting->value : null;
                $before = $before === '' ? null : $before;

                if ($before === $value) {
                    continue;
                }

                $setting->value = $value ?? '';
                $setting->save();

                AuditLog::create([
                    'auditable_type' => 'setting',
                    'auditable_id' => $setting->id,
                    'action' => 'updated',
                    'user_id' => $userId,
                    'before' => ['key' => $key, 'value' => $before],
                    'after' => ['key' => $key, 'value' => $value],
                ]);
            }
        });

        // La instancia por request se descarta para que la próxima lectura vea lo nuevo.
        app()->forgetInstance(CompanySettings::class);
    }

    private function normalize(string $key, ?string $value): ?string
    {
        return match ($key) {
            CompanySettings::NAME => $this->name($value),
            CompanySettings::BRAND_COLOR, CompanySettings::ACCENT_COLOR => $this->color($key, $value),
            CompanySettings::LOGO_PATH, CompanySettings::FAVICON_PATH => $this->path($value),
            CompanySettings::CHARCUTERIA_VAT_RATE, CompanySettings::DEVIATION_THRESHOLD_PERCENT => $this->percent($value),
            default => throw new InvalidArgumentException("Configuración desconocida: {$key}"),
        };
    }

    private function name(?string $value): string
    {
        $name = trim((string) $value);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('El nombre de la empresa es obligatorio y tiene como máximo 100 caracteres.');
        }

        return $name;
    }

    private function color(string $key, ?string $value): string
    {
        if ($value === null || preg_match(CompanySettings::HEX_COLOR_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("Color inválido para {$key}: use el formato #RRGGBB.");
        }

        return strtoupper($value);
    }

    private function path(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! str_starts_with($value, CompanySettings::LOGO_DIRECTORY.'/') || str_contains($value, '..')) {
            throw new InvalidArgumentException('Ruta de imagen inválida.');
        }

        return $value;
    }

    private function percent(?string $value): string
    {
        return self::normalizePercent($value)
            ?? throw new InvalidArgumentException('El porcentaje debe ser un número entre 0 y 100.');
    }

    /**
     * C9: sin coma flotante; el porcentaje (alícuota, umbral) se valida y normaliza como texto decimal ("8,5" → "8.50").
     * Devuelve null si no es un número entre 0 y 100 con hasta dos decimales.
     */
    public static function normalizePercent(?string $value): ?string
    {
        if ($value === null || preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', trim($value), $match) !== 1) {
            return null;
        }

        $integer = (int) $match[1];
        $decimals = str_pad($match[2] ?? '', 2, '0');

        if ($integer > 100 || ($integer === 100 && $decimals !== '00')) {
            return null;
        }

        return $integer.'.'.$decimals;
    }
}
