# M1 — Tema de la oficina (Filament) según las maquetas

Rama `feat/m1-office-theme`, sale de `feat/m1-roles` (293cf46). Aplica a la oficina el diseño de `docs/design.md` §2.5; antes solo eran configurables el nombre, el logo y los colores.

## Specs

- **S1** — «El diseño de la oficina»: aplicar el tema de las maquetas al panel (colores, fuentes, menú lateral oscuro, tablas, badges y KPI), tal como lo describe `docs/design.md` §2.5. `d-dashboard.html` y `d-inventario.html` son referencia visual.
- **S2** — design.md §2.5: «Archivo `resources/css/filament/admin/theme.css`, que importa los tokens de §2.4 y las fuentes de §2.2. No requiere dependencia nueva, porque Tailwind ya está.»
- **S3** — design.md §2.5, deben coincidir con la maqueta: «encabezado de página, tarjetas KPI, tablas (alto de fila, divisores `line-soft`, cifras tabulares a la derecha), badges, filtros y menú lateral (`brand-night`, ítem activo en `brand`)». «Lo demás (modales, notificaciones, paginación, formularios de recursos) se deja como lo trae Filament con los colores del panel.»
- **S4** — Fuentes: «los descargo yo»: Baloo 2 y Figtree como `woff2` en `public/fonts/`, declaradas con `@font-face`, sin dependencia nueva. Licencia OFL.
- **S5** — C6: los colores salen de las variables `--brand` y `--accent` que la configuración de empresa sobrescribe; el tema no escribe ningún hex de marca fijo ni el nombre del cliente.
- **S6** — Modo oscuro (design.md §2.5): se mantiene; el tema solo sobrescribe los tokens de superficie en modo claro.
- **S7** — Fuera de alcance: las tarjetas KPI del dashboard son datos del hito M6 (BR-06, BR-11, BR-21, BR-28) y la tasa BCV en el menú no tiene BR de pantalla. Aquí solo queda lista la **apariencia** del componente KPI; no se construye el dashboard.
- **S8** — «el bg del sidebar deberia ser #2A1215 no se ve la tasa del dolar del dia». El fondo del menú es `brand-night` con valor fijo `#2A1215` (design.md §2.1), no derivado de la marca. Al pie del menú va la tarjeta de la maqueta: «Tasa BCV de hoy», el monto en `accent` («Bs 233,56») y «Carga automática · 8:02 a. m.». Si la tasa vigente no es de hoy: «Tasa BCV del dd/mm»; si no hay ninguna: «Sin tasa registrada». Hora y fecha en Caracas (C13).

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T1 | S4 | worker — fuentes woff2 en `public/fonts` y `@font-face` | 4eb2e69 |
| T2 | S2, S3, S5, S6 | worker — `theme.css`, registro en Vite y en `AdminPanelProvider` | 4eb2e69 |
| T3 | S8 | inline — `SidebarRate` + hook `SIDEBAR_FOOTER` + `--brand-night` fijo | (este commit) |

## Log

- **L1** (2026-11-20, pedido literal): «aun no veo las cambios en el front». Aclarado por el humano: «El diseño de la oficina».
- **L2** Decisión del humano: fuentes «Los descargo yo».
- **L3** Hallazgo: `resources/css/filament/` no existía y no había fuentes; §2.5 estaba documentado pero sin implementar.
- **L4** Hecho en 4eb2e69: 289/289, pint, types:check 0, build ok (el tema sale en el manifest), Vitest 24/24. La PWA dejó de cargar la fuente de Bunny (BR-26). El panel recibe `--brand`/`--accent` por un render hook. **No verificado visualmente** (sin navegador): contraste del menú, alto de fila de 44 px, badges, foco, carga de fuentes y modo oscuro; lo confirma el humano. `theme.css` importa `app.css`, lo que suma ~49 KB al CSS; separar los tokens en un archivo propio queda como mejora.
- **L5** (pedido literal): «el bg del sidebar deberia ser #2A1215 no se ve la tasa del dolar del dia». Causa del color: `--brand-night` se derivaba con `color-mix()` y no daba exactamente ese valor. Causa de la tasa: la tarjeta de d-dashboard.html no se había implementado (S7 la dejó fuera). Consecuencia a tener en cuenta: con otra marca, el fondo del menú sigue siendo `#2A1215`; si un cliente lo quiere distinto hay que agregar un ajuste de color de menú. Suite: 293/293, pint, types:check 0 (se declaró el tipo de `ExchangeRate::effective_at`), build, Vitest 24/24.
