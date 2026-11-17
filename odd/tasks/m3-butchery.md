# M3 — Despiece y costeo

> Documento ODD del hito M3. Rama: `feat/m3-butchery` (se crea al aprobar este documento).
> Copia en Engram: `odd/m3-butchery/tasks`.
> **Estado: decisiones de §3 cerradas (2026-11-17) — pendiente de la aprobación final del humano para empezar la tarea 1.1** (AGENTS §4.3 puerta 1).

## 1. Fuente

- **Roadmap, M3:** «Despiece y costeo» · 16 h · ODD: **feature + verificador + 4R** · Hecho cuando: «BR-08 a BR-13 en verde, incluido el caso dorado». El roadmap marca M3 con verificador + 4R (no solo "feature", como M2) por el riesgo alto de C18: costeo.
- **Pedido del humano (2026-11-17):** planificar M3 en base a constitution, business-rules y los documentos de M0, M1 y M2; crear el documento del feature sin dar nada por sobreentendido y considerando el diseño de pantallas en `docs/design/screens/`.
- **Constitution:** C4 (módulo `Butchery` propio, no escribe tablas de `Inventory` ni `Catalog`, llama sus Actions), C9 (sin float), C10 (kardex solo inserción), C11 (transacciones), C12 (orden cerrada no se edita, se anula), C13 (moneda y tasa en cada monto), C14 (Policies y permisos `module.action`), C18 (riesgo alto → verificador + 4R), C19 (no inventar el negocio).
- **Glosario de M0** (`odd/tasks/m0-foundation.md` §4.1): ya define «Plantilla», «Corte», «Merma», «Rendimiento» y «Orden de despiece» como tablas propias de M3.
- **`report/Propuesta-tecnica.docx`** (fuente de BR-13): contiene la tabla completa del "caso dorado" con columnas que business-rules.md resume parcialmente. Se cita íntegra en §3, DEC-00, porque es necesaria para implementar BR-12 y no está completa en business-rules.md.

## 2. Alcance

| BR | Qué cubre M3 |
|---|---|
| **BR-08** | Plantilla de despiece por materia prima: cortes con % estándar + merma esperada, suman 100 %. |
| **BR-09** | Orden de despiece: lote de entrada, kg de entrada, carnicero; muestra kg esperados por corte. |
| **BR-10** | Merma real = kg entrada − Σ kg salida. Salidas que superan la entrada rechazan la orden. |
| **BR-11** | Alerta cuando un corte se desvía de la plantilla más del umbral configurado (2 puntos por defecto). |
| **BR-12** | Costeo por valor relativo de mercado; la merma no lleva costo; residuo de redondeo al corte de mayor valor. |
| **BR-13** | Caso dorado (20 kg de pollo a US$ 56,00) como prueba de aceptación de BR-12 — con la salvedad de DEC-00. |

**Reglas transversales ya implementadas, no se reimplementan:** BR-01 (peso/monto), BR-02 (tasa BCV, no aplica directamente porque el despiece no vuelve a comprar), BR-03 (permisos: el carnicero ya tiene `butchery.process` desde M1), BR-04/BR-05 (catálogo: los cortes son productos tipo `derivado` ya existentes, con su IVA e listas de precio), C10 (kardex, se reutiliza `inventory_movements` y sus Actions de `Inventory`).

**Fuera de alcance de M3** (quedan para su propio hito):
- BR-14 a BR-17 (M4): FEFO, mermas tipificadas de inventario (deshidratación, rebanado, vencimiento/decomiso), conteo físico. M3 solo necesita saber el saldo disponible de un lote (Σ movimientos, ver DEC-07), no construye FEFO ni los otros tipos de merma.
- BR-28 (M6): el reporte de margen por corte y rendimiento por carnicero. M3 deja los datos que ese reporte va a leer (línea de orden con % esperado, kg real, costo asignado, carnicero), pero no construye la pantalla de reporte.
- Despiece sin conexión: BR-26 solo cubre ventas. `docs/design.md` §5 marca esto como `[DEC-PENDIENTE]` "si se necesita"; no se activa en M3 — la PWA de despiece necesita conexión, igual que la de recepción en M2.
- Lectura de balanza por puerto serie («Leer balanza» en la maqueta): backlog declarado en el roadmap. En M3 el peso se digita (ver DEC-08).
- Almacenes/bodegas: ninguna BR de este alcance lo pide; no se inventa (C19).
- Plantillas por "categoría" compartidas entre varios productos, o umbral por plantilla/corte: ver DEC-02 y DEC-05.

## 3. Decisiones

```text
[CORREGIDO 2026-11-17] M3/BR-12, BR-13 — DEC-00 (ya no es una decisión: era un error de cálculo propio)
Al redactar este documento calculé a mano el costeo de BR-12 redondeando directo a 2 decimales, y encontré
una supuesta discrepancia con BR-13 (Muslo salía en US$ 19,15, no US$ 19,16). Esa cuenta a mano estaba mal
hecha: redondeaba a 2 decimales en vez de a los 4 decimales internos que BR-01/C9 exigen para todo monto.
Recalculando con precisión real (4 decimales internos, luego 2 para pantalla, como manda `Money`):

  Corte             kg    Precio US$/kg  kg·p    Costo interno (4 dec.)   Costo en pantalla (2 dec.)   BR-13 dice
  Filete de pechuga 4,80  6,50            31,20   24,5738                  24,57                        24,57
  Muslo             6,40  3,80            24,32   19,1550                  19,16                        19,16
  Alas              2,00  3,50             7,00    5,5134                   5,51                         5,51
  Carapacho         4,40  1,20             5,28    4,1586                   4,16                         4,16
  Hígado y molleja  1,20  2,50             3,00    2,3629                   2,36                         2,36
  Piel y grasa      0,60  0,50             0,30    0,2363                   0,24                         0,24
  Σ kg·p = 71,10                                  56,0000 exacto (sin residuo)                           56,00

Los seis valores de BR-13 salen exactos, sin necesidad de tocar el residuo (en este ejemplo el redondeo a
4 decimales ya suma 56,0000 limpio) y sin reinterpretar "mayor valor" en BR-12. No había ninguna
inconsistencia real entre BR-12 y BR-13: la había en mi primera cuenta a mano. Verificado con `python3`
(precisión decimal exacta) y reproducido en el test del caso dorado (tarea 5.1) con el mismo resultado.
```
**No bloqueaba nada; se cierra sola.** `AllocateButcheryCostsAction` implementa BR-12 literal (reparto
proporcional a kg·p, 4 decimales internos, residuo — si lo hay en otros casos — al corte de mayor valor
monetario); el caso dorado de BR-13 sale exacto, sin aproximaciones ni excepciones.

```text
[DEC-PENDIENTE] M3/BR-12 — DEC-01 Precio de referencia (p_i) para el costeo
Contexto: BR-12 usa p_i = "precio de venta de referencia" de cada corte. El catálogo (M1, BR-04) ya
guarda listas de precio detal y mayor por producto; los cortes son productos tipo `derivado`.
Duda: ¿p_i es el precio detal vigente del producto-corte al cerrar la orden, el precio mayor, o un
precio de referencia propio por línea de plantilla (independiente del precio de venta configurado)?
Opciones:
  A) Precio detal vigente del producto-corte en el catálogo, leído al cerrar la orden.
  B) Precio de referencia propio, editable por línea de plantilla, que puede diferir del precio de
     venta real (dos precios por corte: el de venta y el de costeo).
  C) Precio mayor vigente.
Recomendación técnica: A. Es el dato que ya existe (BR-04), evita mantener dos precios por producto, y
es coherente con que el carnicero no ve montos pero el sistema sí calcula con lo configurado en Catalog.
Si un corte no tiene precio detal configurado, `CloseButcheryOrder` rechaza con un mensaje claro (no se
inventa un precio).
```
**Resuelta: A, 2026-11-17.** p_i = precio de lista detal vigente del producto-corte en el catálogo, leído al cerrar la orden. Si falta, `CloseButcheryOrder` rechaza.

```text
[DEC-PENDIENTE] M3/BR-08 — DEC-02 Granularidad de la plantilla
Contexto: BR-08 dice "cada materia prima tiene una plantilla".
Duda: ¿una plantilla por producto materia prima específico (ej. "Pollo entero"), o una plantilla
reutilizable por categoría compartida entre varios productos?
Opciones:
  A) Una plantilla activa por producto materia prima.
  B) Plantilla por categoría de materia prima, compartida entre productos.
Recomendación técnica: A. Es la lectura literal de BR-08 y la más simple (C6); B no tiene BR que la pida.
```
**Resuelta: A, 2026-11-17.** Una plantilla activa por producto materia prima específico.

```text
[DEC-PENDIENTE] M3/BR-08, BR-09 — DEC-03 Edición y versionado de la plantilla (calibración)
Contexto: la propuesta técnica prevé recalibrar los % de la plantilla con las primeras 5-10 órdenes
reales. BR-09 dice que la orden "muestra los kg esperados por corte". C12 protege documentos cerrados,
no catálogos de configuración, así que editar la plantilla en sí no viola C12 — la duda es qué pasa con
las órdenes ya cerradas si la plantilla cambia después.
Duda: ¿la plantilla se edita en el sitio y cada orden congela su propio % esperado al abrirse (para que
una orden cerrada no cambie si luego se recalibra la plantilla), o cada edición crea una versión nueva?
Opciones:
  A) Edición en el sitio; cada orden copia (congela) el % esperado de cada corte en sus propias líneas
     al abrirse. La plantilla "vigente" es siempre la que ve una orden nueva.
  B) Versionado completo: cada edición marca una plantilla nueva como activa y la anterior queda
     histórica pero visible.
Recomendación técnica: A. Mismo criterio que M2 usa con la tasa BCV (se congela en cada operación, C13);
el reporte de rendimiento (BR-28, M6) compara contra el snapshot guardado en cada orden, no contra "la"
plantilla actual.
```
**Resuelta: A, 2026-11-17.** Edición en el sitio; cada orden congela (snapshot) el % esperado de cada corte en `butchery_order_lines` al abrirse.

```text
[DEC-PENDIENTE] M3/BR-11 — DEC-05 Alcance del umbral de desviación configurable
Contexto: BR-11 dice "el umbral configurado (2 puntos por defecto)", en singular.
Duda: ¿el umbral es un solo valor global del sistema, o configurable por plantilla o por corte?
Opciones:
  A) Global, un solo valor en Settings. 2 puntos por defecto.
  B) Por plantilla (cada materia prima su propio umbral).
  C) Por línea de plantilla (cada corte su propio umbral).
Recomendación técnica: A. BR-11 habla en singular de "el umbral", sin distinguir por producto; es la
lectura más simple y coherente con C6. Se puede ampliar a B o C después si se necesita, sin romper nada.
```
**Resuelta: A, 2026-11-17.** Umbral global en `settings` (`butchery.deviation_threshold_percent`), 2 puntos por defecto (DEC-06).

```text
[DEC-PENDIENTE] M3/BR-09, BR-10 — DEC-07 ¿Una orden puede tomar solo parte de un lote?
Contexto: BR-09 dice que la orden registra "el lote y los kg de entrada", sin aclarar si siempre es
el lote completo. El negocio despieza canales completas, pero puede no hacerlo todo en un solo turno.
Duda: ¿una orden de despiece puede tomar menos kg que el saldo disponible del lote (dejando el resto
para otra orden después), o siempre debe consumir el lote completo de una vez?
Opciones:
  A) Puede ser parcial: la orden valida que los kg de entrada ≤ saldo disponible del lote (Σ movimientos
     del lote, sin esperar a M4/FEFO — es solo una suma); el lote puede despiezarse en varias órdenes.
  B) Siempre completo: una orden consume todo el saldo del lote de una sola vez.
Recomendación técnica: A. Bloquear a "todo o nada" no es realista (un pollo de 20 kg puede despiezarse
en dos tandas); la validación de saldo es una consulta simple sobre `inventory_movements`, no requiere
construir FEFO ni ninguna otra pieza de M4.
```
**Resuelta: A, 2026-11-17.** Puede ser parcial; `OpenButcheryOrder` valida `input_weight_kg ≤` saldo disponible del lote (Σ movimientos).

**Decisiones técnicas resueltas sin bloquear (C19, puramente técnicas):**

- **DEC-04 — Consumo del lote de entrada al abrir, no al cerrar.** El movimiento `butchery_in` (salida del lote de materia prima) se registra al **abrir** la orden, dentro de la misma transacción que valida el saldo (DEC-07), con bloqueo de fila (`lockForUpdate`) sobre el lote. Así dos órdenes abiertas a la vez no pueden sobre-consumir el mismo lote (evita una condición de carrera). Si la orden se anula estando abierta, se revierte ese movimiento. Es el mismo principio que M2 usa al confirmar una recepción (el movimiento nace cuando se compromete el lote), solo que aquí el "compromiso" ocurre al abrir en vez de al cerrar, porque cerrar puede tardar (varias pesadas).
- **DEC-06 — Umbral de desviación: tabla `settings`.** M1 no construyó una tabla de configuración general de clave/valor (solo `exchange_rates`, específica de la tasa). Para BR-11 se crea una tabla mínima `settings` (`key` string único, `value` string) en el módulo `Settings`, con una fila sembrada `butchery.deviation_threshold_percent = 2.00`. Es infraestructura general reutilizable para cualquier futuro valor configurable simple (C6), no solo de despiece.
- **DEC-08 — "Leer balanza" en la maqueta.** `docs/design.md` §5 ya aclara que es backlog (balanza por puerto serie); en M3 el botón principal de la pantalla de despiece es «Registrar peso» (el carnicero digita el peso), no «Leer balanza». La maqueta (`m-despiece.html`) se sigue en todo lo demás (ver §4.5).
- **Residuo de redondeo en costeo (BR-12).** Mismo criterio que M2 DEC-02: el residuo va al corte de mayor valor monetario (kg_i × p_i más alto), para que Σ costos = costo total exacto. Ver DEC-00 para la salvedad sobre el caso dorado literal.

## 4. Diseño técnico

> Supuestos a verificar contra M0/M1/M2 antes de la tarea 1.1: existe `app/Domain/Settings/PermissionKey.php` (enum de permisos) con `butchery.process` ya sembrado al rol carnicero; existe `batches` con `source_type`/`source_id` polimórficos y `total_cost`/`unit_cost`; existe `inventory_movements` con su trigger de solo inserción y el tipo `purchase_in` ya en uso (`butchery_in`/`butchery_out` están previstos en el glosario de M0 pero ningún código los escribe todavía — `butchery_out` solo aparece como dato de prueba manual en `VoidPurchaseActionTest.php`); no existe ninguna tabla `settings` de clave/valor. Si algo de esto no coincide, se anota aquí antes de seguir.

### 4.1 Tablas (módulo `Butchery`, salvo lotes y movimientos, que son de `Inventory`)

**`settings`** (módulo `Settings`, DEC-06; tabla mínima de configuración general)
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| key | string, unique | Ej. `butchery.deviation_threshold_percent` |
| value | string | Se castea en el Action/Policy que la lee |

**`butchery_templates`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| raw_material_product_id | FK `products` | Producto tipo `materia_prima` (BR-08, DEC-02) |
| name | string | Ej. "Pollo entero" |
| expected_shrinkage_percent | numeric(5,2) | Merma esperada (BR-08) |
| is_active | boolean, default true | Índice único parcial `(raw_material_product_id) WHERE is_active` — solo una plantilla activa por materia prima (DEC-02) |
| created_by | FK users | |
| timestamps | | |

**`butchery_template_lines`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| template_id | FK `butchery_templates` | |
| cut_product_id | FK `products` | Producto tipo `derivado` (el "corte", BR-04/BR-08) |
| expected_yield_percent | numeric(5,2) | % estándar del corte |
| unique(template_id, cut_product_id) | | Un corte no se repite en la misma plantilla |

Validación a nivel de Action (no de columna, C3): `Σ expected_yield_percent` (líneas) `+ expected_shrinkage_percent` (plantilla) `= 100` (tolerancia ±0,01 por redondeo).

**`butchery_orders`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| code | string, unique | Ej. `D-0118` (análogo al código de lote) |
| batch_id | FK `batches` | Lote de entrada (materia prima); BR-09 |
| template_id | FK `butchery_templates` | Referencia informativa; el % real queda congelado en las líneas (DEC-03) |
| input_weight_kg | numeric(12,3) | Kg de entrada (BR-09, BR-01) |
| expected_shrinkage_percent | numeric(5,2) | Snapshot de la plantilla al abrir (DEC-03) |
| butcher_id | FK users | Carnicero (BR-09, BR-03) |
| status | string | `open` → `closed` \| `voided` |
| actual_shrinkage_kg | numeric(12,3), nullable | Calculado al cerrar (BR-10) |
| closed_at, voided_at | timestamptz, nullable | UTC (C13) |
| voided_by | FK users, nullable | |
| void_reason | string, nullable | |
| timestamps | | |

**`butchery_order_lines`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| order_id | FK `butchery_orders` | |
| cut_product_id | FK `products` | |
| expected_yield_percent | numeric(5,2) | Snapshot congelado de la plantilla al abrir (DEC-03) |
| expected_weight_kg | numeric(12,3) | `input_weight_kg × expected_yield_percent / 100`, calculado al abrir (BR-09) |
| actual_weight_kg | numeric(12,3), nullable | Kg real de salida; llenado al pesar, puede ser 0 |
| reference_price_per_kg | numeric(18,4), nullable | Snapshot de p_i (DEC-01), tomado al cerrar |
| allocated_cost | numeric(18,4), nullable | Costo asignado (BR-12), calculado al cerrar |
| deviation_percent | numeric(6,2), nullable | `(actual_weight_kg / input_weight_kg × 100) − expected_yield_percent` (BR-11) |
| output_batch_id | FK `batches`, nullable | Lote de salida creado al cerrar (si `actual_weight_kg > 0`) |
| timestamps | | |

Todo peso y monto en `BcMath\Number`, nunca float (C9). Ningún campo de `batches` cambia: `source_type = 'butchery_order_line'` y `source_id` = id de la línea, reutilizando el mismo patrón polimórfico que M2 (`source_type = 'purchase_line'`).

### 4.2 Movimientos de inventario (tipos nuevos, módulo `Inventory`)

- `butchery_in`: salida (cantidad negativa) del lote de materia prima consumido, registrada al **abrir** la orden (DEC-04).
- `butchery_out`: entrada (cantidad positiva) a cada lote de salida (corte), registrada al **cerrar** la orden, una por línea con `actual_weight_kg > 0`.

Ambos ya estaban previstos en el glosario de M0 y en el comentario de la migración de `inventory_movements`; M3 es el primer hito que los escribe de verdad.

### 4.3 Actions

| Action | Módulo | Qué hace | BR |
|---|---|---|---|
| `CreateButcheryTemplate` / `UpdateButcheryTemplate` | Butchery | Valida Σ% = 100 (BR-08); solo materia prima como origen y derivados como cortes; una activa por materia prima (DEC-02). Edición en el sitio (DEC-03). | BR-08 |
| `OpenButcheryOrder` | Butchery | Transacción (C11) con `lockForUpdate` sobre el lote: valida permiso `butchery.process`; valida que el lote sea de una materia prima con plantilla activa; valida `input_weight_kg ≤` saldo disponible del lote (DEC-07); congela cada línea de la plantilla en `butchery_order_lines`; registra `butchery_in` (DEC-04). | BR-09 |
| `RegisterButcheryOutputWeight` | Butchery | Guarda `actual_weight_kg` en una línea (puede ser 0). Sin cálculo de costo todavía. | BR-09 |
| `CloseButcheryOrder` | Butchery | Transacción (C11): exige todas las líneas con `actual_weight_kg` registrado; valida `Σ actual_weight_kg ≤ input_weight_kg`, si no rechaza (BR-10); calcula `actual_shrinkage_kg = input − Σ salidas`; toma `reference_price_per_kg` vigente por línea (DEC-01); reparte el costo total del lote de entrada proporcional a `kg_i × p_i`, residuo al de mayor valor (BR-12, DEC-00); calcula `deviation_percent` por línea (BR-11); llama a `CreateLotFromButcheryOutput` por cada línea con salida > 0; pasa la orden a `closed`. | BR-10, BR-11, BR-12 |
| `CreateLotFromButcheryOutput` | Inventory | Análoga a `CreateLotFromPurchaseLineAction` de M2: crea el lote de salida (`source_type = 'butchery_order_line'`, costo total = `allocated_cost`, costo unitario = `allocated_cost / actual_weight_kg`) y el movimiento `butchery_out`. | BR-12, C10 |
| `VoidButcheryOrder` | Butchery | Solo si ningún lote de salida tiene movimientos aparte de su `butchery_out` (mismo patrón que `VoidPurchaseAction` de M2); revierte `butchery_in` y cada `butchery_out`; `voided`; auditoría con motivo (C15, BR-03: requiere `butchery.void`). | C10, C12, C15 |
| `AllocateButcheryCosts` (servicio puro) | Butchery | Reparte un monto entre cortes proporcional a `kg_i × p_i`; Σ asignado = monto exacto, residuo al de mayor valor. Análoga a `AllocateCosts` de M2 pero por valor, no por kg. | BR-12 |

### 4.4 Permisos (enum único `PermissionKey`, C14)

| Permiso | Administrador | Carnicero |
|---|---|---|
| `butchery.process` | ✔ | ✔ (ya sembrado desde M1) |
| `butchery.manage-templates` | ✔ | |
| `butchery.void` | ✔ | (BR-03: anular requiere permiso propio, de inicio solo administrador) |

Policies: `ButcheryTemplatePolicy`, `ButcheryOrderPolicy`. Sin `hasRole()` ni `abort(403)` (C14).

### 4.5 Pantallas

**Referencia:** `docs/design/screens/m-despiece.html` (única pantalla dibujada para M3; cubre BR-09, BR-10, BR-11, BR-12 según `docs/design.md` §5). Pendientes marcados ahí: «Leer balanza» es backlog → se implementa como «Registrar peso» (DEC-08); despiece sin conexión no se activa (fuera de alcance, §2).

**PWA (despiece)** — `resources/js/pages/butchery/`, necesita conexión (como la recepción de M2).
- `ButcheryOrdersIndex.vue`: lotes de materia prima con saldo disponible, como `ListRow`; al tocar uno se abre la orden (si no hay una abierta para ese lote) o se continúa la existente. `AppHeader` variante `floor`.
- `ButcheryOrderShow.vue`, siguiendo la maqueta 1:1:
  - `AppHeader` variante `task`: código de la orden, lote y materia prima, `StatusBadge` «En proceso» en `accent`.
  - Tres KPI (`entrada`, `costo del lote` leído de `batches.total_cost`, `carnicero`).
  - `ProgressBar`/`StackedBar`: kg registrados de N, con «X de Y cortes pesados» y la merma esperada.
  - Lista de cortes como `ListRow`: nombre, «Esperado X kg · Y %» (`expected_weight_kg`/`expected_yield_percent`), peso real a la derecha; si ya está pesado, `DeviationIndicator` en puntos (`success` dentro del umbral, `warning` + ícono por encima — umbral de DEC-05/DEC-06); si falta, `WeightDisplay` + `NumericKeypad` en vez de texto (sustituye el input simple de la maqueta por el patrón estándar de §3, más accesible).
  - Nota informativa fija: "Al cerrar, el costo de $ X se reparte por valor de mercado de cada corte. La merma no lleva costo."
  - `StickyActionBar`: «Registrar peso» (DEC-08) como acción principal mientras falten cortes; «Cerrar orden» se habilita solo cuando todos los cortes tienen peso registrado (deshabilitado si no, igual que en la maqueta).
- Rutas: `GET /butchery/orders`, `GET /butchery/orders/{order}`, `POST /butchery/orders` (abre, `OpenButcheryOrder`), `PATCH /butchery/orders/{order}/lines/{line}` (`RegisterButcheryOutputWeight`), `POST /butchery/orders/{order}/close` (`CloseButcheryOrder`). FormRequest + Policy en cada ruta (C3).

**Filament (oficina)** — sin pantalla de referencia dibujada para la gestión de plantillas; se construye con los patrones de `docs/design.md` §3/§4, igual que M2 hizo con `SupplierResource` sin maqueta.
- `ButcheryTemplateResource`: listado por materia prima con estado (activa/inactiva); formulario con repeater de líneas (corte, % estándar) + campo de merma esperada; validación de suma 100 % en el formulario antes de guardar; acción para activar una plantilla nueva (desactiva la anterior de esa materia prima).
- `ButcheryOrderResource` (solo lectura/consulta en Filament; la operación vive en la PWA, igual que M2 separó compra en Filament de recepción en PWA): listado de órdenes con filtro por estado, materia prima y carnicero; vista de detalle con líneas, desviaciones y costos asignados; acción «Anular» con motivo, visible con `butchery.void`.
- Hereda solo color primario, Figtree y logo (`docs/design.md` §2.4).

## 5. Tareas

- [ ] **1.1** Revisar el esquema real de M0/M1/M2 contra los supuestos de §4 (en particular: confirmar que no existe tabla `settings`, confirmar `PermissionKey` y el seeder de permisos, confirmar columnas de `batches`/`products`). — sin dependencias
- [ ] **2.1** Migración `settings` + fila sembrada del umbral (DEC-06). — depende de 1.1
- [ ] **2.2** Migraciones `butchery_templates`, `butchery_template_lines`, `butchery_orders`, `butchery_order_lines`. — depende de 1.1 y DEC-02, DEC-03, DEC-07
- [ ] **2.3** Modelos, casts decimales y factories con datos ficticios. — depende de 2.1, 2.2
- [ ] **2.4** Permisos `butchery.manage-templates` y `butchery.void`; Policies; test de arquitectura C14 en verde. — depende de 2.3
- [ ] **3.1** `AllocateButcheryCosts` con tests (reparto proporcional a kg·p, residuo, merma sin costo, caso dorado de DEC-00). — BR-12 · depende de DEC-00, DEC-01
- [ ] **3.2** `CreateButcheryTemplate` / `UpdateButcheryTemplate` con validación de suma 100 %. — BR-08 · depende de 2.4, DEC-02, DEC-03
- [ ] **3.3** `OpenButcheryOrder`: congela plantilla, valida saldo del lote, registra `butchery_in`. — BR-09 · depende de 3.2, DEC-04, DEC-07
- [ ] **3.4** `RegisterButcheryOutputWeight`. — BR-09 · depende de 3.3
- [ ] **3.5** `CreateLotFromButcheryOutput` en Inventory con movimiento `butchery_out`. — BR-12, C10 · depende de 2.3
- [ ] **3.6** `CloseButcheryOrder` en una transacción (valida BR-10, costea BR-12, calcula desviación BR-11). — BR-10, BR-11, BR-12 · depende de 3.1, 3.4, 3.5
- [ ] **3.7** `VoidButcheryOrder` con reversión y auditoría. — C10, C12, C15 · depende de 3.6
- [ ] **4.1** Filament: `ButcheryTemplateResource` y `ButcheryOrderResource` (solo lectura + anular). — depende de 3.2, 3.7
- [ ] **4.2** PWA: `ButcheryOrdersIndex` y `ButcheryOrderShow` siguiendo `m-despiece.html`, rutas y FormRequest. — depende de 3.6
- [ ] **5.1** Test de aceptación del caso dorado (BR-13) end-to-end: abrir orden de 20 kg, registrar las 6 salidas de la tabla de DEC-00, cerrar y verificar costos según la resolución de DEC-00. — depende de 3.6, DEC-00
- [ ] **5.2** Verificador independiente (C18 — roadmap marca M3 "feature + verificador"). Revisa en particular el costeo (3.1, 3.6) y la concurrencia de DEC-04. — depende de 5.1
- [ ] **5.3** Revisión 4R (roadmap "+4R") antes de cerrar el hito. — depende de 5.2
- [ ] **5.4** Evidencia: comandos de AGENTS §2, GGA, hashes y cierre. — depende de todo lo anterior

## 6. Evidencia

Pendiente — se completa durante la implementación, igual que en M0/M2.

## 7. Siguiente paso

Las 6 decisiones de §3 (DEC-00 a DEC-07) quedaron resueltas el 2026-11-17, todas con la opción recomendada. Falta solo la confirmación final del humano para crear la rama `feat/m3-butchery` y empezar la tarea 1.1 (AGENTS §4.3 puerta 1).
