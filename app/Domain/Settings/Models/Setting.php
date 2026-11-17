<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * C6: valores de configuración simples, en datos, nunca en código.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function valueFor(string $key, string $default): string
    {
        return self::where('key', $key)->value('value') ?? $default;
    }
}
