# M1 — Dinero con 2 decimales exactos

Rama `feat/m1-money-2dp`, sale de `feat/m1-exchange-rate` (c7a1853). Cambia C9 y BR-01: hoy «dinero `numeric(18,4)`» y «montos con 4 decimales internos y 2 en pantalla e impresión».

## Specs

- **S1** — «en la economia de venezuela generalemnet usamos 2 decimales para dinero sin redondeo,montos exactos ejemplo: 3.99+3.99=7.98$ eso es lo que se paga exactamente». Todo monto de dinero se guarda y se muestra con 2 decimales exactos; las sumas, los pagos y el vuelto nunca redondean: 3,99 + 3,99 = 7,98.
- **S2** — «tasa bcv debe tener solo 2 decimales» / «quedamos que todos los montos de dinero tendrían solo 2 decimales en cualquier tasa». Ya hecho para la tasa (BR-02, 5d14522).
- **S3** — Precio por kg × peso: «Al centavo más cercano»: 1,8315 → 1,83 y 1,8350 → 1,84 (el 5 sube, half-up). El monto de la línea se crea una sola vez, al calcularlo; después se suma exacto.
- **S4** — Conversión a Bs e IVA: «Sobre el total, no por línea». Bs = total en $ × tasa, redondeado una vez. IVA = subtotal de cada alícuota × %, redondeado una vez (BR-24). Las líneas no se convierten ni gravan una por una.
- **S5** — Supuesto del padre, a confirmar: el precio por kg y el costo por kg son valores unitarios, no montos, y conservan sus decimales (4) para no distorsionar el costo del inventario; el peso sigue en 3 decimales.

## Tasks

| ID | Specs | Ruta | Commit |
|---|---|---|---|
| T0 | S1-S5 | explorador — mapa de impacto (Money, Actions, columnas, tests) | |

## Log

- **L1** (2026-11-20): «como se aplicaria redondeo y cuales casos?» y luego la aclaración de S1.
- **L2** Decisiones del humano (tras corregir una selección equivocada y repetir la pregunta): «Al centavo más cercano (Recomendado)» y «Sobre el total, no por línea (Recomendado)».
