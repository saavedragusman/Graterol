<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * BR-11 (DEC-05/DEC-06): umbral de desviación del despiece, 2 puntos por defecto.
 * C6 / AGENTS §6: empresa de ejemplo con datos ficticios; cada instalación
 * configura los suyos desde la oficina.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'butchery.deviation_threshold_percent' => '2.00',
            CompanySettings::NAME => 'Distribuidora Demo',
            CompanySettings::BRAND_COLOR => CompanySettings::DEFAULT_BRAND_COLOR,
            CompanySettings::ACCENT_COLOR => CompanySettings::DEFAULT_ACCENT_COLOR,
            CompanySettings::CHARCUTERIA_VAT_RATE => CompanySettings::DEFAULT_CHARCUTERIA_VAT_RATE,
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
