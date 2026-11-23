<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * BR-11 (DEC-05/DEC-06): umbral de desviación del despiece, 2 puntos por defecto.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(
            ['key' => 'butchery.deviation_threshold_percent'],
            ['value' => '2.00'],
        );
    }
}
