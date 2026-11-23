<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * BR-11 (DEC-05/DEC-06): umbral de desviación del despiece, 2 puntos por defecto.
 * M4: parámetros de inventario (BR-17, DEC-09).
 * C6 / AGENTS §6: empresa de ejemplo con datos ficticios; cada instalación
 * configura los suyos desde la oficina.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            CompanySettings::DEVIATION_THRESHOLD_PERCENT => CompanySettings::DEFAULT_DEVIATION_THRESHOLD_PERCENT,
            CompanySettings::DEFAULT_CURRENCY => CompanySettings::DEFAULT_CURRENCY_VALUE,
            CompanySettings::NAME => 'Distribuidora Demo',
            CompanySettings::BRAND_COLOR => CompanySettings::DEFAULT_BRAND_COLOR,
            CompanySettings::ACCENT_COLOR => CompanySettings::DEFAULT_ACCENT_COLOR,
            CompanySettings::CHARCUTERIA_VAT_RATE => CompanySettings::DEFAULT_CHARCUTERIA_VAT_RATE,
            // BR-17 [abierto] (DEC-03 b): por defecto la salida sin stock suficiente se bloquea.
            'inventory.allow_sale_without_stock' => 'false',
            // DEC-09 (1 A): días para considerar un lote «por vencer».
            'inventory.expiry_alert_days' => '3',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
