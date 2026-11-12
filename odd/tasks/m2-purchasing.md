# M2 — Compras y recepción con pesaje

> Documento ODD del hito M2. Rama: `feat/m2-purchasing`. Copia en Engram: `odd/m2-purchasing/tasks`.
> Estado: **aprobado** (puerta 1, AGENTS §4.3) — 2026-11-12. Las 6 decisiones de §3 están resueltas.

## 1. Fuente

- **Roadmap, M2:** «Compras y recepción con pesaje» · 8 h · ODD: feature · Hecho cuando: «BR-06 y BR-07 en verde; se crea un lote desde una compra».
- **Pedido del humano (2026-11-12):** «Vamos con el feat02» → confirmado como M2.
- **Propuesta comercial, módulo Compras:** «proveedores, compras por canal, media canal o kilo, pesaje al recibir y flete incluido en el costo».
- **Constitution C1:** la recepción es una superficie de la PWA; la oficina es Filament.

## 2. Alcance

| BR | Qué se cubre en M2 |
|---|---|
| **BR-06** | Toda recepción se pesa; se guarda el peso facturado, el recibido y la diferencia por línea. |
| **BR-07** | Costo del lote = compra + flete + otros costos asignados. El lote nace al confirmar la recepción, con su movimiento de entrada en el kardex. |
| BR-01, BR-02, BR-03 | Se aplican como reglas transversales ya implementadas en M1: precisión de peso y monto, tasa del día guardada en la compra, permisos del carnicero y del administrador. No se reimplementan. |

**Fuera de alcance:** cuentas por pagar a proveedores (backlog), devoluciones a proveedor, notas de crédito del proveedor, recepciones parciales, recepción sin conexión (BR-26 solo cubre ventas), lectura de balanza por puerto serie (backlog) y el reporte de compras por proveedor (M6).

## 3. Decisiones

Ninguna de estas reglas aparece en business-rules. Bloquean solo las tareas indicadas en §5.

```text
[DEC-PENDIENTE] M2/BR-06, BR-07 — DEC-01 Flujo de compra y recepción
Contexto: C1 pone la recepción en la PWA y la oficina en Filament; BR-07 dice que el lote nace en la recepción.
Duda: ¿la compra se registra antes de que llegue la mercancía o en el mismo momento?
Opciones:
  A) Dos pasos. La oficina registra la compra en Filament (proveedor, factura del proveedor, líneas con kg o piezas facturados y monto, flete y otros costos). El carnicero la recibe en la PWA y pesa cada línea. Al confirmar nacen los lotes. Una recepción por compra.
     → El carnicero no ve montos. Si llega mercancía sin compra cargada, se espera a la oficina.
  B) Un paso en la PWA: quien recibe carga proveedor, montos y pesos.
     → Más rápido. El carnicero ve y carga costos.
  C) Un paso en Filament: todo en la oficina, sin pantalla PWA.
     → Menos trabajo, pero la sala de despiece no tiene la recepción en la tablet.
Recomendación técnica: A. Respeta C1 y BR-03 (el carnicero recibe, el administrador maneja costos) y cabe en las 8 h.
```
**Resuelta: A, 2026-11-12.** Dos pasos: Filament crea la compra en borrador (proveedor, factura, líneas, flete), la PWA pesa y confirma la recepción. El carnicero nunca ve montos.

**Sub-decisión (BR-07/C12):** el monto facturado (`line_amount`) de una línea **se puede editar mientras la compra esté en `draft`** (por ejemplo si el proveedor corrige su factura). Una vez `received`, deja de editarse — cualquier corrección posterior va por un documento inverso (C12), no por edición directa.

```text
[DEC-PENDIENTE] M2/BR-07 — DEC-02 Reparto del flete y de otros costos entre líneas
Contexto: una compra puede traer res, cochino y pollo con un solo flete.
Duda: ¿cómo se reparte el flete (y los otros costos) entre los lotes?
Opciones:
  A) Proporcional a los kg recibidos.
  B) Proporcional al monto de cada línea.
  C) Manual por línea.
Recomendación técnica: A. El flete depende del peso. En cualquier opción el residuo de redondeo va a la línea de mayor monto, para que la suma de los costos asignados sea igual al total exacto (mismo criterio que BR-12).
```
**Resuelta: A, 2026-11-12.** Flete y otros costos se prorratean proporcional a los kg recibidos; el residuo de redondeo va a la línea de mayor monto.

```text
[DEC-PENDIENTE] M2/BR-06, BR-07 — DEC-03 Costo cuando llega menos (o más) peso del facturado
Contexto: el proveedor factura 100 kg y se reciben 98 kg.
Duda: ¿«compra» en BR-07 es el monto facturado o el monto ajustado al peso recibido?
Opciones:
  A) Monto facturado. El lote vale lo que se paga y su costo por kg sube. La diferencia queda registrada (BR-06) para reclamar.
  B) Monto ajustado: precio por kg facturado × kg recibidos.
     → El costo del lote ya no cuadra con lo que se paga al proveedor.
Recomendación técnica: A.
```
**Resuelta: A, 2026-11-12.** El costo del lote sale del monto facturado (ajustado por costos asignados) ÷ kg recibidos; no se ajusta automáticamente al peso. Si el proveedor corrige la factura, se edita `line_amount` a mano mientras la compra esté en `draft` (ver sub-decisión de DEC-01).

```text
[DEC-PENDIENTE] M2/BR-14 — DEC-04 Fecha de vencimiento del lote
Contexto: el lote nace en M2, pero FEFO (BR-14, M4) necesita saber cuándo vence.
Duda: ¿de dónde sale la fecha de vencimiento?
Opciones:
  A) Campo opcional en cada línea de la recepción. M4 decide qué hacer con los lotes sin fecha.
  B) Días de vida útil por producto en el catálogo; el lote calcula la fecha.
     → Cambia el catálogo de M1.
  C) Nada en M2; se resuelve en M4 con una migración nueva.
Recomendación técnica: A. Es una columna nullable y no adelanta ninguna regla.
```
**Resuelta: A, 2026-11-12.** `expires_on` nullable en `purchase_lines`/lote; M4 decide qué hacer con los lotes sin fecha.

```text
[DEC-PENDIENTE] M2/BR-04 — DEC-05 Qué tipos de producto se compran
Contexto: BR-04 define materia prima, derivado y reventa.
Duda: ¿se puede comprar un derivado (por ejemplo, cortes ya despresados a otro distribuidor)?
Opciones:
  A) Solo materia prima y reventa. Los derivados salen únicamente del despiece.
  B) Los tres tipos.
     → Un derivado comprado tendría costo de compra y no de despiece; los reportes de margen (BR-28) lo tendrían que distinguir.
Recomendación técnica: A, salvo que el negocio ya compre cortes.
```
**Resuelta: A, 2026-11-12.** Solo se compran productos de tipo materia prima o reventa; los derivados salen únicamente del despiece propio (M3).

```text
[DEC-PENDIENTE] M2/BR-07, BR-09 — DEC-06 Granularidad del lote
Contexto: se compran 2 cochinos o media res más un cuarto.
Duda: ¿un lote por línea de compra (con n.º de piezas) o un lote por canal pesada por separado?
Opciones:
  A) Un lote por línea, con su peso total y el número de piezas. La orden de despiece (BR-09) toma kg de ese lote.
  B) Un lote por pieza o canal, cada una pesada aparte.
     → Permite rendimiento por canal, pero obliga a pesar cada pieza y multiplica los lotes.
Recomendación técnica: A. BR-09 y BR-28 piden rendimiento por orden, materia prima y carnicero, no por canal.
```
**Resuelta: A, 2026-11-12.** Un lote por línea de compra, con el número de piezas (`received_pieces`) guardado adentro; no se pesa cada pieza por separado.

## 4. Diseño técnico

> Supuestos a verificar contra M0/M1 antes de la tarea 2.1 (tarea 1.1): existen `products` (con tipo y unidad), `exchange_rates`, `inventory_movements` con su trigger, la tabla de lotes (si M0 la creó, se reutiliza y se agregan solo las columnas que falten con una migración nueva), el enum o seeder de permisos y la moneda base en Settings. Si algo no está, se anota aquí antes de seguir.

El diseño de abajo asume las recomendaciones de §3. Si una decisión cambia, se ajusta esta sección y se vuelve a aprobar.

### 4.1 Tablas (módulo `Purchasing`, salvo los lotes, que son de `Inventory`)

**`suppliers`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| name | string | Razón social |
| tax_id | string, unique, nullable | RIF |
| phone, notes | string/text, nullable | |
| is_active | boolean | No se borra (C12) |

**`purchases`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| supplier_id | FK | |
| supplier_invoice_number | string | Factura del proveedor; única por proveedor |
| supplier_invoice_date | date | |
| currency | char(3) | C13 |
| exchange_rate | igual que en M1 | Tasa BCV del día de la compra (BR-02) |
| freight_amount | numeric(18,4) | Default 0 |
| other_costs_amount | numeric(18,4) | Default 0 |
| other_costs_note | string, nullable | |
| status | string | `draft` → `received` → `voided` |
| created_by, received_by, voided_by | FK users | |
| received_at, voided_at | timestamptz | UTC (C13) |
| void_reason | string, nullable | |

**`purchase_lines`**
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| purchase_id, product_id | FK | Producto de tipo materia prima o reventa (DEC-05) |
| invoiced_weight_kg | numeric(12,3) | BR-01, BR-06 |
| invoiced_pieces | integer, nullable | Canales y productos por pieza |
| line_amount | numeric(18,4) | Monto facturado de la línea (DEC-03) |
| received_weight_kg | numeric(12,3), nullable | Se llena en la recepción |
| received_pieces | integer, nullable | |
| weight_difference_kg | numeric(12,3), nullable | recibido − facturado, guardado (BR-06) |
| allocated_freight, allocated_other_costs | numeric(18,4), nullable | DEC-02 |
| expires_on | date, nullable | DEC-04 |
| lot_id | FK, nullable | Lote creado al confirmar |

**Lotes (módulo `Inventory`)**: si M0 no los tiene completos, se agregan `source_type`/`source_id` (la línea de compra), `initial_weight_kg`, `pieces`, `total_cost` y `unit_cost` (numeric(18,4), por kg), `currency`, `exchange_rate` y `expires_on`. `Purchasing` no escribe esta tabla: llama a las Actions de `Inventory` (C4).

Todo peso y monto en `BcMath\Number`, nunca float (C9).

### 4.2 Actions

| Action | Módulo | Qué hace | BR |
|---|---|---|---|
| `CreatePurchase` / `UpdateDraftPurchase` | Purchasing | Crea o edita la compra en borrador; toma la tasa del día de Settings. Solo `draft` se edita (C12). | BR-02 |
| `ReceivePurchase` | Purchasing | En una transacción (C11): valida que todas las líneas tengan peso recibido; calcula la diferencia por línea; reparte flete y otros costos con residuo a la línea de mayor monto; llama a `CreateLotFromPurchaseLine` por línea; pasa la compra a `received`. | BR-06, BR-07 |
| `CreateLotFromPurchaseLine` | Inventory | Crea el lote con costo total = monto de la línea + flete asignado + otros asignados, costo por kg = total ÷ kg recibidos, y registra el movimiento de entrada `purchase_receipt` en el kardex. | BR-07, C10 |
| `VoidPurchase` | Purchasing | Solo si ningún lote tiene movimientos aparte de su entrada; registra el movimiento inverso por lote, pasa a `voided` y deja auditoría con motivo. Si algún lote ya se movió, se rechaza con un mensaje (la corrección va por M4). | BR-03, C10, C12, C15 |
| `AllocateCosts` (servicio puro) | Purchasing | Reparte un monto entre líneas por kg; Σ asignado = monto exacto. | BR-07 |

### 4.3 Permisos (enum o seeder único, C14)

| Permiso | Administrador | Carnicero |
|---|---|---|
| `purchasing.view` | ✔ | ✔ (sin montos en la PWA) |
| `purchasing.create` | ✔ | |
| `purchasing.receive` | ✔ | ✔ (BR-03: recepción) |
| `purchasing.void` | ✔ | (BR-03: anular requiere permiso propio) |
| `purchasing.manage_suppliers` | ✔ | |

Policies: `SupplierPolicy`, `PurchasePolicy`. Sin `hasRole()` ni `abort(403)`.

### 4.4 Pantallas

No hay pantalla de referencia para M2 en `docs/design/screens/` (design.md §5). Se construyen con los patrones de §4 y los componentes de §3; cualquier elemento que no esté en esta sección es `[DEC-PENDIENTE]`.

**Filament (oficina)**
- `SupplierResource`: listado, crear, editar, desactivar.
- `PurchaseResource`: listado con filtro por estado y proveedor; formulario de borrador con repeater de líneas (producto, kg y piezas facturados, monto, vence); flete y otros costos; vista de la compra recibida con peso facturado, recibido y diferencia por línea (en `warning` si ≠ 0) y el costo de cada lote; acción «Anular» con motivo, visible con `purchasing.void`.
- Hereda solo color primario, Figtree y logo (design.md §2.4).

**PWA (recepción)** — `resources/js/pages/purchasing/`
- `ReceiptsIndex.vue`: compras en `draft` como `ListRow` (proveedor, n.º de factura, nº de líneas). `AppHeader` variante `floor`.
- `ReceiptShow.vue`: `AppHeader` `task`; cada línea como `ListRow` con kg facturados y, una vez pesada, kg recibidos y diferencia con `StatusBadge` en `warning` si ≠ 0. Al tocar una línea se abre `BottomSheet` con `WeightDisplay` + `NumericKeypad`, campo de piezas si aplica y fecha de vencimiento (DEC-04); acción «Registrar peso». `StickyActionBar` con «Confirmar recepción» en `accent`, deshabilitado hasta pesar todas las líneas.
- Necesita conexión: sin internet muestra «Necesita conexión» (design.md §4).
- Rutas: `GET /purchasing/receipts`, `GET /purchasing/receipts/{purchase}`, `POST /purchasing/receipts/{purchase}/confirm` (FormRequest + Policy + `ReceivePurchase`).

## 5. Tareas

- [ ] **1.1** Revisar el esquema real de M0/M1 contra los supuestos de §4 y anotar diferencias aquí. — sin dependencias
- [ ] **2.1** Migraciones `suppliers`, `purchases`, `purchase_lines` y columnas faltantes del lote. — depende de 1.1 y de DEC-04, DEC-06
- [ ] **2.2** Modelos, casts decimales y factories con datos ficticios. — depende de 2.1
- [ ] **2.3** Permisos y Policies; test de arquitectura C14 en verde. — BR-03 · depende de 2.2
- [ ] **3.1** `AllocateCosts` con tests (reparto exacto, residuo, flete 0). — BR-07 · depende de DEC-02
- [ ] **3.2** `CreatePurchase` / `UpdateDraftPurchase`; tasa del día guardada; validación de tipo de producto. — BR-02, BR-07 · depende de 2.3, DEC-05
- [ ] **3.3** `CreateLotFromPurchaseLine` en Inventory con movimiento en el kardex. — BR-07, C10 · depende de 2.2, DEC-03
- [ ] **3.4** `ReceivePurchase` en una transacción. — BR-06, BR-07 · depende de 3.1, 3.3
- [ ] **3.5** `VoidPurchase` con movimientos inversos y auditoría. — BR-03, C12, C15 · depende de 3.4
- [ ] **4.1** Filament: `SupplierResource` y `PurchaseResource`. — depende de 3.2, 3.5 y DEC-01
- [ ] **4.2** PWA: `ReceiptsIndex` y `ReceiptShow`, rutas y FormRequest. — depende de 3.4 y DEC-01
- [ ] **5.1** Evidencia: comandos de AGENTS §2, GGA, hashes y cierre. — depende de todo lo anterior

## 6. Evidencia

### Tests por BR (nombre con el ID, C16)
| Test | Verifica |
|---|---|
| `BR-06 registra la diferencia entre peso recibido y facturado` | 100,000 kg facturados, 98,250 recibidos → diferencia −1,750 guardada en la línea |
| `BR-06 no confirma una recepción con líneas sin pesar` | `ReceivePurchase` lanza error y no crea lotes |
| `BR-07 el costo del lote suma compra, flete y otros costos` | Monto + flete asignado + otros asignados = `total_cost`; costo por kg = total ÷ kg recibidos |
| `BR-07 el reparto del flete cuadra al centavo` | Σ asignado = flete exacto con residuo en la línea de mayor monto |
| `BR-07 el lote nace en la recepción` | Antes de confirmar no hay lote; al confirmar hay un lote por línea y un movimiento `purchase_receipt` por lote |
| `BR-07 una recepción fallida no deja nada a medias` | Si falla un lote, no queda ni la compra recibida ni movimientos (C11) |
| `BR-03 solo con purchasing.void se anula una compra` | Carnicero → denegado; administrador → movimientos inversos y auditoría |
| `C12 no se anula una compra cuyo lote ya se movió` | Lote con salida → rechazo |

### Comandos
| Comando | Resultado |
|---|---|
| `sail artisan test` | — |
| `sail pint --test` | — |
| `sail composer types:check` | — |
| `sail pnpm build` | — |
| GGA | — |

### Commits
—

### Siguiente paso
M3 — Despiece y costeo (`odd/tasks/m3-butchery.md`).
