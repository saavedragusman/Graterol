# Constitution — ERP Graterol

Producto instalable y configurable para distribuidoras de cárnicos; Distribuidora Graterol es el piloto.
Esta es la ley del repo: el agente la lee antes de trabajar y GGA la usa como reglas en cada commit. Si una regla estorba, se cambia aquí en un commit propio; no se ignora.

## Stack
- **C1.** PHP 8.4 · Laravel 13 · PostgreSQL · Filament 5 (oficina) · Inertia + Vue 3 + TypeScript + Tailwind como PWA (mostrador, recepción, despiece). Sail en WSL, versiones fijadas en `compose.yaml`, pnpm como gestor JS. Una dependencia nueva la aprueba el humano y queda registrada en Engram.
- **C2.** Código, identificadores, tablas y permisos en inglés; textos de pantalla y mensajes en español.

## Arquitectura
- **C3.** Toda regla de negocio vive en una Action de `app/Domain/<Module>/Actions`, con su transacción y su test. Controladores (FormRequest + Policy + Action), recursos Filament y componentes Vue solo orquestan.
- **C4.** Módulos: `Settings`, `Catalog`, `Purchasing`, `Butchery`, `Inventory`, `Sales`, `Fiscal`, `Reports`. Un módulo no escribe tablas de otro: llama sus Actions.
- **C5.** El servidor es la autoridad: precios, costos, stock, impuestos, totales, vuelto y permisos se calculan y validan en el backend. La PWA solo calcula totales provisionales para vender sin conexión; al sincronizar, el servidor recalcula, su resultado manda y la diferencia queda registrada.
- **C6.** Nada del cliente en el código. Lo que varía entre negocios es configuración en datos simples (tablas y settings), nunca un motor de reglas ni plugins. Verifica: `grep -ri graterol app/ database/migrations` → 0.
- **C7.** Las integraciones (tasa BCV, fiscal, impresión) quedan fuera del núcleo y corren después del commit, por colas. La facturación sale solo por la interfaz `FiscalGateway`. Una caída externa nunca detiene la caja.
- **C8.** Una base de datos por cliente; sin `tenant_id`.

## Datos
- **C9.** Peso `numeric(12,3)`, dinero `numeric(18,4)`. `float`/`double` prohibidos en columnas, casts y cálculos de peso o dinero; se usa `BcMath\Number`. Verifica: grep en migraciones y `app/Domain` → 0.
- **C10.** Kardex de solo inserción: un trigger de PostgreSQL rechaza `UPDATE`/`DELETE` en `inventory_movements`. Cada movimiento guarda lote, cantidad, tipo, costo, usuario, referencia y motivo. Los errores se corrigen con un movimiento inverso.
- **C11.** Toda operación que toca varias tablas va en una transacción; nada queda a medias.
- **C12.** Lo cerrado no se edita (despiece cerrado, venta confirmada, cierre de caja, documento fiscal aceptado): se anula o se corrige con un documento inverso. Nada que tenga historial se borra físicamente.
- **C13.** Todo monto guarda moneda y tasa BCV usada. Timestamps en UTC, se muestran en `America/Caracas`.

## Seguridad
- **C14.** La autorización se hace solo con Policies y permisos `module.action`. Prohibidos `hasRole()`, `Gate::before` y `abort(403)`; un test de arquitectura en `tests/Architecture/` lo vigila.
- **C15.** Auditoría inmutable (quién, qué, cuándo, valores antes y después) solo de: anulaciones, devoluciones, ajustes de inventario, cambios de precio o tasa, cierres de caja y cambios de permisos.

## Calidad
- **C16.** Cada regla `BR-xx` de `docs/business/business-rules.md` tiene al menos un test Pest con su ID en el nombre. Las pruebas corren contra la base `testing` de PostgreSQL, nunca SQLite. TDD estricto en riesgo alto (C18); en lo demás, test en rojo primero cuando el resultado esperado es claro.
- **C17.** Antes de cada commit: Pest verde, Larastan nivel 6 sin errores, Pint, `pnpm build` y GGA aprobado.
- **C18.** Riesgo alto (costeo, kardex, caja, fiscal, migraciones con datos): verificador independiente y review 4R antes de cerrar el hito.

## Flujo ODD
- **C19.** No se inventa el negocio. Un comportamiento no definido se registra como `[DEC-PENDIENTE]` y solo esa parte se detiene.
- **C20.** Lo pequeño (se retoma desde el diff) va directo, sin documento. Lo grande lleva un solo `odd/tasks/<hito>-<feature>.md` con la especificación literal. No se crean otros documentos de planificación.
- **C21.** Las decisiones de dominio se guardan una vez en Engram y no se rediscuten sin un dato nuevo. Si Engram y el repo difieren, manda el repo.
- **C22.** Push, merge, deploy y migraciones fuera de local los decide el humano.

## Límites
- **C23.** Fuera de alcance: contabilidad general, nómina, tienda en línea y SaaS multi-tenant. Lo que no está en `docs/roadmap.md` es cambio de alcance y se cotiza.
- **C24.** El agente no lee ni escribe `.env` de producción ni secretos, y nunca corre `migrate:fresh` ni `db:wipe` fuera de local.
