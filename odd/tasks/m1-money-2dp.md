# M1 — Dinero con 2 decimales exactos

Rama `feat/m1-money-2dp`, sale de `feat/m1-exchange-rate` (c7a1853). Cambia C9 y BR-01: hoy «dinero `numeric(18,4)`» y «montos con 4 decimales internos y 2 en pantalla e impresión».

## Specs

- **S1** — «en la economia de venezuela generalemnet usamos 2 decimales para dinero sin redondeo,montos exactos ejemplo: 3.99+3.99=7.98$ eso es lo que se paga exactamente». Todo monto de dinero se guarda y se muestra con 2 decimales exactos; las sumas, los pagos y el vuelto nunca redondean: 3,99 + 3,99 = 7,98.
- **S2** — «tasa bcv debe tener solo 2 decimales» / «quedamos que todos los montos de dinero tendrían solo 2 decimales en cualquier tasa». Ya hecho para la tasa (BR-02, 5d14522).
- **S3** — Precio por kg × peso: «Al centavo más cercano»: 1,8315 → 1,83 y 1,8350 → 1,84 (el 5 sube, half-up). El monto de la línea se crea una sola vez, al calcularlo; después se suma exacto.
- **S4** — Conversión a Bs e IVA: «Sobre el total, no por línea». Bs = total en $ × tasa, redondeado una vez. IVA = subtotal de cada alícuota × %, redondeado una vez (BR-24). Las líneas no se convierten ni gravan una por una.
- **S5** — Confirmado por el humano («Sí, unitarios en 4»): el precio por kg y el costo por kg son valores unitarios, no montos, y conservan 4 decimales; el peso sigue en 3.
- **S6** — «Redondear en sitio y repartir residuo»: la migración pasa las filas existentes a 2 decimales y reparte el residuo al mayor para que los costos asignados sigan sumando el total.
- **S7** — «Solo al despiece y a lo que ya se calcula»: la compra mantiene el monto de línea tecleado; no se agrega precio por kg a la compra. La regla aplica al reparto de costos, al costo de entrada y a las ventas cuando existan (M5).
- **S8** — «Sí, a numeric(18,2)»: la tasa congelada (`purchases.exchange_rate`, `batches.exchange_rate`, `exchange_rates.rate`) pasa a 2 decimales, coherente con BR-02.

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T0 | S1-S5 | explorador — mapa de impacto (Money, Actions, columnas, tests) | — |
| U1 | S1-S5 | inline — C9 (ya cambiado por el humano), BR-01, BR-12, BR-24 y comentario de `C9NoFloatTest` | 58a1319 |
| U2+U3 | S1, S3, S4, S5 | worker — `Money` sin doble redondeo, `UnitAmount`, cálculos de compras/despiece/inventario; columnas aún sin cambiar | |
| U4-U6 | S6, S8 | worker — migración de columnas con reparto de residuo y actualización de tests | |
| U7 | todos | verificador independiente + cierre | |

## Log

- **L1** (2026-11-20): «como se aplicaria redondeo y cuales casos?» y luego la aclaración de S1.
- **L2** Decisiones del humano (tras corregir una selección equivocada y repetir la pregunta): «Al centavo más cercano (Recomendado)» y «Sobre el total, no por línea (Recomendado)».
- **L3** Mapa de impacto: C9 ya decía `numeric(18,2)` en el árbol de trabajo (cambiado por el humano, sin commitear; esta rama lo incluye) mientras migraciones y `Money` usan 4. Decisiones del humano: «Redondear en sitio y repartir residuo (Recomendado)», «Solo al despiece y a lo que ya se calcula (Recomendado)», «Sí, a numeric(18,2) (Recomendado)», «Sí, unitarios en 4 (Recomendado)». Riesgo: `Money` sirve hoy para montos y unitarios; hay que separarlos. Postgres devuelve la escala de la columna, así que tras la migración ~150 aserciones de texto con 4 decimales cambian.
