<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

/**
 * C10/DEC-04: tipos de movimiento del kardex. Los valores son los strings que ya
 * guarda `inventory_movements.type`; no cambian (sin migración).
 * Signos: entradas positivas, salidas y mermas negativas; `count_adjustment` con el signo de la diferencia.
 */
enum MovementType: string
{
    case PurchaseIn = 'purchase_in';
    case PurchaseVoid = 'purchase_void';
    case ButcheryIn = 'butchery_in';
    case ButcheryOut = 'butchery_out';
    case ButcheryVoid = 'butchery_void';
    case ShrinkageDehydration = 'shrinkage_dehydration';
    case ShrinkageSlicing = 'shrinkage_slicing';
    case ShrinkageExpiry = 'shrinkage_expiry';
    case ShrinkageConfiscation = 'shrinkage_confiscation';
    case CountAdjustment = 'count_adjustment';
    // Reservado (BR-14): lo empieza a escribir M5 (ventas).
    case SaleOut = 'sale_out';

    /** Etiqueta en español para el kardex. */
    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Entrada por compra',
            self::PurchaseVoid => 'Anulación de compra',
            self::ButcheryIn => 'Consumo por despiece',
            self::ButcheryOut => 'Lote de despiece',
            self::ButcheryVoid => 'Anulación de despiece',
            self::ShrinkageDehydration => 'Merma por deshidratación',
            self::ShrinkageSlicing => 'Merma por rebanado',
            self::ShrinkageExpiry => 'Merma por vencimiento',
            self::ShrinkageConfiscation => 'Merma por decomiso',
            self::CountAdjustment => 'Ajuste por conteo',
            self::SaleOut => 'Salida por venta',
        };
    }

    /** Ícono (Heroicons outline) del tipo en el kardex. */
    public function icon(): string
    {
        return match ($this) {
            self::PurchaseIn => 'heroicon-o-arrow-down-tray',
            self::PurchaseVoid,
            self::ButcheryVoid => 'heroicon-o-arrow-uturn-left',
            self::ButcheryIn,
            self::ButcheryOut => 'heroicon-o-scissors',
            self::ShrinkageDehydration => 'heroicon-o-fire',
            self::ShrinkageSlicing => 'heroicon-o-minus-circle',
            self::ShrinkageExpiry => 'heroicon-o-clock',
            self::ShrinkageConfiscation => 'heroicon-o-no-symbol',
            self::CountAdjustment => 'heroicon-o-scale',
            self::SaleOut => 'heroicon-o-shopping-cart',
        };
    }

    /**
     * Tono del `StatusBadge` (docs/design.md): accent | success | warning | info | neutral.
     */
    public function tone(): string
    {
        return match ($this) {
            self::PurchaseIn => 'success',
            self::ButcheryIn,
            self::ButcheryOut => 'info',
            self::PurchaseVoid,
            self::ButcheryVoid => 'neutral',
            self::ShrinkageDehydration,
            self::ShrinkageSlicing,
            self::ShrinkageExpiry,
            self::ShrinkageConfiscation => 'warning',
            self::CountAdjustment => 'accent',
            self::SaleOut => 'neutral',
        };
    }
}
