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
- **S9** — «umbral de desviacion que significa? porque no se edita? no quiero nada fork». El umbral de desviación del despiece (BR-11, global por DEC-05) se edita en la misma pantalla, que pasa a llamarse «Configuración general» (secciones Identidad, Impuestos y Despiece). Valor entre 0 y 100, con 2,00 por defecto.
- **S10** — Moneda por defecto: «¿Hago configurable la moneda que aparece por defecto en compras y precios?» → «Sí, en Configuración general». Ajuste «Moneda por defecto» (USD o VES) en la sección nueva «Monedas», que usan como valor inicial los formularios y Actions de compras y precios. Los valores fijos `'USD'`/`'VES'` que se usan como *default* salen del código. La lógica dual USD+Bs no cambia (mercado: «Solo Venezuela»). Por defecto, el valor actual de cada lugar; si hoy difieren entre compras y precios, se conserva ese comportamiento hasta que se configure.
- **S11** — Zona horaria: «Fija Caracas, como dice C13». Se guarda en UTC y las fechas y horas se muestran en `America/Caracas` en la oficina (Filament) y en la PWA. Formato de §4 de design.md: «Lunes 9 de noviembre», «7:30 a. m.».
- **S12** — Roles: «¿Querés una pantalla para gestionar roles y permisos desde la oficina?» → «Sí, la planifico». Solo el diseño; no se escribe código hasta que el humano lo apruebe.

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T1 | S1, S3, S7, S8 | inline — `PermissionKey::SettingsManage`, `CompanySettings` (lectura + valores por defecto), `UpdateSettingsAction` (transacción + AuditLog), seeder ficticio, test arquitectura C6 | 3e0a4be |
| T2 | S2, S3, S6, S7 | inline — página Filament `ManageCompanySettings` (nombre, logo, favicon, brand, accent, IVA charcutería) | 3e0a4be |
| T3 | S4 | inline — `AdminPanelProvider` con closures desde Settings | 3e0a4be |
| T4 | S5 | inline — Inertia comparte `company`; `app.blade.php` inyecta `:root`; `app.css` pasa a `:root` + `@theme inline` | 3e0a4be |
| T6 | S9, S7 | inline — `CompanySettings::deviationThresholdPercent()`; `OrderController` y `ButcheryOrderResource` lo leen de ahí; campo en la pantalla | c6c95c4 |
| T7 | S10 | worker — `catalog.default_currency` (USD/VES, USD por defecto); las Actions de compra y precio sin moneda explícita usan el ajuste | cad41ac |
| T8 | S11 | worker — `FilamentTimezone::set(DisplayTimezone::CARACAS)`; `format.ts` con `formatDate`/`formatTime` en Caracas | fb987ca |
| T9 | S12 | explore (solo diseño) | — |
| T5 | S6 | inline — `CreateProductAction` lee el IVA de Settings; se elimina `config/graterol.php` | 9e79502 (borrado de config en 3e0a4be) |

Risk: item 2 (permiso nuevo y subida de archivos) e item 3 (`CreateProductAction` es código compartido del catálogo).

## Evidencia

- `artisan test`: 187/187 · `pint --test` ok · `types:check` 0 errores · `pnpm build` ok · `pnpm test` 21/21.
- Antes de corregirlos fallaron 2 tests existentes: `RolePermissionSeederTest` tenía fijos 13 permisos (ahora cuenta `PermissionKey::cases()`) y `C9NoFloatTest` detectó la palabra «float» en un comentario.
- Verificador independiente (gentle-ai-verify): S1–S8 cumplidos, sin bloqueantes. Dos observaciones, corregidas con su test: el regex hex usaba `$` (aceptaba un salto de línea final) y ahora usa `\z`, centralizado en `CompanySettings::HEX_COLOR_PATTERN`; los colores se validan también al leer, con fallback al valor por defecto si una fila se escribió fuera del Action. Suite final: 189/189, pint ok, types:check 0.
- Assess sobre 0baa01d..9e79502: risk high (escalado por el ítem 2), con verificador independiente; la revisión nativa no corresponde (under_budget).

## Log

- **L1** (2026-11-20, pedido literal): «ok listo si recuerda que nada debe estar fork, esto ademas es un piloto para vender el sistema, por lo que la configuracion debe ser dinamica» — aceptación de la propuesta `fix/m1-company-settings`.
- **L2** Decisiones del humano: datos = «Identidad visual» + «Solo identidad por ahora»; quién edita = «Solo administrador»; IVA de charcutería = «Sí, en este arreglo»; rama = «Desde feat/m1-products».
- **L4** Decisión técnica: en `app.css`, los tonos de la familia brand/accent se derivan con `color-mix()` para que una marca distinta quede coherente; se registró en design.md §2.4. El layout inyecta `html:root` para ganarle al CSS que Vite inyecta en desarrollo. El SVG queda excluido de los formatos de logo (riesgo de XSS al servirlo desde el mismo origen).
- **L3** Hallazgo: no existe ninguna página de Settings en el historial de git; `AdminPanelProvider.php:32-37` tenía fijos el nombre, el logo y los colores del piloto.
- **L5** (pedido literal): «umbral de desviacion que significa? porque no se edita? no quiero nada fork» → T6. Suite: 193/193, pint ok, types:check 0.
- **L6** Auditoría de valores fijos (explorador): no queda nada del piloto. Decisiones del humano: mercado «Solo Venezuela» (USD+Bs, BCV, RIF y Caracas quedan fijos por diseño); moneda por defecto configurable; C13 con Caracas fija; pantalla de roles «Sí, la planifico». La URL de la tasa queda en `.env`, por instalación (riesgo SSRF si fuera editable desde la oficina). Lo que todavía no existe (métodos de pago, EAN-13, IGTF, stock negativo, vencimientos, crédito) nace como configuración o catálogo en su hito.
- **L7** T7: con un solo ajuste no se podían conservar los dos valores por defecto anteriores (formularios en USD, Actions sin moneda en VES). Se eligió USD, que es lo que ve el usuario. El test `PurchasingResourcesTest` («shows purchase amounts in USD with the Bs line…») ahora pasa `currency: 'VES'` explícito en su preparación, y su expectativa no cambió. Cambio de comportamiento: una Action de compra o precio llamada sin moneda usa el ajuste (USD por defecto), no VES. T8: `FilamentTimezone` global; ningún Resource necesitó cambios por columna. Suite: 206/206, pint, types:check, build, Vitest 24/24. T9: diseño en `odd/tasks/m1-roles.md`.
- **L8** Verificador independiente de T7/T8: S10 y S11 cumplidos, sin bloqueantes. Ningún flujo de producción pasó en silencio de VES a USD, porque los formularios siempre pasaban la moneda. Se agregaron sus dos tests de protección: una fecha sin hora no se corre al día anterior, y una moneda explícita gana sobre el ajuste. Observación que ya venía de antes: las Actions no validan una moneda explícita fuera de USD/VES, pero ninguna ruta de usuario llega a ese caso.
