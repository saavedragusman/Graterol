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
    case SettingsManage = 'settings.manage';
    case AuditView = 'audit.view';
    case RolesManage = 'roles.manage';
    case UsersManage = 'users.manage';
    case RatesManage = 'rates.manage';

    /** Nombre en español para la pantalla de roles; todo caso nuevo debe tener el suyo. */
    public function label(): string
    {
        return match ($this) {
            self::PosSell => 'Vender en el POS',
            self::PosCloseOwn => 'Cerrar su propia caja',
            self::PurchasingView => 'Ver compras',
            self::PurchasingCreate => 'Crear compras',
            self::PurchasingReceive => 'Recibir compras',
            self::PurchasingVoid => 'Anular compras',
            self::PurchasingManageSuppliers => 'Gestionar proveedores',
            self::ButcheryProcess => 'Procesar despieces',
            self::ButcheryManageTemplates => 'Gestionar plantillas de despiece',
            self::ButcheryVoid => 'Anular despieces',
            self::CatalogManage => 'Gestionar el catálogo de productos',
            self::SalesVoid => 'Anular ventas',
            self::SalesReturn => 'Devolver ventas',
            self::SettingsManage => 'Gestionar la configuración de la empresa',
            self::AuditView => 'Ver la auditoría',
            self::RolesManage => 'Gestionar roles y permisos',
            self::UsersManage => 'Gestionar usuarios',
            self::RatesManage => 'Registrar tasa BCV',
        };
    }

    /** Grupo en español con el que se agrupan los permisos en pantalla. */
    public function module(): string
    {
        return match ($this) {
            self::PurchasingView,
            self::PurchasingCreate,
            self::PurchasingReceive,
            self::PurchasingVoid,
            self::PurchasingManageSuppliers => 'Compras',
            self::ButcheryProcess,
            self::ButcheryManageTemplates,
            self::ButcheryVoid => 'Despiece',
            self::CatalogManage => 'Catálogo',
            self::PosSell,
            self::PosCloseOwn => 'POS',
            self::SalesVoid,
            self::SalesReturn => 'Ventas',
            self::SettingsManage,
            self::AuditView,
            self::RolesManage,
            self::UsersManage,
            self::RatesManage => 'Configuración',
        };
    }
}
