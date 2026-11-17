<?php

declare(strict_types=1);

namespace App\Domain\Butchery\Models;

use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-08: plantilla de despiece de una materia prima (DEC-02: una activa por
 * materia prima). Las líneas (cortes) suman, junto con expected_shrinkage_percent,
 * 100 %.
 */
class ButcheryTemplate extends Model
{
    protected $fillable = [
        'raw_material_product_id',
        'name',
        'expected_shrinkage_percent',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_shrinkage_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'raw_material_product_id');
    }

    /**
     * @return HasMany<ButcheryTemplateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ButcheryTemplateLine::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
