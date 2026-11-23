# M4 — Inventario: kardex por lote, mermas, conteo

> Documento ODD del hito M4. Rama: `feat/m4-inventory` (se crea al aprobar este documento).
> Copia en Engram: `odd/m4-inventory/tasks`.
> **Estado: APROBADO por el humano el 2026-11-23, con DEC-01 a DEC-11 resueltas.** Puerta 1 de AGENTS §4.3 cumplida. Pendiente antes de las pantallas: DEC-01 = B exige cambiar `docs/design.md` §2.4/§5 (nivel superior) en un commit aparte; el humano debe autorizar ese cambio cuando se llegue a esa tarea.

## 1. Fuente

- **Roadmap, M4:** «Inventario: kardex por lote, mermas, conteo» · 10 h · ODD: **feature** · Hecho cuando: «BR-14 a BR-17 en verde». Sin verificador ni 4R en el roadmap; aun así el kardex es riesgo alto según C18 (ver tarea 6.2).
- **Pedido del humano (2026-11-20):** «mientras tanto haz un borrador del m04 en base a las feat anteriores, business rules, constitution y sobre todo aplicando un diseño acorde a los prototipos de docs/design/screens/*.html».
- **Constitution:** C4 (módulo `Inventory`; otros módulos llaman sus Actions, no escriben sus tablas), C9 (sin float), C10 (kardex de solo inserción; cada movimiento guarda lote, cantidad, tipo, costo, usuario, referencia y motivo; error = movimiento inverso), C11 (transacciones), C12 (lo cerrado no se edita), C13 (UTC, se muestra en `America/Caracas`), C14 (Policies y permisos `module.action`), C15 (auditoría de ajustes de inventario), C18 (riesgo alto: kardex), C19 (no inventar el negocio).
- **Lo que dejó M3 para M4** (`odd/tasks/m3-butchery.md` §2 y `VoidButcheryOrderAction`): FEFO, mermas tipificadas, conteo físico y el «ajuste de inventario» al que remite el mensaje de anulación cuando un lote de salida ya tiene movimientos.
- **Lo que dejó M2 para M4** (M2 DEC-04): `expires_on` nullable en la línea de compra y `batches.expires_at`; «M4 decide qué hacer con los lotes sin fecha» → DEC-02.
- **Lo que dejó M0** (`odd/tasks/m0-foundation.md`, obs. A3): el trigger no cubre `TRUNCATE`; C10 solo exige rechazar `UPDATE`/`DELETE`, así que no se agrega aquí (anotado en §2, fuera de alcance).

## 2. Alcance

Texto literal de `docs/business/business-rules.md`:

| BR | Texto literal | Qué cubre M4 |
|---|---|---|
| **BR-14** | «Stock = Σ movimientos por lote. Las salidas consumen primero el lote que vence antes (FEFO) y toman su costo.» | Consulta única de saldo por lote y por producto; Action de salida FEFO que reparte una cantidad entre lotes y toma el costo de cada uno. La invoca M5 (ventas); M4 la entrega probada. |
| **BR-15** | «Mermas tipificadas, siempre con motivo y responsable: deshidratación, rebanado, vencimiento o decomiso, y ajuste por conteo.» | Action para registrar una merma por lote, con tipo, motivo y responsable obligatorios (DEC-04, DEC-05). |
| **BR-16** | «El conteo físico con pesaje genera un ajuste por la diferencia.» | Sesión de conteo por lote; al confirmarla genera el movimiento de ajuste por cada diferencia (DEC-07). |
| **BR-17** | «Venta sin stock suficiente: `[abierto]`. Por defecto se permite, con alerta de ajuste pendiente.» | `[abierto]` → configuración `inventory.allow_sale_without_stock`, valor por defecto «bloquear» (DEC-03 b; BR-17 actualizada). La Action FEFO implementa ambos comportamientos y deja la alerta (DEC-03 define cómo se registra el faltante). |

**Transversales ya implementadas, no se reimplementan:** BR-01 (peso 3 decimales; montos con 2 decimales exactos y valores por kg con 4, `Money` y `UnitAmount`), BR-03 (roles como conjuntos de permisos `module.action`; anular o devolver con permiso propio), BR-07 y BR-12 (el costo del lote ya viene calculado), C10 (tabla `inventory_movements` y su trigger), C15 (tabla `audit_logs`, `AuditLog`).

**Fuera de alcance de M4:**
- La venta en sí (BR-18 a BR-26, M5): M4 no toca `Sales`. La Action FEFO se prueba con un tipo de movimiento reservado, `sale_out`, que M5 empieza a escribir de verdad.
- Reporte «inventario valorizado» de M6: aquí solo se muestra el valor por lote y los KPI de la pantalla (DEC-08).
- Almacenes, cámaras o ubicaciones: ninguna BR los pide (M3 ya lo excluyó); la maqueta dibuja «Cámara 1» y no se implementa.
- Pantalla de PWA para contar o registrar mermas: DEC-06.
- Devoluciones de venta al inventario (`sales.return`, M5).
- Trigger `BEFORE TRUNCATE` (M0 A3).
- Cambiar el comportamiento de `VoidButcheryOrderAction` (DEC-09): solo se ajusta su mensaje si se aprueba la opción recomendada.
- Cantidades por pieza: todo el stock se lleva en kg (`inventory_movements.quantity` es `numeric(12,3)` y ambas Actions de lote guardan `unit = 'kg'`; `batches.pieces` es informativo desde M2). El conteo es «con pesaje» (BR-16), así que no hay conteo por pieza.

### 2.1 Hallazgos de consistencia entre las fuentes (para el humano)

1. `docs/design.md` §5 marca `d-inventario.html` con «Pendientes: Ninguno», pero la maqueta dibuja varias cosas sin BR: «Cámara 1», KPI «Vencen en 3 días», KPI «Valorizado al costo», KPI «Mermas del mes», umbral de «por vencer» y de fila crítica, código de lote `L-0412`, agrupación «Ventas · 4 tickets», menú con módulos de otros hitos y tarjeta de tasa BCV. Por design.md §0.3 no se implementan sin decisión; se listan en §4.5 y se resuelven en DEC-01, DEC-02, DEC-08, DEC-09 y DEC-10.
2. `docs/design.md` §2.4 y §5 dicen que Filament «usa sus componentes nativos» y que `d-inventario.html` es «referencia de contenido y jerarquía, no de píxeles»; el pedido de esta feature pide que la pantalla se parezca a la maqueta. Es una contradicción entre el pedido y un documento de nivel superior: DEC-01.
3. La maqueta muestra una merma de **deshidratación** con la leyenda «conteo de J. Pérez»; BR-15 trata «ajuste por conteo» como otro tipo de merma y BR-16 llama al conteo «ajuste». La leyenda mezcla dos tipos; se muestra el tipo y el responsable por separado (DEC-04, DEC-05).
4. La maqueta usa «Del despiece #112» y lote «L-0412»; el código real de lote hoy es `purchase_line-12` o `butchery_order_line-5` y el de orden de despiece es `D-0118`: DEC-10.
5. `docs/design.md` §3 lista `KpiCard`, `WeightDisplay`, `NumericKeypad`, `SegmentedControl` y `TaxBadge`, pero **no existen** en `resources/js/components/` (existen `AppHeader`, `AppButton`, `AppInput`, `ListRow`, `StatusBadge`, `ProgressBar`, `DeviationIndicator`, `StickyActionBar`, `MoneyAmount`, `AppIcon`, `HeaderStat`, `AppNotice`). M3 nombró `WeightDisplay` + `NumericKeypad` en su diseño. Afecta a M4 solo si DEC-06 elige PWA o DEC-01 elige C.
6. `app/Providers/Filament/AdminPanelProvider.php` escribe a mano el color `#B5121B` (el token `brand` es `#A51016`), el nombre del negocio y el logo. Eso contradice C6 y design.md §2.4 y no es de M4; se anota para que lo atienda M1 o DEC-01 si se elige B. `resources/css/app.css` tiene los tokens de color pero todavía usa «Instrument Sans»: Figtree y Baloo 2 locales siguen pendientes (design.md §2.2, `[DEC-PENDIENTE]` de dependencia, C1).
7. `batches.expires_at` es `timestamp` y la línea de compra guarda `expires_on` como `date`. Si se muestra con la conversión de C13 a `America/Caracas`, un vencimiento «11 nov» puede aparecer como «10 nov». Se verifica en la tarea 1.1 y se muestra como fecha sin conversión de zona.

## 3. Decisiones

Ninguna de estas reglas está definida en business-rules. Cada una bloquea solo las tareas indicadas en §5.

```text
[DEC-PENDIENTE] M4/BR-14, BR-15, BR-16 (pantalla de inventario) — DEC-01 Estilo de la pantalla de Filament
Contexto: design.md §2.4/§5: en Filament «solo hereda color primario, Figtree y logo; d-inventario.html es referencia de contenido y jerarquía, no de píxeles». El humano pidió que esta pantalla se parezca a los prototipos.
Duda: ¿hasta dónde llega el parecido visual de la oficina con la maqueta?
Opciones:
  A) Seguir design.md tal cual: tabla, filtros, acciones y widgets nativos de Filament con el mismo contenido y jerarquía (KPI arriba, filtros, tabla, kardex al lado). Costo: el menor; cabe en las 10 h. La pantalla no se verá como la maqueta (sin menú oscuro, sin tarjetas de 20 px, sin tipografía Baloo 2).
  B) Tema propio de Filament (Vite theme con los tokens de design.md §2: `brand-night` en el menú lateral, `canvas` de fondo, radios 20–24, Figtree y Baloo 2) más una página propia de Inventario con vistas Blade y widgets personalizados (KPI, píldoras de filtro, panel de kardex) construidos con componentes Filament/Livewire. El tema afecta a todo el panel (M2/M3 incluidos). Costo: mayor, estimado +4 a +6 h sobre las 10 h (estimación mía, sin medir); hay que verificar con Context7 la API de temas de Filament 5 y Tailwind 4. Exige cambiar antes design.md §2.4 y la fila de `d-inventario.html` en §5.
  C) Construir la pantalla de inventario en la capa Inertia/Vue (PWA) con los componentes de design.md §3 (la maqueta se puede copiar casi 1:1 con `ListRow`, `StatusBadge`, `HeaderStat`, etc.). Costo: similar a B, más crear los componentes que no existen (`KpiCard`, tabla). La oficina pierde la tabla, el filtrado y la paginación nativos de Filament, y la superficie de «oficina = Filament» (C1) queda partida. También exige cambiar design.md (§5 dice «Filament») y el flujo de permisos.
Consecuencia común a B y C: design.md está por encima de este documento en la jerarquía de estilos (AGENTS §3); solo el humano puede autorizar ese cambio, que va en su propio commit antes de la primera línea de código de pantalla.
Recomendación técnica: B, acotado: tema del panel con los tokens y las fuentes, y personalizar solo la página de Inventario (la maqueta existe y es la única de la oficina con contenido de M4). Mantiene Filament para tabla, filtros, paginación, modales y autorización, y la apariencia sale de los tokens ya declarados en `app.css`. Si el tiempo de B no cabe en las 10 h, el humano decide cómo se ajusta (C23). Mientras no haya respuesta, las tareas de lógica (secciones 2 y 3 de §5) avanzan; las de pantalla (sección 4) esperan.
```

Resuelta: B, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-14 — DEC-02 FEFO con lotes sin fecha de vencimiento y empates
Contexto: BR-14 dice «el lote que vence antes». M2 DEC-04 dejó la fecha como nullable y M4 decide qué se hace con los lotes sin fecha; los lotes de despiece (`CreateLotFromButcheryOutputAction`) nacen sin fecha.
Duda: ¿en qué orden entran los lotes sin fecha y los que vencen el mismo día?
Opciones:
  A) Primero los que tienen fecha (más próxima primero); luego los sin fecha, del más antiguo al más reciente (`received_at`); desempate final por id.
  B) Los sin fecha primero, para no dejarlos olvidados.
  C) Los sin fecha nunca se consumen por FEFO (hay que elegirlos a mano).
Recomendación técnica: A. Es la lectura literal de BR-14 (vence antes = tiene fecha y es menor) y deja el comportamiento determinista; con empate se cae a «primero en entrar, primero en salir», que es lo más cercano a FEFO sin fecha.
```

Resuelta: A, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-14 — DEC-03 Lotes ya vencidos en la salida FEFO, y faltante cuando no hay stock (BR-17)
Contexto: (a) FEFO consume «el lote que vence antes»; un lote ya vencido es el primero en la fila. (b) BR-17 [abierto] permite por defecto vender sin stock suficiente «con alerta de ajuste pendiente», sin decir dónde queda registrado el faltante en un kardex que solo suma movimientos por lote.
Duda: (a) ¿FEFO salta los lotes vencidos o los consume? (b) Cuando la cantidad pedida supera el saldo de todos los lotes, ¿dónde se registra el faltante?
Opciones (a):
  A) FEFO no distingue: consume el vencido primero y la pantalla lo resalta. La baja por vencimiento es una merma manual (BR-15).
  B) FEFO salta los vencidos; para venderlos hay que darlos de baja antes como merma por vencimiento. Más conservador, pero agrava los faltantes.
Opciones (b), con la configuración `inventory.allow_sale_without_stock` en «permitir»:
  A) La salida se completa contra el último lote de la fila FEFO y su saldo queda negativo; el costo es el de ese lote. La alerta de «ajuste pendiente» es el saldo negativo, y se resuelve con un conteo o una merma que lo lleve a cero.
  B) El faltante va a una tabla `stock_shortfalls` (producto, cantidad, referencia, resuelto o no), sin movimiento de kardex ni costo hasta que se ajuste. Mantiene el saldo siempre ≥ 0, pero la venta queda sin costo (afecta BR-28).
  C) No se permite (equivale a configurar `inventory.allow_sale_without_stock` en «bloquear»): la Action rechaza y no escribe nada.
Si el producto no tiene ningún lote, no hay costo que tomar: con (b)-A la Action rechaza, porque no puede inventar un costo.
Recomendación técnica: (a) A; (b) A. Es literal con BR-14 y BR-17, mantiene cada movimiento con lote y costo (C10) y no crea tablas fuera del kardex. El caso «producto sin lotes» queda rechazado y se anota para M5.
```

Resuelta: (a) B; (b) bloquear por defecto con interruptor, 2026-11-23 (cambia la recomendación original; BR-17 actualizada en su propio commit). (a) FEFO salta los lotes vencidos y un lote vencido no cuenta como stock vendible: para sacarlo se da de baja con una merma por vencimiento (BR-15). (b) `inventory.allow_sale_without_stock` = `false` por defecto: la Action rechaza y no escribe nada. Con el interruptor en `true` aplica la opción (b)-A: el saldo del último lote queda negativo como «ajuste pendiente»; la parte que vacía el lote toma el residuo (C9) y el excedente se costea `kg × unit_cost` de ese lote.

```text
[DEC-PENDIENTE] M4/BR-15 — DEC-04 Tipos de merma y movimiento que genera cada uno
Contexto: BR-15 enumera «deshidratación, rebanado, vencimiento o decomiso, y ajuste por conteo». Hoy `inventory_movements.type` es un string libre; los tipos escritos son `purchase_in`, `purchase_void`, `butchery_in`, `butchery_out` y `butchery_void`.
Duda: ¿«vencimiento o decomiso» es un tipo o dos? ¿«ajuste por conteo» es un tipo de merma o el nombre del movimiento que genera el conteo (BR-16), también cuando sobra producto?
Opciones:
  A) Cuatro tipos de movimiento: `shrinkage_dehydration`, `shrinkage_slicing`, `shrinkage_expiry` (vencimiento y decomiso juntos; el motivo dice cuál) y `count_adjustment` (positivo o negativo). El motivo de texto es obligatorio en todos.
  B) Cinco tipos (vencimiento y decomiso por separado).
  C) Un solo tipo `shrinkage` con una columna nueva «tipo de merma».
Recomendación técnica: A. BR-15 los une con «o» en una misma enunciación; si el negocio quiere separarlos es un tipo más sin romper nada. Se evita agregar columnas al kardex (C10 ya fija sus campos). Un sobrante por conteo es `count_adjustment` positivo; una merma tipificada solo resta.
```

Resuelta: B, 2026-11-23 (cambia la recomendación original). Cinco tipos de movimiento: `shrinkage_dehydration`, `shrinkage_slicing`, `shrinkage_expiry` (vencimiento), `shrinkage_confiscation` (decomiso) y `count_adjustment` (positivo o negativo). Vencimiento y decomiso van separados porque el decomiso es frecuente en Venezuela y el negocio quiere filtrarlos y reportarlos por aparte. Motivo de texto obligatorio en todos; el decomiso se registra con el motivo que lo originó.

```text
[DEC-PENDIENTE] M4/BR-15 — DEC-05 Responsable y permisos de la merma
Contexto: BR-15 exige «motivo y responsable». C10 define como campos del movimiento «usuario» y «motivo», no «responsable».
Duda: (1) ¿el responsable es quien registra, o una persona distinta que se elige? (2) ¿Qué rol puede registrar mermas y confirmar un conteo? BR-03 define solo administrador, cajero y carnicero («recepción y despiece»).
Opciones (1):
  A) Responsable = el usuario que registra (`user_id` del movimiento). Sin columnas nuevas.
  B) Responsable elegible (puede ser otro empleado), con columna nueva `responsible_user_id` en el kardex.
Opciones (2):
  A) Permisos nuevos `inventory.view`, `inventory.register-shrinkage`, `inventory.count`; los tres solo para administrador en el seeder. El humano los asigna al carnicero desde la gestión de roles si quiere.
  B) El carnicero recibe `inventory.view` y `inventory.register-shrinkage` desde el inicio; el conteo solo el administrador.
Recomendación técnica: (1) A; (2) A (lo más conservador y reversible, AGENTS §9). Si la persona que pesa no es quien teclea, (1) B se agrega luego con una migración nueva.
```

Resuelta: (1) B; (2) A, 2026-11-23 ((1) cambia la recomendación original). El responsable es una persona elegible, distinta de quien registra: por ejemplo, el administrador registra y el carnicero pesa. `user_id` del movimiento sigue siendo quien registra y se agrega `responsible_user_id` (quien pesó o responde por la merma). Se elige entre los usuarios activos, con el usuario conectado preseleccionado; es obligatorio (BR-15). El responsable aplica también al conteo (BR-16, decisión del humano 2026-11-23): cada línea pesada lleva su responsable. (2) Los permisos `inventory.*` son solo del administrador, que asigna al carnicero lo que quiera desde la gestión de roles.

```text
[DEC-PENDIENTE] M4/BR-15, BR-16 — DEC-06 ¿Dónde se registran las mermas y los conteos?
Contexto: la maqueta `d-inventario.html` dibuja los botones «Conteo físico» y «Registrar merma» en la pantalla de oficina (Filament). No hay maqueta de PWA para M4, y design.md §5 no asigna ninguna pantalla de PWA a este hito.
Duda: ¿el piso (tablet) necesita registrar mermas o pesar un conteo?
Opciones:
  A) Solo en Filament (la maqueta). Sin pantalla de PWA en M4.
  B) Además, pantallas de PWA (`resources/js/pages/inventory/`) para pesar el conteo y registrar mermas, sin maqueta: se construirían con los patrones de design.md §3/§4 (`AppHeader` `floor`, `ListRow`, `StickyActionBar`) y, para digitar el peso, `WeightDisplay` + `NumericKeypad`, que todavía no existen (hallazgo 5).
Recomendación técnica: A. Es lo único con pantalla de referencia, cabe en las 10 h y no inventa un flujo de piso sin BR. B queda como candidato a backlog si el negocio lo pide.
```

Resuelta: A, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-16 — DEC-07 Flujo del conteo físico
Contexto: BR-16 dice solo «el conteo físico con pesaje genera un ajuste por la diferencia».
Duda: ¿qué se cuenta (todo o lotes elegidos), cuándo se genera el ajuste y quién lo aprueba?
Opciones:
  A) Sesión de conteo (`inventory_counts`) con líneas por lote: el usuario elige los lotes (por defecto todos los de saldo > 0), pesa cada uno y la sesión pasa de `draft` a `confirmed`. Al confirmar, por cada lote con diferencia ≠ 0 se escribe un `count_adjustment` por (peso contado − saldo del sistema al momento de pesar) al costo del lote, más una fila de auditoría (C15). Una sesión confirmada no se edita (C12); un error se corrige con otra sesión. Un borrador se puede descartar (`cancelled`, sin movimientos).
  B) Conteo suelto por lote, sin sesión: se pesa y genera el ajuste de inmediato.
  C) Como A, pero con aprobación de un segundo usuario antes de generar el ajuste.
Detalle técnico (no requiere decisión): el saldo del sistema se toma al registrar el peso de cada línea, con bloqueo de fila del lote, para que ventas posteriores no deformen la diferencia. El conteo muestra el saldo del sistema junto al campo de peso (no es «a ciegas»; si se quiere a ciegas es otra decisión).
Recomendación técnica: A. Una sesión agrupa lo que se contó en un mismo momento, deja evidencia y cabe en una pantalla; C duplica el control sin BR que lo pida.
```

Resuelta: A, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-14, C10 — DEC-08 Definición de los KPI de la pantalla de inventario
Contexto: la maqueta muestra «Valorizado al costo», «Lotes activos», «Vencen en 3 días» y «Mermas del mes». design.md §5 dice «Pendientes: Ninguno», pero ninguna BR define estas cifras. El «inventario valorizado» como reporte es de M6. Los lotes guardan su propia moneda (`batches.currency`, por defecto `VES`) y la «moneda contable» sigue abierta con el contador.
Duda: ¿se implementan los cuatro KPI y con qué cálculo?
Opciones:
  A) Los cuatro, con estas definiciones: Lotes activos = lotes con saldo > 0. Valorizado = Σ del valor de los lotes activos (regla de costo y valor del lote, §3: lo que entró menos lo que salió), agrupado por moneda del lote (normalmente una sola; sin convertir). Vencen en N días = lotes activos con fecha dentro de N días (N de DEC-09). Mermas del mes = Σ kg de los movimientos de merma (los cuatro tipos de merma) y de los `count_adjustment` negativos del mes calendario en `America/Caracas`.
  B) Solo «Lotes activos» y «Vencen en N días» (derivan directo del kardex y la fecha); «Valorizado» y «Mermas del mes» pasan a M6.
  C) Ninguno; la pantalla arranca en la tabla.
Recomendación técnica: A. Son sumas del kardex sin regla nueva, la maqueta los dibuja y M6 reutilizará la misma consulta. Si el humano prefiere cerrar la moneda contable antes, se pasa a B.
```

Resuelta: A, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-14 — DEC-09 Umbral de «por vencer», fila crítica y anulación de despiece con lotes movidos
Contexto: la maqueta pinta «Vencen en 3 días» y «Por vencer», y una fila en `brand-soft` para «2 días». Ninguna BR define el umbral. Además, `VoidButcheryOrderAction` rechaza anular cuando un lote de salida tiene movimientos y remite a «un ajuste de inventario (M4)».
Duda: (1) ¿qué días definen «por vencer» y cuándo una fila es crítica? (2) ¿M4 cambia la anulación del despiece?
Opciones (1):
  A) Un solo umbral global en `settings` (`inventory.expiry_alert_days`, 3 por defecto, como el umbral de M3). Por vencer = vence en ≤ N días: `StatusBadge` `warning`. Ya vencido = fila `brand-soft` con `StatusBadge` «Vencido». No se implementa un segundo umbral («2 días» de la maqueta).
  B) Dos umbrales configurables (por vencer y crítico).
Opciones (2):
  A) No se cambia la anulación; M4 entrega el ajuste (merma/conteo) y solo corrige el mensaje para que diga «registre una merma o un conteo». La corrección de un lote de despiece ya movido va por ahí.
  B) Anular un despiece con lotes movidos revierte lo que quede (saldo ≠ lo producido) con un ajuste. Cambia la regla de M3 y toca el costo de lo ya vendido: no hay BR que lo defina.
Recomendación técnica: (1) A; (2) A.
```

Resuelta: (1) A; (2) A, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-14 — DEC-10 Código visible del lote
Contexto: la maqueta muestra lotes `L-0412` y despieces `#112`. Hoy `batches.code` vale `purchase_line-12` o `butchery_order_line-5` (único, string de 30). Es el código que se imprimiría en una etiqueta o diría el carnicero.
Duda: ¿se cambia el código de lote?
Opciones:
  A) Se deja como está y se muestra tal cual. La pantalla no se parecerá a la maqueta en esa columna.
  B) Código legible `L-0001…` generado al crear cada lote (por ambas Actions de lote) y una migración nueva que re-codifica los lotes existentes; el kardex referencia `batch_id`, no el código. Es una migración con datos (C18).
Recomendación técnica: B, como tarea aparte y con prueba de que ningún movimiento ni orden pierde su lote; es lo que ve el usuario en la pantalla que más se mira. Si no se aprueba, A.
```

Resuelta: B, 2026-11-23 (el humano aceptó la recomendación técnica).

```text
[DEC-PENDIENTE] M4/BR-16, C9 — DEC-11 Costo de un sobrante por conteo (ajuste positivo)
Contexto: la regla de costo y valor (§3) define las salidas. Un conteo físico con más peso que el sistema genera una entrada sin compra que la respalde; hay que decidir con qué costo entra para que el valor del lote siga cuadrando (C9).
Duda: ¿con qué costo entra el sobrante?
Opciones:
  A) `kg × unit_cost` del lote, redondeado al centavo; suma al valor del lote. El `total_cost` del lote deja de ser lo único que entró: el valor pasa a ser `total_cost` + Σ costo de entradas por ajuste − Σ costo de salidas.
  B) Sin costo (0,00): el sobrante diluye el costo por kg del lote y no inventa dinero.
  C) No se permite sobrante: la diferencia positiva se rechaza y se corrige con una entrada documentada (compra o despiece).
Recomendación técnica: A, es lo más cercano a BR-16 («ajuste por la diferencia») y mantiene el costo por kg estable; la fórmula de valor lo incluye. Es decisión de negocio: no se implementa hasta que el humano elija.
```

Resuelta: B, 2026-11-23. El sobrante entra al kardex como `count_adjustment` positivo con costo 0,00: no suma valor al lote (el valor sigue siendo `total_cost` − Σ salidas) y el costo por kg efectivo baja. Test: lote de 200 kg con `total_cost` 190,01, conteo de 201 kg → valor sigue en 190,01 y, al vender los 201 kg, Σ costo de salidas = 190,01.

```text
[DEC-PENDIENTE] M4/BR-14 — DEC-12 Fecha de vencimiento: la pantalla de recepción no la captura
Contexto: M2 DEC-04 (A) pidió un campo opcional de vencimiento en cada línea de la recepción. Las Actions lo soportan, pero `ReceiptController` y la pantalla de recepción no lo envían (tarea 1.1). Hoy todo lote de compra nace sin fecha, así que FEFO (DEC-02 A) cae siempre en «primero en entrar», y «saltar los vencidos» (DEC-03 a B) y el KPI «Vencen en N días» nunca se activan.
Duda: ¿M4 agrega el campo de vencimiento a la recepción?
Opciones:
  A) Sí: campo opcional «Vence» por línea en `ReceiptShow`, `ConfirmReceiptRequest` y `ReceiptController`, con test. Cambio chico que completa lo decidido en M2.
  B) No: M4 funciona sin fechas y el campo queda para después. FEFO, vencidos y KPI quedan inertes hasta entonces.
  C) Obligatorio para productos perecederos: exige una BR que lo defina (no existe).
Recomendación técnica: A, es opcional y no inventa una regla; sin él, DEC-02, DEC-03 (a) y el KPI de vencimiento no hacen nada. No bloquea las tareas 2.x ni 3.1; sí condiciona el test de aceptación de vencidos (5.1) y la tarea 4.1.
```

**Decisiones técnicas resueltas sin bloquear (C19, puramente técnicas):**

- **Saldo único.** Stock = Σ `inventory_movements.quantity` por lote (BR-14); no se guarda saldo denormalizado. Hoy hay dos cálculos duplicados (`OpenButcheryOrderAction::availableBalance` y `ListAvailableRawMaterialBatchesAction`); se unifican en un servicio `StockBalance` de `Inventory` y esas dos clases lo usan (con sus tests de M3 como PRESERVE). Para listas, `Batch::withSum('movements', 'quantity')` y un índice `inventory_movements(batch_id)` (PostgreSQL no lo crea solo para la FK).
- **Concurrencia.** Toda Action que escribe movimientos usa transacción (C11) y `lockForUpdate` sobre las filas de lote implicadas, en orden de id para evitar interbloqueos; el saldo se vuelve a leer dentro del bloqueo (mismo principio que M3 DEC-04).
- **Regla de costo y valor del lote (C9, sin fuga de dinero; decisión del humano, 2026-11-23).** Valor de un lote = `total_cost` − Σ costo de sus salidas en el kardex. Cada salida (venta, merma, ajuste negativo) cuesta `kg × unit_cost` redondeado al centavo (half-up, BR-01), **salvo la que deja el lote en 0,000 kg: esa toma exactamente el valor restante**, de modo que Σ costo de las salidas = `total_cost` al centavo, sin crear ni perder centavos. El valor nunca se calcula como `saldo × unit_cost` (puede diferir unos centavos de `total_cost`, también en lotes viejos de M1 cuyo `unit_cost` no se recalculó). `total_cost` se guarda con 2 decimales y `unit_cost` con 4 (C9). Ejemplo: lote de 200 kg con `total_cost` 190,01 y `unit_cost` 0,9501 vendido entero cuesta 190,01 (y no 200 × 0,9501 = 190,02). El precio de venta no depende de esto: solo cambia el margen y el valor del inventario. Un sobrante por conteo entra con costo 0,00 y no suma valor al lote (DEC-11 B).
- **Monto de cada movimiento (C9, decisión técnica, hallazgo de la tarea 3.2).** El kardex hoy solo guarda `unit_cost` (4 decimales): el residuo de la salida que vacía un lote no se puede reconstruir con `kg × unit_cost`. Para que la regla de costo y valor sea calculable, cada movimiento nuevo guarda su monto exacto en una columna nueva nullable `inventory_movements.total_cost` `numeric(18,2)`, con el mismo signo que `quantity` (entrada +, salida −). Migración nueva que solo agrega la columna (sin `UPDATE`; los movimientos existentes quedan en `NULL`). Respaldo para filas con `NULL`: `round(quantity × unit_cost)` al centavo. **Valor de un lote = `batches.total_cost` + Σ `total_cost` con signo de sus movimientos que no son la entrada inicial** (`purchase_in` o `butchery_out`). La Action que escribe el movimiento calcula el monto: `kg × unit_cost` redondeado al centavo, salvo la salida que deja el lote en 0,000 kg, que toma exactamente el valor restante; el sobrante por conteo guarda `0.00` (DEC-11 B).
- **Merma.** Siempre resta, nunca deja el saldo del lote por debajo de cero (rechaza) y toma su costo según la regla anterior. Motivo obligatorio y no vacío validado en la Action (la columna `reason` hoy es nullable; C10 pide el motivo). Cada merma y cada ajuste por conteo deja una fila de `audit_logs` con saldo antes y después (C15: «ajustes de inventario»).
- **Enum de tipos.** Se crea `App\Domain\Inventory\MovementType` (backed enum de strings, patrón de `ProductType`) con los tipos actuales, los de DEC-04 y `sale_out` (reservado, lo escribe M5), con su etiqueta en español, ícono y tono para el kardex. Las Actions existentes pasan a usarlo sin cambiar los valores guardados (sin migración).
- **Ajustes inversos.** Un error en un movimiento se corrige con un movimiento inverso (C10); M4 no agrega ninguna acción de «editar movimiento».
- **Configuración sin pantalla** (como el umbral de M3): `inventory.allow_sale_without_stock` = `false` y `inventory.expiry_alert_days` = `3` en `SettingSeeder`.
- **Sin float** (C9): `BcMath\Number` en todo cálculo de saldo, reparto FEFO y valor.

## 4. Diseño técnico

> Supuestos a verificar en la tarea 1.1 antes de escribir código: `inventory_movements` y su trigger (M0); `batches` con `product_id` (M3), `expires_at`, `unit_cost`, `total_cost`, `currency`; `purchase_lines.expires_on` y `ReceivePurchaseAction` que lo pasa al lote; `PermissionKey` y `RolePermissionSeeder`; `settings` y `SettingSeeder` (M3 DEC-06); `AuditLog`; que `AdminPanelProvider` descubre `app/Filament/Resources` y `app/Filament/Pages`. Si algo no coincide, se anota aquí antes de seguir.

### 4.0 Resultado de la tarea 1.1 (verificado el 2026-11-23 contra el código de `main`)

Confirmados: `inventory_movements` y su trigger de fila (solo `UPDATE`/`DELETE`; un `ADD COLUMN` nullable es seguro, pero **no se puede rellenar con `UPDATE`**), `batches` (`code` string(30) único, `unit_cost` (18,4), `total_cost` (18,2), `expires_at` timestamp nullable, `currency` char(3), `product_id` nullable), `unit_cost` derivado del `total_cost` ya redondeado a 2 decimales (`UnitAmount::fromTotal`, división a 12 decimales y un solo redondeo half-up a 4), `PermissionKey` (un caso nuevo exige entrada en `label()` y `module()`), `settings` (`Setting::valueFor`; `CompanySettings::KEYS` solo si se lee por esa clase), descubrimiento de recursos y páginas de Filament, las dos sumas de saldo duplicadas y los cuatro tipos de movimiento actuales (`purchase_in`, `butchery_in`, `butchery_out`, `butchery_void`; ningún enum los referencia).

Diferencias respecto de lo supuesto:
1. **Sin índices** en `inventory_movements` (ni siquiera en las FK `batch_id` y `user_id`): la migración de índices de 2.1 es necesaria.
2. **`expires_on` no llega desde la pantalla:** `ReceivePurchaseAction` y `CreateLotFromPurchaseLineAction` lo soportan, pero `ReceiptController` solo envía `received_weight_kg` y `received_pieces`. Hoy todo lote de compra nace sin fecha → DEC-12.
3. **Hallazgo 7 (fecha de vencimiento):** la fecha se guarda como medianoche UTC (`config/app.php` en UTC), que en Caracas es el día anterior a las 20:00. Regla para M4: mostrar y comparar la fecha de `expires_at` **en UTC, sin convertir a `America/Caracas`**.
4. **No hay helper de auditoría:** cada Action escribe `AuditLog::create([...])` en línea (`auditable_type`, `auditable_id`, `action`, `user_id`, `reason`, `before`, `after`). Las Actions de M4 hacen igual.
5. **`batches.pieces`** (unsignedSmallInteger nullable) existe y no estaba listado; lo escribe `CreateLotFromPurchaseLineAction`.
6. **Mensaje de anulación del despiece** (`VoidButcheryOrderAction.php:35`): ningún test alcanza esa rama (los de `VoidButcheryOrderActionTest` solo exigen `RuntimeException`). El test de 3.6 debe cubrir la rama con un lote de salida con más de un movimiento y fijar el texto nuevo.

### 4.1 Tablas y migraciones (módulo `Inventory`)

Se agrega una columna nullable a `inventory_movements` (DEC-05 1 B); `batches` no se altera. El trigger de C10 solo rechaza `UPDATE`/`DELETE`, así que `ALTER TABLE ... ADD COLUMN` no lo toca y permanece. Migraciones nuevas, ninguna edita una existente:

1. **Índice** en `inventory_movements(batch_id)` y `(type)`, y columna `responsible_user_id` (FK `users`, nullable) en `inventory_movements` (DEC-05 1 B): quien pesó o responde por la merma; `user_id` sigue siendo quien registra. Los movimientos existentes quedan en `NULL`; no hay datos que reconstruir.
2. **`inventory_counts`** (DEC-07)

| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| code | string, unique | Ej. `C-0007` |
| status | string | `draft` → `confirmed` \| `cancelled` |
| notes | string, nullable | |
| created_by | FK users | Quien abre la sesión |
| confirmed_by | FK users, nullable | |
| confirmed_at | timestamptz, nullable | UTC (C13) |
| timestamps | | |

3. **`inventory_count_lines`**

| Columna | Tipo | Nota |
|---|---|---|
| id | bigint | |
| count_id | FK `inventory_counts` | |
| batch_id | FK `batches` | `unique(count_id, batch_id)` |
| system_quantity_kg | numeric(12,3), nullable | Saldo del sistema al pesar |
| counted_weight_kg | numeric(12,3), nullable | Peso contado; puede ser 0 |
| difference_kg | numeric(12,3), nullable | `counted − system`, calculado al pesar |
| adjustment_movement_id | FK `inventory_movements`, nullable | Se llena al confirmar si la diferencia ≠ 0 |
| counted_by, counted_at | FK users, timestamptz | `counted_by` es quien teclea el peso |
| responsible_user_id | FK users, nullable | Quien pesó (puede ser otra persona que `counted_by`); obligatorio al registrar el peso (DEC-05 1 B) |
| timestamps | | |

4. **Migración de datos (solo si DEC-10 = B):** código `L-####` para lotes existentes. Riesgo alto (C18).
5. **`stock_shortfalls`:** solo si DEC-03 (b) = B. Con la recomendación (A) no existe.

Semillas: `inventory.allow_sale_without_stock`, `inventory.expiry_alert_days` en `SettingSeeder` (no migración).

### 4.2 Tipos de movimiento (kardex)

Existentes (no cambian): `purchase_in`, `purchase_void`, `butchery_in`, `butchery_out`, `butchery_void`.
Nuevos de M4 (DEC-04): `shrinkage_dehydration`, `shrinkage_slicing`, `shrinkage_expiry`, `shrinkage_confiscation`, `count_adjustment`.
Reservado: `sale_out` (BR-14; lo escribe M5).
Signos: entradas positivas, salidas y mermas negativas; `count_adjustment` con el signo de la diferencia.

### 4.3 Actions (`app/Domain/Inventory/Actions`)

| Action | Qué hace | BR |
|---|---|---|
| `StockBalance` (servicio de consulta) | Saldo por lote, por producto, y listado de lotes activos con saldo. Única fuente de saldo del código. | BR-14 |
| `ConsumeStockFefoAction` | Recibe producto, kg, tipo de movimiento, referencia y usuario. En una transacción: bloquea los lotes del producto con saldo > 0 y no vencidos (DEC-03 a B: los vencidos se saltan) y los ordena por vencimiento, luego `received_at`, luego id (DEC-02); descuenta lote por lote costeando cada salida con la regla de costo y valor del lote (§3: la salida que lo deja en 0 toma el residuo); devuelve las asignaciones (lote, kg, costo) y el costo total. Si falta stock, aplica `inventory.allow_sale_without_stock` y DEC-03: bloquear → rechaza sin escribir nada; permitir → el faltante va según DEC-03. | BR-14, BR-17 |
| `RegisterShrinkageAction` | Merma tipificada sobre un lote: tipo, kg > 0, motivo obligatorio, responsable obligatorio (usuario activo, puede ser distinto de quien registra; se guarda en `responsible_user_id`); rechaza si supera el saldo; escribe el movimiento y la auditoría. Permiso `inventory.register-shrinkage`. | BR-15, C15 |
| `OpenInventoryCountAction` | Crea la sesión `draft` con los lotes elegidos. | BR-16 |
| `RegisterCountedWeightAction` | Guarda el peso contado de una línea, su responsable (obligatorio, usuario activo) y el saldo del sistema en ese momento (bloqueo del lote). Solo en `draft`. | BR-16 |
| `ConfirmInventoryCountAction` | Transacción: exige todas las líneas pesadas; por cada diferencia ≠ 0 escribe un `count_adjustment` con el `responsible_user_id` de la línea (`user_id` = quien confirma) y una fila de `audit_logs`; el negativo se costea con la regla de costo y valor del lote (§3) y el positivo entra a costo 0,00 (DEC-11 B); pasa a `confirmed`. Rechaza una sesión no `draft`. | BR-15, BR-16, C15 |
| `CancelInventoryCountAction` | `draft` → `cancelled`, sin movimientos. | C12 |

`CreateLotFromPurchaseLineAction` y `CreateLotFromButcheryOutputAction` no cambian salvo DEC-10 (código de lote) y el uso del enum de tipos.
`ListAvailableRawMaterialBatchesAction` y `OpenButcheryOrderAction::availableBalance` pasan a leer `StockBalance` (sin cambio de comportamiento). `VoidButcheryOrderAction`: solo el mensaje (DEC-09 A).

### 4.4 Permisos y Policies (C14)

Se agregan al enum `PermissionKey` (único lugar, sin strings literales) y al `RolePermissionSeeder`:

| Permiso | Administrador | Carnicero | Cajero |
|---|---|---|---|
| `inventory.view` | ✔ | — | — |
| `inventory.register-shrinkage` | ✔ | — | — |
| `inventory.count` | ✔ | — | — |

(asignación según DEC-05 recomendada; el humano puede cambiarla). Policies: `BatchPolicy` (ver inventario y kardex) e `InventoryCountPolicy` (abrir, pesar, confirmar, descartar). Sin `hasRole()` ni `abort(403)`; el test de arquitectura de C14 debe seguir en verde. La anulación de un movimiento no existe (se corrige con ajuste).

### 4.5 Pantallas

Antes de crear o cambiar cualquier pantalla se relee `docs/design.md`. **Tokens:** solo por nombre (`brand`, `brand-strong`, `brand-night`, `brand-soft`, `accent`, `canvas`, `surface`, `line`, `line-soft`, `ink`, `ink-muted`, `success(-soft)`, `warning(-soft)`, `info(-soft)`), nunca hex ni estilos en línea de la maqueta. Textos de pantalla en español, tomados de la maqueta. Fechas en `America/Caracas` salvo el vencimiento (hallazgo 7). Cifras con `tabular-nums`, kg con 3 decimales y montos con 2 (BR-01), formato `es-VE`.

**Superficie y construcción de las pantallas de Filament dependen de DEC-01.** La columna «Construcción» describe la opción B (recomendada); con A, la misma estructura se hace con componentes nativos y sin la apariencia de las columnas «Tokens».

#### 4.5.1 Inventario por lote y kardex (Filament, oficina)

- **Referencia:** `docs/design/screens/d-inventario.html`. Cubre BR-14, BR-15, BR-16 y C10 (design.md §5).
- **Ruta/clase:** `app/Filament/Pages/InventoryPage.php` (o `BatchResource` con página de lista personalizada), grupo de navegación «Inventario», ícono de caja. Visible con `inventory.view`.
- **Datos:** `Batch` con `withSum('movements','quantity')` y `product`; lote seleccionado con sus `InventoryMovement` ordenados por `occurred_at`.

| Sección de la maqueta | Construcción | Tokens (design.md §2) |
|---|---|---|
| Menú lateral (`aside`: logo, nombre, módulos, tarjeta «Tasa BCV de hoy») | Navegación de Filament. Fondo y estados activos por el tema (solo B). Logo y nombre del negocio desde Settings (C6), no escritos en código. | `brand-night` (fondo), `brand` (ítem activo), texto blanco; tasa en `accent` |
| Cabecera: rótulo gris + `h1` «Inventario» | `getHeading()` y subtítulo de la página. Rótulo: «Salidas por FEFO (vence primero, sale primero)». **Sin «Cámara 1»** (no hay almacenes). | Título: display 800/34, `brand-strong`; rótulo: 14 `ink-muted` |
| Botón «Conteo físico» (outline) | `Action` de cabecera: abre la lista/creación de sesiones de conteo (4.5.3). Requiere `inventory.count`. | Borde y texto `brand`, fondo `surface`, radio 14, alto 46 |
| Botón «Registrar merma» (primario) | `Action` de cabecera con modal (4.5.2). Requiere `inventory.register-shrinkage`. | Fondo `brand`, texto blanco, radio 14, alto 46 |
| 4 tarjetas KPI | Widget de estadísticas personalizado (vista Blade), 4 columnas que se acomodan. Cálculo según DEC-08. | Fondo `surface`, radio 20, rótulo 14/600 `ink-muted`, valor display 30. «Vencen en N días»: fondo `warning-soft`, rótulo `warning`, valor en `warning` oscuro (tono de texto sobre `warning-soft`; si no hay token se usa `warning`). Sin sombra |
| Chips de filtro: Todos · Materia prima · Derivado · Reventa · Por vencer | `Tab`s de la tabla con estilo de píldora. Tipo de producto (BR-04) y «Por vencer» (DEC-09). El tipo sale de `products.product_type`. | Activa: `brand` + blanco; inactiva: `canvas` + `ink`; «Por vencer»: `warning-soft` + `warning`; radio 999 |
| Tabla: Lote · Producto · Tipo · Stock kg · Costo /kg · Valor · Vence | `Table` de Filament con columnas de texto; Stock con 3 decimales, Costo y Valor con 2 y el símbolo de la moneda del lote; Valor = `total_cost` − Σ costo de las salidas del lote (regla de §3; calculado en servidor, C5). Orden y búsqueda nativos. Clic en la fila selecciona el lote del kardex. | Cabecera: 12/600 mayúsculas `ink-muted`; divisor `line-soft`; código del lote en `brand` solo si es fila crítica |
| Fila crítica (fondo rosado) y píldora «11 nov · 2 días» | Vencido: fila `brand-soft` y `StatusBadge` «Vencido». Por vencer (≤ N días): píldora «dd mmm · X días» en `warning`. Resto: fecha en `ink-muted`. Sin fecha: «Sin fecha». | `brand-soft`, `warning-soft`/`warning` |
| Lote con saldo negativo (BR-17) — **no dibujado** | Fila `warning-soft` y `StatusBadge` «Ajuste pendiente»; aviso sobre la tabla con el número de lotes. Se hace con los tokens existentes (design.md §4 «aviso en `warning`»). | `warning-soft`, `warning` |
| Pie «Mostrando 7 de 46 lotes» | Resumen de paginación de Filament. | 13 `ink-muted` |
| Panel «Kardex · L-0412» (rótulo, título, subtítulo, lista, saldo, nota) | Panel lateral (Livewire) del lote seleccionado, a la derecha en escritorio y debajo en pantallas angostas. Cabecera: rótulo 12/600 mayúsculas, nombre del producto display 22/700, subtítulo «Del despiece {código} · costo $ X /kg» (u «De la compra…» si el origen es una compra). | Fondo `surface`, radio 22; rótulo `ink-muted`; título `ink` |
| Filas del kardex: ícono de 32 px, título, fecha·referencia, cantidad | Una fila **por movimiento** (C10), con la etiqueta, ícono y tono del enum de tipos: entrada `success-soft`/`success` con signo «+»; salida neutra `canvas`/`ink`; merma `warning-soft`/`warning`. Equivale a `ListRow` (design.md §3, ícono de 40 px; la maqueta usa 32: manda el componente). Responsable y motivo de la merma en el subtítulo. | `success`, `warning`, `canvas`, `line-soft` |
| Bloque «Saldo» | Σ de los movimientos mostrado y comprobable contra la tabla. | `canvas`, radio 16, display 24 |
| Nota «Los movimientos no se editan ni se borran…» | Texto fijo. | 13 `ink-muted` |

**Dibujado sin BR en M4 — no se implementa:**
- «Cámara 1» (almacenes): fuera de alcance.
- Entradas del menú «Punto de venta», «Facturación», «Inicio», «Reportes», tarjeta «Tasa BCV» y «Valera · Sede única»: son del shell del panel/otros hitos; solo se tocan si DEC-01 = B (el tema) y sin agregar destinos nuevos.
- Fila «Ventas · 4 tickets» agrupada por día: el kardex de M4 muestra un movimiento por fila; la agrupación por día y los «tickets» son de M5/M6 (`[DEC-PENDIENTE]` en M5).
- Segundo umbral «2 días» (DEC-09), «Por vencer» como filtro sin umbral definido (DEC-09), KPI (DEC-08), código `L-0412` (DEC-10).
- Cualquier filtro por «cámara», ubicación o proveedor.

#### 4.5.2 Registrar merma (Filament, modal de acción)

- **Referencia:** solo existe el botón «Registrar merma» en `d-inventario.html`; **no hay maqueta del formulario**. Se construye con los patrones de design.md §4 «Formularios» y un modal nativo de Filament (en B con los tokens del tema).
- **Campos:** Lote (selector con búsqueda: código y producto, mostrando saldo), Tipo de merma (los cuatro tipos de merma de DEC-04, sin «ajuste por conteo», que solo lo crea el conteo), Peso (kg, 3 decimales; no puede superar el saldo, mensaje en `brand` debajo del campo), Motivo (obligatorio). Responsable (obligatorio): selector de usuarios activos con el usuario conectado preseleccionado; puede elegirse otra persona, por ejemplo quien pesó. Quien registra queda aparte como `user_id` (DEC-05).
- **Etiquetas:** siempre visibles (no solo placeholder); campos de 48–52 px; foco con borde de 2 px `brand`; error en `brand`. Botón de confirmación con la acción del modal (primario `brand`, no `accent`: `accent` se reserva para vender/cobrar/confirmar de la PWA). Resultado: notificación de éxito y el movimiento aparece arriba del kardex del lote.
- **No implementado:** adjuntar fotos, categorías de motivo, aprobación (sin BR; si se quieren, `[DEC-PENDIENTE]` nuevo).

#### 4.5.3 Conteo físico (Filament, oficina)

- **Referencia:** botón «Conteo físico» de `d-inventario.html`; **no hay maqueta de la sesión**. Patrones de design.md §3/§4 con componentes de Filament. Visible con `inventory.count`.
- **`InventoryCountResource`:** lista de sesiones con píldoras de estado como `StatusBadge` (`draft` en `info`, `confirmed` en `success`, `cancelled` en `neutral`; `accent` no se usa como estado); formulario con las líneas por lote: Lote · Producto · Saldo del sistema · Peso contado (campo de 52 px con borde `brand` al enfocar, como el campo «Piel» de `m-despiece.html`) · Diferencia (kg) con tono `success` si es 0 y `warning` si no. Acciones: «Confirmar conteo» (con modal que resume los ajustes a generar y el total en kg), «Descartar».
- **Barra de avance** («N de M lotes pesados») con `ProgressBar`-equivalente de Filament; en B, con los tokens (`brand` sobre `canvas`, extremos redondeados).
- **No implementado:** lectura de balanza (backlog), conteo «a ciegas», segundo aprobador (DEC-07), exportación a Excel/PDF (sin BR).

#### 4.5.4 Pantallas de PWA (piso)

**Ninguna en M4**, salvo que el humano elija DEC-06 B. No hay maqueta; si se aprueba, se construyen con los componentes existentes (`AppHeader` variante `floor`, `ListRow`, `StatusBadge`, `StickyActionBar`, `AppInput`, `AppButton`, `DeviationIndicator` para la diferencia frente al sistema) y se crean primero `WeightDisplay` y `NumericKeypad` (design.md §3), sin inventar flujos fuera de BR-15/BR-16. Necesitarían conexión (BR-26 solo cubre ventas).

#### 4.5.5 Rutas

Sin rutas Inertia nuevas con DEC-06 A. Las páginas y recursos de Filament se registran por descubrimiento. Si DEC-01 = C o DEC-06 = B se agregan bajo `routes/web.php` (`inventory/...`, middleware `auth`) con FormRequest + Policy por ruta (C3).

## 5. Tareas

Cada tarea cita sus BR y dependencias. Las pruebas se escriben primero (rojo) y llevan el ID de la BR en el nombre (C16); riesgo alto (C18): TDD estricto en el kardex.

- [x] **1.0** Aprobación de este documento por el humano y respuesta a los `[DEC-PENDIENTE]` de §3. Si DEC-01 = B o C: el humano autoriza el cambio a `docs/design.md` §2.4/§5 (commit aparte, antes del código de pantallas). — sin dependencias
- [x] **1.1** Revisar el esquema real contra los supuestos de §4 y el hallazgo 7 de §2.1 (zona horaria de `expires_at`). — depende de 1.0
- [x] **2.1** Migraciones: índice de `inventory_movements`, `inventory_counts`, `inventory_count_lines`; semillas de `settings`. — depende de 1.1, DEC-07
- [x] **2.2** Enum `MovementType` con etiqueta/ícono/tono; las Actions existentes lo usan (PRESERVE: tests de M2/M3 sin cambios). — depende de 1.1, DEC-04
- [x] **2.3** Modelos, casts decimales (`decimal:3`) y factories con datos ficticios para conteos. — depende de 2.1
- [x] **2.4** Permisos `inventory.*`, seeder de roles y `BatchPolicy`/`InventoryCountPolicy`; test de arquitectura C14 en verde. — depende de 2.3, DEC-05
- [x] **3.1** `StockBalance` y migración de `OpenButcheryOrderAction` y `ListAvailableRawMaterialBatchesAction` a ese servicio. — BR-14 · depende de 2.2
- [x] **3.2** `ConsumeStockFefoAction`: orden FEFO, costo por lote, reparto entre lotes, lotes sin fecha y vencidos, configuración de BR-17 y faltante, concurrencia. — BR-14, BR-17 · depende de 3.1, DEC-02, DEC-03
- [ ] **3.3** `RegisterShrinkageAction` con tipos, motivo obligatorio, saldo no negativo y auditoría. — BR-15, C15 · depende de 2.4, 3.1, DEC-04, DEC-05
- [ ] **3.4** `OpenInventoryCountAction`, `RegisterCountedWeightAction`, `CancelInventoryCountAction`. — BR-16 · depende de 2.4, 3.1, DEC-07
- [ ] **3.5** `ConfirmInventoryCountAction`: un ajuste por diferencia ≠ 0 (negativo con la regla de costo del lote, positivo a 0,00 por DEC-11 B) con el responsable de cada línea, auditoría, sesión confirmada inmutable. — BR-15, BR-16, C15 · depende de 3.3, 3.4
- [ ] **3.6** Ajustar el mensaje de `VoidButcheryOrderAction` (DEC-09 A) con un test que fije el texto. — C10 · depende de 3.3, 3.5, DEC-09
- [ ] **3.7** Si DEC-10 = B: código de lote legible en ambas Actions y migración de datos con prueba de integridad (cada movimiento y orden conserva su lote). — BR-14 · depende de 1.1, DEC-10
- [ ] **4.1** Estructura de la pantalla de inventario: tabla de lotes, filtros, KPI (DEC-08), alertas de vencimiento (DEC-09) y ajuste pendiente (BR-17) siguiendo §4.5.1. — BR-14, BR-17, C10 · depende de 3.1, 3.2, DEC-01, DEC-08, DEC-09
- [ ] **4.2** Panel de kardex del lote con etiqueta, ícono y tono por tipo, saldo y nota. — BR-14, C10 · depende de 4.1
- [ ] **4.3** Acción «Registrar merma» según §4.5.2. — BR-15 · depende de 3.3, 4.1
- [ ] **4.4** `InventoryCountResource` según §4.5.3. — BR-16 · depende de 3.5, 4.1
- [ ] **4.5** Si DEC-01 = B: tema de Filament y vistas con los tokens (sin hex ni estilos en línea), Figtree y Baloo 2 locales (dependencia nueva: `[DEC-PENDIENTE]` de design.md §2.2, la aprueba el humano, C1). Si = C: pantalla Vue. — depende de 1.0, DEC-01
- [ ] **5.1** Tests de aceptación por BR (ver §6): FEFO con tres lotes y costos distintos, merma de cada tipo, conteo con diferencia positiva y negativa, venta sin stock en las dos configuraciones (por defecto bloquea; lote vencido saltado y no vendible); regla de costo y valor (C9): lote de 200 kg con `total_cost` 190,01 vendido entero cuesta 190,01, lote de 950 kg con 1.000,00 consumido por partes suma 1.000,00 y queda en 0,00, y lote viejo de M1 donde `unit_cost × peso` ≠ `total_cost`. — BR-14 a BR-17 · depende de 3.2, 3.3, 3.5
- [ ] **6.1** Comandos de AGENTS §2 y GGA en verde; evidencia. — depende de todo lo anterior
- [ ] **6.2** Verificador independiente (kardex = riesgo alto, C18); `gentle-ai review assess` sobre el rango de commits y, si sale `high`, se propone la revisión y se espera. — depende de 6.1
- [ ] **6.3** Cierre: confirmación del humano (AGENTS §4.3 puerta 2). — depende de 6.2

## 6. Evidencia

*(Plantilla vacía; se llena al implementar.)*

### Tests por BR
| BR | Test (ruta y nombre) | Resultado |
|---|---|---|
| BR-14 | `tests/Feature/Inventory/…` (saldo = Σ movimientos; FEFO consume el que vence antes y toma su costo; sin fecha; empate; vencido) | |
| BR-15 | `tests/Feature/Inventory/…` (cada tipo; motivo obligatorio; no supera el saldo; auditoría; responsable distinto de quien registra queda guardado en `responsible_user_id` y `user_id` conserva a quien registró; responsable inexistente o inactivo se rechaza) | |
| BR-16 | `tests/Feature/Inventory/…` (diferencia positiva y negativa; sesión confirmada no se edita; sin diferencia = sin movimiento; el responsable de cada línea queda en el movimiento y `user_id` es quien confirma; sin responsable no se puede registrar el peso) | |
| BR-17 | `tests/Feature/Inventory/…` (permitir con alerta de ajuste pendiente; bloquear sin escribir nada) | |
| C9 | `tests/Feature/Inventory/…` (Σ costo de las salidas = `total_cost`; el lote vaciado vale 0,00; sin float) | |
| C10 | `InventoryMovementsImmutableTest` (heredado) más corrección por movimiento inverso | |
| C14 | `tests/Architecture/C14NoRoleHelpersTest.php` (heredado) | |

### Comandos (AGENTS §2)
| Comando | Resultado |
|---|---|
| `./vendor/bin/sail artisan test` | |
| `./vendor/bin/sail composer types:check` | |
| `./vendor/bin/sail pint --test` | |
| `./vendor/bin/sail pnpm build` | |
| `./vendor/bin/sail pnpm test` | |
| GGA | |

### Tramos (commits)
| Tramo | Contenido | Commit |
|---|---|---|
| | | |

### Verificador independiente / review
*(Pendiente.)*

### Siguiente paso
*(Pendiente.)*
