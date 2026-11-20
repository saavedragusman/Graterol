<?php

declare(strict_types=1);

use App\Domain\Settings\PermissionKey;

// S1/S2: cada permiso debe tener nombre y grupo en español para la pantalla de roles.

it('gives every permission a non-empty label and module', function () {
    foreach (PermissionKey::cases() as $key) {
        expect(trim($key->label()))->not->toBe('', $key->value.' sin label')
            ->and(trim($key->module()))->not->toBe('', $key->value.' sin module');
    }
});

it('declares the role and user management permissions', function () {
    expect(PermissionKey::RolesManage->value)->toBe('roles.manage')
        ->and(PermissionKey::UsersManage->value)->toBe('users.manage')
        ->and(PermissionKey::RolesManage->module())->toBe('Configuración')
        ->and(PermissionKey::PurchasingVoid->label())->toBe('Anular compras');
});
