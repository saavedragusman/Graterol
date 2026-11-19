<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Única fuente de verdad para los permisos module.action (AGENTS.md §6, C14).
 * Ningún otro archivo debe escribir estos strings de forma literal.
 */
enum PermissionKey: string
{
    case PosSell = 'pos.sell';
    case PosCloseOwn = 'pos.close-own';
    case PurchasingView = 'purchasing.view';
    case PurchasingCreate = 'purchasing.create';
    case PurchasingReceive = 'purchasing.receive';
    case PurchasingVoid = 'purchasing.void';
    case PurchasingManageSuppliers = 'purchasing.manage-suppliers';
    case ButcheryProcess = 'butchery.process';
    case ButcheryManageTemplates = 'butchery.manage-templates';
    case ButcheryVoid = 'butchery.void';
    case CatalogManage = 'catalog.manage';
    case SalesVoid = 'sales.void';
    case SalesReturn = 'sales.return';
}
