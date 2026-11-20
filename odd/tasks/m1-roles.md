# M1 — Gestión de roles, permisos y usuarios desde la oficina

Hito M1 (BR-03, roles). Rama `feat/m1-roles`, que sale de `fix/m1-company-settings` al cerrarla.
Estado: T4 hecho. Diseño de T1–T3 aprobado completo (2026-11-20, L6). En implementación.

## Specs

- **S1** — BR-03: «Roles iniciales como conjuntos de permisos `module.action` (C14): administrador (todos los permisos asignados), cajero (POS y su propio cierre) y carnicero (recepción y despiece). Anular o devolver requiere un permiso propio, que de inicio solo tiene el administrador.»
- **S2** — «Roles y usuarios»: pantallas «Configuración › Roles» y «Configuración › Usuarios» en Filament. En Usuarios: crear, asignar roles, desactivar y restablecer la contraseña.
- **S3** — «Editables, menos administrador»: cajero, carnicero y los roles nuevos se crean, renombran, editan o borran. El rol administrador no se borra y siempre conserva todos los permisos.
- **S4** — «Solo desactivar, pero se puede borrar si no tiene historial»: un usuario inactivo no entra al panel ni a la PWA, pero su historial queda. Solo se puede borrar un usuario que no aparece en ninguna tabla: compras, lotes, movimientos, despiece, tasas, auditoría, etc.
- **S5** — C15: «cambios de permisos» se auditan, igual que la asignación de roles y la desactivación de usuarios: quién, qué, cuándo, antes y después.
- **S6** — C14: solo Policies y permisos; prohibidos `hasRole()`, `Gate::before` y `abort(403)`.
- **S7** — Nunca quedarse afuera: ningún cambio (de roles, permisos, asignaciones o desactivación) puede dejar el sistema sin al menos un usuario activo con permiso para gestionar roles.
- **S8** — Vista de auditoría: «había una opción en el menú, para ver las auditorías y sus movimientos se abrían en un popup». Va en «Configuración › Auditoría», es de solo lectura (C15: inmutable) y muestra fecha y hora en Caracas, usuario, qué se tocó, acción y motivo. Filtros por tipo, usuario y rango de fechas, con lo más nuevo primero. El botón «Ver» abre un popup con el detalle: el valor anterior y el nuevo, campo por campo. Quién la ve: «Solo administrador», con el permiso `audit.view`.

## Diseño técnico (aprobado)

- Permisos nuevos en `PermissionKey`: `roles.manage` y `users.manage`. `PermissionKey::label()` y `module()` dan el nombre en español y el grupo (Compras, Despiece, Catálogo, POS, Ventas, Configuración). Los permisos futuros (`inventory.*` de M4) aparecen solos en la pantalla.
- Actions en `app/Domain/Settings/Actions`: `CreateRoleAction`, `UpdateRoleAction` (nombre y permisos), `DeleteRoleAction` (rechaza administrador y roles con usuarios), `CreateUserAction`, `UpdateUserRolesAction`, `DeactivateUserAction` / `ReactivateUserAction`, `ResetUserPasswordAction` y `DeleteUserAction` (solo sin historial). Todas transaccionales, con `AuditLog` y con la guarda de S7.
- Migración nueva: `users.is_active` (boolean, true por defecto) y `roles.is_system` (boolean) para marcar administrador sin depender del nombre. Si se renombra, se sigue reconociendo.
- `User::canAccessPanel()` y el login de la PWA rechazan a los usuarios inactivos.
- `RolePermissionSeeder`: crea los roles iniciales **solo si no existen** y agrega al administrador los permisos nuevos de `PermissionKey`. Deja de pisar con `sync()` lo editado desde la pantalla.
- Policies `RolePolicy` y `UserPolicy`.

## Riesgo

Ítem 2 (seguridad: permisos, contraseñas, acceso) e ítem 3 (cambia el comportamiento del seeder). Va con verificador independiente y con assess de RDD.

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T1 | S1, S6 | `PermissionKey` label/module + permisos nuevos + seeder no destructivo | |
| T2 | S3, S5, S7 | Actions y Policy de roles + pantalla Roles | |
| T4 | S8, S6 | worker — `AuditLogResource` de solo lectura + `audit.view` + `AuditLogPolicy`; «Ver» autoriza por registro | dacf9de |
| T3 | S2, S4, S5, S7 | migración `is_active`, Actions y Policy de usuarios + pantalla Usuarios + bloqueo de inactivos | |

## Log

- **L1** (2026-11-20) Pregunta: «¿Querés una pantalla para gestionar roles y permisos desde la oficina?» → «Sí, la planifico».
- **L2** Decisiones: «Roles y usuarios»; «Editables, menos administrador»; «Solo desactivar, pero se puede borrar si no tiene historial»; «Rama propia: feat/m1-roles».
- **L3** Hallazgos (explorador): no hay pantalla de usuarios ni de roles; los usuarios solo se crean por seeder; `RolePermissionSeeder` hace `sync()` en cada corrida; no hay ninguna guarda contra el bloqueo; varios tests usan los nombres fijos de los roles.
- **L4** (pedido literal): «ok quiero añadir otra cosa que tambien estoy seguro que ya habia porque lo vi varias veces y hasta lo probe fue la vista de auditoria, habia una opcion en el menu, para ver las audiorias y sus movimientos se abrian en un popup que paso que ahora no se ve?». Búsqueda: no existe en ninguna rama, en el historial de `app/Filament`, en los objetos sueltos ni en los paquetes; solo `audit_logs` y su modelo (M2). Decisiones: «No, construila de cero»; «Solo administrador»; «En feat/m1-roles».
- **L5** T4 en dacf9de: 215/215, pint, types:check 0, build ok. RED observado (7/7). Se agregó `->authorize('view')` al popup «Ver», para que el detalle también pase por la Policy.
- **L6** Confirmación del humano: migración `users.is_active` + `roles.is_system`: «Sí, aprobada»; seeder no destructivo (crea los roles iniciales solo si no existen y agrega al administrador los permisos nuevos): «Sí, así».
