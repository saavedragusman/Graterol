# M1 — Tasa BCV: carga diaria programada y registro manual

Rama `feat/m1-exchange-rate`, sale de `feat/m1-office-theme` (8301ee0).

## Specs

- **S1** — BR-02: «La tasa BCV es diaria: carga automática con respaldo manual. Cada operación guarda la tasa que usó.»
- **S2** — «la tasa que se muestra es la del 12». Hallazgo: no había ninguna tarea programada (`schedule:list` vacío), así que la carga automática nunca corría; `bcv:fetch` solo encola un job y la cola es `database`, que necesita un worker. El humano autorizó correrla una vez: se cargó 918,7662 el 2026-11-20.
- **S3** — «Programar la carga diaria», «8:00 a. m. de Caracas»: tarea en `routes/console.php`, a las 08:00 de `America/Caracas` (C13). Si la fuente falla, reintenta cada hora hasta las 12:00 mientras no haya una tasa automática de hoy. No debe depender de un worker de cola: ejecuta el job en el mismo proceso del programador. Una caída de la fuente externa nunca para nada más (C7; el job ya atrapa y registra el error).
- **S4** — «Pantalla para registrar la tasa a mano»: en «Configuración › Tasa BCV»: historial (fecha y hora en Caracas, tasa, fuente automática o manual, quién la registró) y acción «Registrar tasa» (valor en Bs por USD, con 4 decimales, mayor que cero, sin float, C9). Es el respaldo de BR-02.
- **S5** — C15: «cambios de precio o tasa» se auditan. Registrar una tasa manual deja un `AuditLog` (tipo `exchange_rate`, antes y después, usuario). Permiso propio `rates.manage`, de inicio solo en administrador, con etiqueta en español y módulo «Configuración» (`PermissionKey::label()`/`module()`). C14: solo Policy y permisos.
- **S6** — La tabla `exchange_rates` es de solo inserción: no se edita ni se borra ninguna fila desde la pantalla.

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T1 | S3 | worker — programación en `routes/console.php` con test | |
| T2 | S4, S5, S6 | worker — `PermissionKey::RatesManage`, Policy, página/recurso Filament, auditoría | |

## Log

- **L1** (2026-11-20): «la tasa que se muestra es la del 12».
- **L2** Decisiones del humano: «Cargar la de hoy ahora», «Programar la carga diaria», «Pantalla para registrar la tasa a mano»; hora «8:00 a. m. de Caracas».
- **L3** Datos: tras la carga manual hay dos filas iguales (ids 2 y 3, 918,7662); inofensivo por ser solo inserción.
