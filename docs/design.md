# Diseño de interfaz — ERP

> Referencia de interfaz para humanos y agentes. Cubre apariencia, layout y comportamiento de pantalla; **no define negocio**.
> Pantallas de referencia: `docs/design/screens/*.html` (HTML estático, se abren en el navegador). Fuente editable: canvas «ERP Graterol — Propuesta visual UI» (privado del humano).
> Todas las cifras, nombres y tasas de las pantallas son **datos de ejemplo**.

## 0. Cómo usar este documento (agentes)

1. Antes de crear o cambiar una pantalla Vue (PWA) o un recurso Filament, leer §1 a §4 y abrir la pantalla de referencia que indique §5.
2. **Jerarquía:** constitution > business-rules > roadmap > documento ODD > este archivo. Este documento manda solo sobre estilos, layout y componentes. Si una pantalla contradice una BR, manda la BR.
3. **Dibujado no es especificado.** Un botón, dato o flujo que aparece en una pantalla pero no tiene BR ni hito (§5, columna «Pendientes») **no se implementa**: se registra como `[DEC-PENDIENTE]` (C19).
4. Las pantallas usan estilos en línea y hex sueltos solo porque son maquetas. En el código se traducen a componentes (§3) con tokens (§2): **nunca se copian estilos en línea ni colores hex**.
5. Los textos de pantalla se toman tal cual de las referencias (español, C2).

## 1. Principios

1. **Móvil primero.** La PWA se diseña a 390 px y luego se amplía a tablet (≥ 768) y escritorio (≥ 1280).
2. **Una acción principal por pantalla**, fija abajo y al alcance del pulgar (barra de acción o navegación inferior).
3. **Toques de 48 px o más.** Teclado numérico 50 px, botón principal 56 px, botón principal en tablet 64 px.
4. **USD y Bs siempre juntos.** Divisa arriba y en grande, Bs debajo; en los totales se agrega la tasa usada (BR-02, C13).
5. **El color comunica estado.** El dorado (`accent`) se reserva para «Vender», «Cobrar», «Confirmar» y el siguiente paso de una tarea. El rojo (`brand`) es marca, navegación activa y acción secundaria fuerte.
6. **Cifras tabulares.** Kg con 3 decimales y montos con 2 (BR-01). Formato `es-VE`: miles con punto y decimales con coma (`1.284,50`).
7. **La marca es configuración (C6).** El nombre y el logo del negocio salen de Settings y nunca se escriben en el código. Los colores son tokens: sus valores por defecto viven en `app.css` (hoy son los del piloto) y Settings puede sobrescribirlos (§2.4).
8. **Sin internet, la caja sigue.** La PWA vende sin conexión (BR-26). Todo lo que el POS necesita para vender (pantallas, fuentes, íconos, catálogo, precios, tasa) está disponible localmente; el estado de conexión siempre es visible.

## 2. Tokens

### 2.1 Color (nombres semánticos; el valor es el tema del piloto)

| Token | Valor | Uso |
|---|---|---|
| `brand` | `#A51016` | Marca, nav activa, encabezado de inicio y POS, botón primario, bordes de selección |
| `brand-strong` | `#6E0A0F` | Presionado/hover, títulos grandes, montos destacados, encabezado de tareas de piso |
| `brand-night` | `#2A1215` | Menú lateral de escritorio |
| `brand-soft` | `#FBE9EA` | Fondo de error o fila crítica |
| `accent` | `#F6B01A` | CTA de venta/cobro, siguiente paso, tasa BCV en menú oscuro |
| `on-accent` | `#2B1D00` | Texto e íconos sobre `accent` |
| `accent-soft` | `#FDF0CF` | Bloque de vuelto y destacados suaves |
| `on-accent-soft` | `#6B4A00` | Texto secundario sobre `accent-soft` |
| `canvas` | `#F7F3EE` | Fondo de la app, teclas, campos de búsqueda |
| `surface` | `#FFFFFF` | Tarjetas, hojas inferiores, barras |
| `line` | `#E6DFD7` | Bordes y divisores |
| `line-soft` | `#F0EAE3` | Divisores dentro de listas y tablas |
| `ink` | `#1F1A17` | Texto principal |
| `ink-muted` | `#6B625C` | Texto secundario (contraste 5,9:1 sobre blanco) |
| `success` / `success-soft` | `#1E7A46` / `#E5F3EA` | Cubierto, exento (E), desviación dentro del umbral, ventas sincronizadas |
| `warning` / `warning-soft` | `#B45309` / `#FDF1E3` | Por vencer, desviación sobre el umbral, diferencia de pesaje, sin conexión |
| `info` / `info-soft` | `#1D4ED8` / `#E8EEFC` | IVA 16 %, avisos administrativos, sincronizando |
| `chart-neutral` | `#8C827B` | Cuarta serie de gráficos |
| `chart-muted` | `#E7B7B9` | Barras no destacadas |

Reglas:
- `accent` **nunca** va como texto sobre blanco (no alcanza contraste); siempre como fondo con `on-accent`.
- Severidad: `warning` = requiere acción pronto; `info` = administrativo; `brand-soft` + `brand` = error o bloqueo. (En `m-inicio.html` la desviación de despiece aparece en rojo: se implementa como `warning`.)
- Series de gráficos, en orden: `brand`, `accent`, `brand-strong`, `chart-neutral`. Se distinguen también por luminosidad, no solo por tono.
- Texto con contraste AA (4,5:1); íconos y barras con 3:1.

### 2.2 Tipografía

| Rol | Fuente | Peso / tamaño |
|---|---|---|
| Display: títulos, montos, KPIs | Baloo 2 | 800: 40–48 (total, peso); 700: 20–26 (títulos de sección, KPI) |
| Interfaz | Figtree | 700/17 botones · 600/15 títulos de fila · 400/16 base · 400/14 secundario · 600/12 rótulo en mayúsculas (+0,06em) |

- En móvil el texto base nunca baja de 16 px en campos (evita el zoom de iOS); 12 px solo para rótulos y metadatos.
- Montos y pesos: `font-variant-numeric: tabular-nums`.
- **Fuentes locales (obligatorio por BR-26):** la PWA vende sin conexión, así que las fuentes se sirven desde la app y no desde Google Fonts ni Bunny. Cómo incluirlas (archivos `woff2` en `public/` o paquete npm) es una dependencia nueva: `[DEC-PENDIENTE]`, la aprueba el humano (C1).

### 2.3 Espacio, radios y sombras

- Espaciado: 4 · 8 · 12 · 16 · 20 · 24 · 32. Margen lateral en móvil: 16; en escritorio: 32.
- Radios: 9–10 (segmento interno) · 12–14 (campos, botones, teclas) · 16–18 (tarjetas pequeñas, botón principal) · 20–24 (tarjetas) · 28 (hoja inferior y encabezado curvo) · 999 (chips y píldoras).
- Sombras: solo en lo que flota (tarjeta KPI montada sobre el encabezado, botón «Vender» de la navegación, segmento activo). Las tarjetas normales van planas sobre `canvas`.
- Prohibido: degradados decorativos, bordes laterales de color en tarjetas y emojis.

### 2.4 Implementación sugerida (Tailwind + variables CSS)

Los tokens viven como variables CSS para que Settings pueda cambiarlos por cliente sin recompilar. Verificar la sintaxis con Context7 según la versión de Tailwind instalada.

```css
/* resources/css/app.css: valores por defecto del producto */
:root {
  --brand: #A51016; --brand-strong: #6E0A0F; --brand-night: #2A1215; --brand-soft: #FBE9EA;
  --accent: #F6B01A; --on-accent: #2B1D00; --accent-soft: #FDF0CF; --on-accent-soft: #6B4A00;
  --canvas: #F7F3EE; --surface: #FFFFFF; --line: #E6DFD7; --line-soft: #F0EAE3;
  --ink: #1F1A17; --ink-muted: #6B625C;
  --success: #1E7A46; --success-soft: #E5F3EA;
  --warning: #B45309; --warning-soft: #FDF1E3;
  --info: #1D4ED8; --info-soft: #E8EEFC;
}

@theme inline {
  --color-brand: var(--brand);
  --color-brand-strong: var(--brand-strong);
  --color-accent: var(--accent);
  --color-on-accent: var(--on-accent);
  --color-canvas: var(--canvas);
  --color-ink: var(--ink);
  --color-ink-muted: var(--ink-muted);
  /* … resto de tokens con el mismo patrón … */
  --font-display: "Baloo 2", system-ui, sans-serif;
  --font-sans: "Figtree", system-ui, sans-serif;
}
```

- El layout de la PWA puede sobrescribir `--brand` y `--accent` con un `<style>` generado desde Settings. Si los colores de marca serán configurables desde M1 o fijos por ahora es `[DEC-PENDIENTE]`. El logo y el nombre del negocio sí son configuración desde M1 (empresa). Sin conexión, la PWA usa los últimos valores guardados.
- **Filament (oficina):** usa sus componentes nativos. Solo hereda el color primario (`brand`), la fuente Figtree y el logo. `d-dashboard.html` y `d-inventario.html` son referencia de **contenido y jerarquía**, no de píxeles. La API de colores y temas del panel se verifica con Context7. La oficina requiere conexión; el modo sin conexión es solo de la PWA.

## 3. Componentes (Vue, `resources/js/components/`)

Los componentes solo muestran y orquestan. Precios, totales, impuestos y vuelto llegan calculados del backend (C5); la PWA calcula valores provisionales únicamente sin conexión y los marca como tales hasta sincronizar.

| Componente | Especificación | Dónde se ve |
|---|---|---|
| `AppButton` | Variantes `accent` (CTA), `primary` (brand), `outline` (borde brand), `neutral` (borde line), `ghost`. Tamaños `md` 48, `lg` 56, `xl` 64. Ícono opcional a la izquierda. Deshabilitado: fondo `canvas`, texto `ink-muted` | Todas |
| `MoneyAmount` | Props `usd`, `bs`, `rate?`, `size`. Divisa arriba (display), Bs debajo (`ink-muted`). Solo formatea con `Intl.NumberFormat('es-VE')` | Totales, KPIs, líneas |
| `WeightDisplay` + `NumericKeypad` | Peso grande (display 48, unidad 22) con subrayado `brand`; teclado 3×4 (1–9, coma, 0, borrar), teclas de 50 px | `m-vender` |
| `BottomSheet` | Radio superior 28, asa de 40×5, fondo oscurecido `ink` al 45 % | `m-vender` |
| `SegmentedControl` | Pista `canvas`, segmento activo `surface` con sombra leve; altura 40 | Digitar/etiqueta, USD/Bs, rango del gráfico |
| `TaxBadge` | `(E) exento` en success; `IVA 16 %` en info. El texto lo da la alícuota del producto (BR-05, BR-24) | Producto, ticket |
| `StatusBadge` | Píldora con tono semántico: «Vence en 2 días», «En proceso» (accent), «Cubierto» | Inventario, despiece |
| `KpiCard` | Rótulo 14/600 muted, valor display 26–36, variación en píldora. Variante `hero` sobre `brand` | Inicio, dashboard |
| `ListRow` | Ícono en cuadro de 40 px con fondo `*-soft`, título 15/700, subtítulo 13 muted, cierre (monto, chevron). Alto mínimo 56 | Alertas, pagos, kardex |
| `ProductTile` | Nombre 15–16/700, precio por kg en display `brand-strong`, stock y alícuota en 12. Seleccionado / en ticket: anillo interior de 2 px `brand` | POS |
| `PaymentMethodTile` | Ícono + nombre, alto ≥ 72; activo con anillo `brand` y texto `brand`. La lista sale de los métodos configurados (M1) | Cobro |
| `DeviationIndicator` | Puntos frente a la plantilla: dentro del umbral → `success`; por encima → `warning` + ícono de alerta y fila `warning-soft`. El umbral sale de la configuración (BR-11), nunca fijo | Despiece |
| `ProgressBar` / `StackedBar` | 8–14 px, extremos redondeados, segmentos separados 2 px; siempre con `aria-label` que diga los valores | Cobro, despiece, pagos |
| `AppHeader` | Variantes `brand` (inicio, POS), `task` (blanco con «volver» de 44 px), `floor` (`brand-strong`, tareas de piso). Incluye `SyncStatus` en su variante chip | Todas |
| `BottomNav` | 5 destinos; el central «Vender» elevado (60 px, `accent`, radio 20). Activo en `brand` | PWA |
| `StickyActionBar` | Fija abajo, fondo `surface` y borde superior `line`, padding 12/16/20; una acción principal y como mucho una secundaria | Cobro, despiece |
| `SyncStatus` | Estado de conexión y cola (BR-26). **Banner** fijo arriba cuando no hay conexión: fondo `warning-soft`, texto `warning`, «Sin conexión · N ventas en cola». Al reconectar: fondo `info-soft`, «Sincronizando N ventas…». Al terminar: «Todo sincronizado» en `success` durante 3 s y desaparece. **Chip** en el encabezado: punto `success` en línea, `warning` sin conexión. Con `aria-live="polite"` | Todas las pantallas PWA |

Íconos: trazo de 2 px, 20–24 px, estilo Lucide. Agregar una librería de íconos es una dependencia nueva (C1); mientras no se apruebe, se usa SVG en línea. Sea cual sea la opción, los íconos van empaquetados en la app, nunca desde un CDN (BR-26).

## 4. Patrones

- **Layout PWA:** encabezado, contenido con scroll y padding de 16, y `StickyActionBar` o `BottomNav`. El padding inferior del contenido es igual al alto de la barra.
- **Venta por peso (BR-18):** se toca el producto, se abre la hoja inferior con el teclado y se elige «Digitar peso» o «Leer etiqueta» (EAN-13 con prefijo configurable). El botón «Agregar al ticket» muestra el subtotal.
- **Cobro (BR-19, BR-25):** total en encabezado `brand` con Bs y tasa; resumen de líneas con desglose por alícuota (BR-24); métodos en cuadrícula de 3; lista de pagos con equivalencia en la otra moneda; barra «Cubierto»; vuelto en `accent-soft` con selector de moneda; campos de número de factura y de control del talonario manual, con aviso en `warning` si el número ya fue usado. «Confirmar venta» en `accent`.
- **Ticket (BR-22):** el ticket nunca dice «factura». Se muestra la leyenda «El ticket no es comprobante fiscal».
- **Sin conexión (M5, BR-26):**
  - La venta se puede completar igual que con conexión. El total muestra la tasa guardada y el rótulo «Tasa del dd/mm» si no es la del día.
  - La venta queda en cola y se confirma en pantalla con «Venta guardada · se sincronizará al volver la conexión».
  - El aviso de número de factura repetido se hace contra las ventas conocidas por el dispositivo y se vuelve a validar al sincronizar.
  - Si al sincronizar el servidor encuentra una diferencia (precio o tasa), la venta se marca con `StatusBadge` «Diferencia registrada» en `warning` dentro del historial; no interrumpe la caja.
  - Cierre de caja con ventas pendientes: el botón «Cerrar caja» queda deshabilitado y se muestra en `warning` «Hay N ventas sin sincronizar. Conéctate para cerrar la caja».
  - Las pantallas que necesitan servidor (reportes, oficina) muestran «Necesita conexión» en lugar de datos viejos.
- **Cuentas por cobrar (M5b, BR-29 a BR-35):** no hay pantallas dibujadas; se siguen estos patrones y lo que no esté aquí es `[DEC-PENDIENTE]`.
  - En el cobro, el método «Crédito» aparece solo si el cliente seleccionado está autorizado y hay conexión. Muestra el saldo actual, el límite y el disponible con `MoneyAmount`. Si la venta supera el límite, el botón «Confirmar venta» se reemplaza por «Pedir autorización» y se muestra el aviso en `brand-soft` + `brand`.
  - «Registrar abono» se abre desde la ficha del cliente (PWA y Filament): saldo pendiente arriba, `PaymentMethodTile` para el método, monto con equivalencia a la tasa del día y la lista de ventas a las que se aplica (la más antigua primero, editable).
  - Saldos vencidos con `StatusBadge` en `warning` («Vencido hace 12 días»); al día en `success`.
  - El estado de cuenta y el reporte por antigüedad viven en Filament (oficina); en la PWA solo se consulta el saldo del cliente.
- **Formularios:** etiqueta visible siempre (no basta el placeholder); campo de 48–52 px; foco con borde de 2 px `brand`; error debajo del campo en `brand`; aviso en `warning`.
- **Carga:** esqueletos en `canvas` sobre tarjetas `surface`; no hay spinners de pantalla completa en el POS.
- **Fechas y horas:** en `America/Caracas` (C13), con estilo «Lunes 9 de noviembre» y «7:30 a. m.».
- **Accesibilidad:** `<button>` y `<a>` reales; `aria-label` en botones de solo ícono; gráficos con `role="img"` y descripción; foco visible; `SyncStatus` anuncia los cambios de estado con `aria-live`.

## 5. Pantallas de referencia y trazabilidad

| Archivo | Superficie | Hito | BR que cubre | Pendientes (no implementar sin decisión) |
|---|---|---|---|---|
| `guia-visual.html` | — | — | — | — |
| `m-vender.html` | PWA móvil | M5 | BR-04, BR-05, BR-14, BR-18, BR-26 | Las categorías salen del catálogo; no son una lista fija. `SyncStatus` no está dibujado: se implementa según §3 y §4 |
| `m-cobro.html` | PWA móvil | M5 | BR-19, BR-22, BR-23, BR-24, BR-25, BR-26 | Método «Otro»; botón de imprimir (la impresión es una integración, C7). La línea de IGTF aparece solo si se activa (BR-20). Si la maqueta muestra control de talonario (rango, disponibles), **no se implementa**: BR-25 solo pide registrar el número y alertar repetidos |
| `m-despiece.html` | PWA móvil | M3 | BR-09, BR-10, BR-11, BR-12 | «Leer balanza» es backlog (balanza por puerto serie): en el MVP el peso se digita, y la acción principal pasa a «Registrar peso». El despiece sin conexión no está en BR-26 (solo ventas): `[DEC-PENDIENTE]` si se necesita |
| `m-inicio.html` | PWA móvil | M6 | BR-21, BR-28 | Un inicio dedicado del dueño es el «panel móvil del dueño» del backlog. En M6 estos datos salen como reportes responsive. Las comparaciones («+12 %») y la bandeja de alertas unificada: `[DEC-PENDIENTE]` |
| `d-pos.html` | PWA tablet | M5 | BR-04 (lista mayor), BR-18, BR-19, BR-22, BR-23, BR-24, BR-26 | «Poner en espera» y «Vaciar ticket» |
| `d-dashboard.html` | Filament | M6 | BR-06, BR-11, BR-21, BR-28 | Variaciones contra periodos anteriores; «Rendimiento por carnicero» como porcentaje global (BR-28 lo pide por orden, materia prima y carnicero) |
| `d-inventario.html` | Filament | M4 | BR-14, BR-15, BR-16, C10 | Ninguno |
| (sin pantalla) | PWA y Filament | M5b | BR-29 a BR-35 | Todas las pantallas de cuentas por cobrar: se construyen con los patrones de §4 |

## 6. Qué no hacer

- Copiar estilos en línea, hex o tamaños sueltos de las pantallas: se usan tokens y componentes.
- Escribir el nombre o el logo del piloto en el código o las migraciones (C6), o usar un color fuera de las variables de §2.4.
- Calcular en el frontend precios, impuestos, totales o vuelto definitivos (C5). Sin conexión son provisionales y el servidor manda al sincronizar.
- Cargar fuentes, íconos o scripts desde un CDN en la PWA: sin internet no estarían (BR-26).
- Implementar algo solo porque aparece dibujado (§0.3).
- Usar `accent` como color de texto, emojis o degradados.
