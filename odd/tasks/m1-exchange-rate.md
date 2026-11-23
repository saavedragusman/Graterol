# M1 — Tasa BCV: carga diaria programada y registro manual

Rama `feat/m1-exchange-rate`, sale de `feat/m1-office-theme` (8301ee0).

## Specs

- **S1** — BR-02: «La tasa BCV es diaria: carga automática con respaldo manual. Cada operación guarda la tasa que usó.»
- **S2** — «la tasa que se muestra es la del 12». Hallazgo: no había ninguna tarea programada (`schedule:list` vacío), así que la carga automática nunca corría; `bcv:fetch` solo encola un job y la cola es `database`, que necesita un worker. El humano autorizó correrla una vez: se cargó 918,7662 el 2026-11-20.
- **S3** — «Programar la carga diaria», «8:00 a. m. de Caracas»: tarea en `routes/console.php`, a las 08:00 de `America/Caracas` (C13). Si la fuente falla, reintenta cada hora hasta las 12:00 mientras no haya una tasa automática de hoy. No debe depender de un worker de cola: ejecuta el job en el mismo proceso del programador. Una caída de la fuente externa nunca para nada más (C7; el job ya atrapa y registra el error).
- **S4** — «Pantalla para registrar la tasa a mano»: en «Configuración › Tasa BCV»: historial (fecha y hora en Caracas, tasa, fuente automática o manual, quién la registró) y acción «Registrar tasa» (valor en Bs por USD, con 4 decimales, mayor que cero, sin float, C9). Es el respaldo de BR-02.
- **S5** — C15: «cambios de precio o tasa» se auditan. Registrar una tasa manual deja un `AuditLog` (tipo `exchange_rate`, antes y después, usuario). Permiso propio `rates.manage`, de inicio solo en administrador, con etiqueta en español y módulo «Configuración» (`PermissionKey::label()`/`module()`). C14: solo Policy y permisos.
- **S6** — La tabla `exchange_rates` es de solo inserción: no se edita ni se borra ninguna fila desde la pantalla.
- **S7** — «tasa bcv debe tener solo 2 decimales». Alcance elegido por el humano: «También al guardarla y al convertir». La tasa se guarda y se usa con 2 decimales (half-up) en la carga automática y en la manual; una tasa que redondea a cero se rechaza. Se agregó a BR-02 en `docs/business/business-rules.md`. La columna sigue en `numeric(18,4)` (C9, sin migración): el valor queda como `918.7700`. Las filas anteriores no se tocan (tabla de solo inserción): la vigente sigue siendo 918,7662 hasta la próxima carga.

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T1 | S3 | worker — programación en `routes/console.php` con test | 430d931 |
| T2 | S4, S5, S6 | worker — `PermissionKey::RatesManage`, Policy, página/recurso Filament, auditoría | a7bf419 |
| T3 | S7 | inline — redondeo en `RegisterExchangeRateAction` (`SCALE = 2`), manual, pantalla y BR-02 | 5d14522 |

## Log

- **L1** (2026-11-20): «la tasa que se muestra es la del 12».
- **L2** Decisiones del humano: «Cargar la de hoy ahora», «Programar la carga diaria», «Pantalla para registrar la tasa a mano»; hora «8:00 a. m. de Caracas».
- **L3** Datos: tras la carga manual hay dos filas iguales (ids 2 y 3, 918,7662); inofensivo por ser solo inserción.
- **L4** T1 en 430d931 y T2 en a7bf419: 339/339, pint, types:check 0. `schedule:list` muestra `bcv-rate-fetch` (08:00–12:00 Caracas). RED observado. Para que corra hace falta un proceso del programador: local `./vendor/bin/sail artisan schedule:work`; producción, cron `* * * * * cd /ruta && php artisan schedule:run`. El `withoutOverlapping` necesita un `CACHE_STORE` con locks (no se leyó `.env`). Sin ver en navegador el ítem «Tasa BCV» del menú. Pasos del humano: migrar no hace falta; sí `db:seed --class=RolePermissionSeeder` para que el administrador reciba `rates.manage`.
- **L5** (pedido literal): «tasa bcv debe tener solo 2 decimales». Pregunta: «¿Dónde debe tener solo 2 decimales la tasa BCV?» → «También al guardarla y al convertir». Mensaje posterior del humano: «quedamos que todos los montos de dinero tendrían solo 2 decimales en cualquier tasa»; los montos ya se muestran con 2 (BR-01) y se guardan con 4 (BR-01/C9); si se quiere guardar con 2 hace falta cambiar C9 y BR-01 primero (pendiente de aclarar). Suite: 347/347, pint, types:check 0.
