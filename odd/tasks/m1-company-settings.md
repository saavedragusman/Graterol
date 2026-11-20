# M1 — Configuración de empresa (marca dinámica)

Hito M1 (ruta directa). Rama `fix/m1-company-settings`, sale de `feat/m1-products` (0baa01d).
Saca del código los datos del piloto (C6): nombre, logo, favicon, colores e IVA de charcutería pasan a Settings, editables desde la oficina.

## Specs

- **S1** — «nada debe estar fijo… esto además es un piloto para vender el sistema, por lo que la configuración debe ser dinámica». Ningún dato del cliente queda en el código. Verificación de C6: «`grep -ri graterol app/ database/migrations` → 0».
- **S2** — Datos de empresa: «Identidad visual: nombre comercial, logo, favicon, color brand y color accent». «Solo identidad por ahora»: los datos fiscales se definen cuando llegue el ticket o la factura.
- **S3** — Se editan desde una pantalla Filament de configuración. El permiso es `settings.manage` y lo tiene «solo administrador».
- **S4** — El panel de oficina toma el nombre, el logo, el favicon y los colores de Settings, sin recompilar. `primary` = brand y un color propio `accent` (design.md §2.5). Se mantiene el modo oscuro.
- **S5** — La PWA toma el nombre y los colores de Settings: `:root { --brand; --accent }` inyectado en el layout (design.md §2.4).
- **S6** — La alícuota de IVA de charcutería sale de `config/graterol.php` y pasa a Settings, editable en la misma pantalla. El valor por defecto es 16,00 (`[abierto]` en BR-05).
- **S7** — Cada cambio de configuración se audita (C15): usuario, valor anterior y valor nuevo. Una entrada inválida (hex mal formado, archivo que no es imagen, IVA fuera de 0–100) no cambia nada.
- **S8** — Sin configuración, el sistema usa los valores neutros del producto. Los seeds usan datos ficticios, nunca los del piloto (AGENTS §6).

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T1 | S1, S3, S7, S8 | inline — `PermissionKey::SettingsManage`, `CompanySettings` (lectura + valores por defecto), `UpdateSettingsAction` (transacción + AuditLog), seeder ficticio, test arquitectura C6 | |
| T2 | S2, S3, S6, S7 | inline — página Filament `ManageCompanySettings` (nombre, logo, favicon, brand, accent, IVA charcutería) | |
| T3 | S4 | inline — `AdminPanelProvider` con closures desde Settings | |
| T4 | S5 | inline — Inertia comparte `company`; `app.blade.php` inyecta `:root`; `app.css` pasa a `:root` + `@theme inline` | |
| T5 | S6 | inline — `CreateProductAction` lee el IVA de Settings; se elimina `config/graterol.php` | |

Risk: item 2 (permiso nuevo y subida de archivos) e item 3 (`CreateProductAction` es código compartido del catálogo).

## Log

- **L1** (2026-11-20, pedido literal): «ok listo si recuerda que nada debe estar fork, esto ademas es un piloto para vender el sistema, por lo que la configuracion debe ser dinamica» — aceptación de la propuesta `fix/m1-company-settings`.
- **L2** Decisiones del humano: datos = «Identidad visual» + «Solo identidad por ahora»; quién edita = «Solo administrador»; IVA de charcutería = «Sí, en este arreglo»; rama = «Desde feat/m1-products».
- **L4** Decisión técnica: en `app.css`, los tonos de la familia brand/accent se derivan con `color-mix()` para que una marca distinta quede coherente; se registró en design.md §2.4. El layout inyecta `html:root` para ganarle al CSS que Vite inyecta en desarrollo. El SVG queda excluido de los formatos de logo (riesgo de XSS al servirlo desde el mismo origen).
- **L3** Hallazgo: no existe ninguna página de Settings en el historial de git; `AdminPanelProvider.php:32-37` tenía fijos el nombre, el logo y los colores del piloto.
