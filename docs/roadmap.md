# Roadmap

Alcance acordado: 98 h de MVP + 18 h de facturación digital. Ritmo: unas 20 h por semana. Pausa del 21-dic al 3-ene.
Cada hito tiene su tratamiento en ODD y un criterio de cierre comprobable.

| # | Hito | h | ODD | Hecho cuando |
|---|---|---|---|---|
| M0 | Fundación: repo, Sail, PostgreSQL, Filament, Pest, Larastan, GGA, glosario, modelo de datos, kardex | 12 | feature + verificador | El trigger del kardex rechaza `UPDATE`/`DELETE` (test); los tests de arquitectura de C9 y C14 pasan; un commit pasa GGA |
| M1 | Configuración y catálogo: empresa, roles, tasa BCV, métodos de pago, productos | 8 | directo | BR-01 a BR-05 en verde |
| M2 | Compras y recepción con pesaje | 8 | feature | BR-06 y BR-07 en verde; se crea un lote desde una compra |
| M3 | Despiece y costeo | 16 | feature + verificador + 4R | BR-08 a BR-13 en verde, incluido el caso dorado |
| M4 | Inventario: kardex por lote, mermas, conteo | 10 | feature | BR-14 a BR-17 en verde |
| M5 | POS PWA + registro de talonarios | 24 | investigar → feature + verificador | BR-18 a BR-26 en verde; venta mixta con vuelto en otra moneda y cierre de caja cuadrado en tablet → **cobro 2** |
| M6 | Reportes: ventas, margen por corte, rendimiento, inventario valorizado | 8 | directo, uno por reporte | BR-28 en verde; el dueño los consulta desde el teléfono |
| M7 | Puesta en marcha: VPS, carga inicial, 5 a 10 despieces reales, capacitación | 12 | directo | Plantillas calibradas con datos reales y el negocio opera → **cobro 3**, empieza la garantía de 60 días |
| M8 | Facturación digital (adaptador SENIAT) | 18 | investigar → feature + verificador + 4R | BR-27 en verde contra el ambiente de pruebas → **cobro 4** |

El **cobro 1** se recibe al iniciar M0.

## Disparadores y límites
- **M5:** si la cola sin conexión en IndexedDB no cabe en las horas, el POS sale en línea y la cola pasa al backlog. Esa decisión la toma el humano.
- **M8** arranca cuando el SENIAT publique su especificación. Si el **1-mar-2027** no la ha publicado, se activa la alternativa de la imprenta digital (se cotiza aparte), para estar operando antes del 19-abr-2027.
- **Antes de M1** hay que cerrar con el contador los `[abierto]`: alícuota de la charcutería, IGTF, moneda contable y validez del facturero manual.
- **Antes de M5** hay que tener la balanza elegida.

## Backlog (fase 3, sin fecha, se cotiza)
Delivery (8 h, US$ 100) · cuentas por cobrar de mayoristas · panel móvil del dueño · balanza por puerto serie · cola sin conexión del POS, si no entró en M5.
