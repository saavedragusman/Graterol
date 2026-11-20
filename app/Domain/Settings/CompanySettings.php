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

    public const DEFAULT_NAME = 'ERP';

    public const DEFAULT_BRAND_COLOR = '#A51016';

    public const DEFAULT_ACCENT_COLOR = '#F6B01A';

    public const DEFAULT_CHARCUTERIA_VAT_RATE = '16.00';

    public const LOGO_DIRECTORY = 'branding';

    /** @var list<string> */
    public const KEYS = [
        self::NAME,
        self::LOGO_PATH,
        self::FAVICON_PATH,
        self::BRAND_COLOR,
        self::ACCENT_COLOR,
        self::CHARCUTERIA_VAT_RATE,
    ];

    /** @var array<string, string>|null */
    private ?array $values = null;

    public function name(): string
    {
        return $this->get(self::NAME) ?? self::DEFAULT_NAME;
    }

    public function brandColor(): string
    {
        return $this->get(self::BRAND_COLOR) ?? self::DEFAULT_BRAND_COLOR;
    }

    public function accentColor(): string
    {
        return $this->get(self::ACCENT_COLOR) ?? self::DEFAULT_ACCENT_COLOR;
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
        ];
    }

    private function get(string $key): ?string
    {
        $this->values ??= Setting::whereIn('key', self::KEYS)->pluck('value', 'key')->all();

        $value = $this->values[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    private function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
