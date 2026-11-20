<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * C6: identidad de la empresa y parámetros del cliente, leídos de settings.
 * Se registra como instancia por request: una sola consulta aunque el panel
 * y la PWA lean varios valores. Sin configuración se usan los valores neutros
 * del producto (docs/design.md §2.4), nunca los de un cliente.
 */
class CompanySettings
{
    public const NAME = 'company.name';

    public const LOGO_PATH = 'company.logo_path';

    public const FAVICON_PATH = 'company.favicon_path';

    public const BRAND_COLOR = 'company.brand_color';

    public const ACCENT_COLOR = 'company.accent_color';

    // BR-05: alícuota de charcutería, [abierto] con 16 % por defecto.
    public const CHARCUTERIA_VAT_RATE = 'catalog.charcuteria_vat_rate';

    // BR-11 (DEC-05/DEC-06): umbral global de desviación del despiece, en puntos.
    public const DEVIATION_THRESHOLD_PERCENT = 'butchery.deviation_threshold_percent';

    public const DEFAULT_NAME = 'ERP';

    public const DEFAULT_BRAND_COLOR = '#A51016';

    public const DEFAULT_ACCENT_COLOR = '#F6B01A';

    public const DEFAULT_CHARCUTERIA_VAT_RATE = '16.00';

    public const DEFAULT_DEVIATION_THRESHOLD_PERCENT = '2.00';

    public const LOGO_DIRECTORY = 'branding';

    // \z y no $: $ acepta un salto de línea final.
    public const HEX_COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}\z/';

    /** @var list<string> */
    public const KEYS = [
        self::NAME,
        self::LOGO_PATH,
        self::FAVICON_PATH,
        self::BRAND_COLOR,
        self::ACCENT_COLOR,
        self::CHARCUTERIA_VAT_RATE,
        self::DEVIATION_THRESHOLD_PERCENT,
    ];

    /** @var array<string, string>|null */
    private ?array $values = null;

    public function name(): string
    {
        return $this->get(self::NAME) ?? self::DEFAULT_NAME;
    }

    public function brandColor(): string
    {
        return $this->color(self::BRAND_COLOR, self::DEFAULT_BRAND_COLOR);
    }

    public function accentColor(): string
    {
        return $this->color(self::ACCENT_COLOR, self::DEFAULT_ACCENT_COLOR);
    }

    public function logoPath(): ?string
    {
        return $this->get(self::LOGO_PATH);
    }

    public function faviconPath(): ?string
    {
        return $this->get(self::FAVICON_PATH);
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl($this->logoPath());
    }

    public function faviconUrl(): ?string
    {
        return $this->publicUrl($this->faviconPath());
    }

    public function charcuteriaVatRate(): string
    {
        return $this->get(self::CHARCUTERIA_VAT_RATE) ?? self::DEFAULT_CHARCUTERIA_VAT_RATE;
    }

    public function deviationThresholdPercent(): string
    {
        return $this->get(self::DEVIATION_THRESHOLD_PERCENT) ?? self::DEFAULT_DEVIATION_THRESHOLD_PERCENT;
    }

    /**
     * Valores para la pantalla de configuración.
     *
     * @return array<string, string|null>
     */
    public function toFormState(): array
    {
        return [
            self::NAME => $this->get(self::NAME),
            self::LOGO_PATH => $this->logoPath(),
            self::FAVICON_PATH => $this->faviconPath(),
            self::BRAND_COLOR => $this->brandColor(),
            self::ACCENT_COLOR => $this->accentColor(),
            self::CHARCUTERIA_VAT_RATE => $this->charcuteriaVatRate(),
            self::DEVIATION_THRESHOLD_PERCENT => $this->deviationThresholdPercent(),
        ];
    }

    private function get(string $key): ?string
    {
        $this->values ??= Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->all();

        $value = $this->values[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * El color termina en CSS del layout y en la paleta del panel: si la fila
     * no tiene formato #RRGGBB (escrita fuera de UpdateSettingsAction), se usa el valor por defecto.
     */
    private function color(string $key, string $default): string
    {
        $value = $this->get($key);

        return $value !== null && preg_match(self::HEX_COLOR_PATTERN, $value) === 1 ? $value : $default;
    }

    private function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
