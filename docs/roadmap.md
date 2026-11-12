# Roadmap

Alcance acordado: 117 h de MVP + 18 h de facturación digital = 135 h. Ritmo: unas 20 h por semana. Pausa del 21-dic al 3-ene.
Cada hito tiene su tratamiento en ODD y un criterio de cierre comprobable.

| # | Hito | h | ODD | Hecho cuando |
|---|---|---|---|---|
| M0 | Fundación: repo, Sail, PostgreSQL, Filament, Pest, Larastan, GGA, glosario, modelo de datos, kardex | 12 | feature + verificador | El trigger del kardex rechaza `UPDATE`/`DELETE` (test); los tests de arquitectura de C9 y C14 pasan; un commit pasa GGA |
| M1 | Configuración y catálogo: empresa, roles, tasa BCV, métodos de pago, productos | 8 | directo | BR-01 a BR-05 en verde |
| M2 | Compras y recepción con pesaje | 8 | feature | BR-06 y BR-07 en verde; se crea un lote desde una compra |
| M3 | Despiece y costeo | 16 | feature + verificador + 4R | BR-08 a BR-13 en verde, incluido el caso dorado |
| M4 | Inventario: kardex por lote, mermas, conteo | 10 | feature | BR-14 a BR-17 en verde |
| M5 | POS PWA sin conexión + registro del n.º de factura manual | 31 | investigar → feature + verificador + 4R | BR-18 a BR-26 en verde; venta mixta con vuelto en otra moneda y cierre de caja cuadrado en tablet; ventas hechas sin conexión se sincronizan sin duplicarse → **cobro 2** |
| M5b | Cuentas por cobrar con abonos | 12 | feature + verificador + 4R | BR-29 a BR-35 en verde; venta a crédito con límite, abono en Bs convertido a la tasa del día y cierre de caja que incluye los abonos |
| M6 | Reportes: ventas, margen por corte, rendimiento, inventario valorizado | 8 | directo, uno por reporte | BR-28 en verde; el dueño los consulta desde el teléfono |
| M7 | Puesta en marcha: VPS, carga inicial, 5 a 10 despieces reales, capacitación | 12 | directo | Plantillas calibradas con datos reales y el negocio opera → **cobro 3**, empieza la garantía de 30 días |
| M8 | Facturación digital (adaptador SENIAT) | 18 | investigar → feature + verificador + 4R | BR-27 en verde contra el ambiente de pruebas → **cobro 4** |

El **cobro 1** se recibe al iniciar M0.

## Desglose de M5 (31 h)
- POS: venta por peso o pieza, pagos mixtos, vuelto, cierre de caja: 18 h.
- Registro del n.º de factura y de control del talonario: 3 h.
- Venta sin conexión (BR-26): copia local de catálogo, precios y tasa; cola en IndexedDB; sincronización idempotente; banner de estado: 10 h.

## Desglose de M5b (12 h)
- Clientes autorizados con límite y plazo; venta a crédito total o con pago inicial: 4 h.
- Abonos parciales multimoneda, aplicación a la deuda más antigua, cola sin conexión: 4 h.
- Estado de cuenta, reporte por antigüedad, abonos en el cierre de caja, alertas de vencimiento: 4 h.

## Disparadores y límites
- **M5:** la venta sin conexión es alcance obligatorio, no opcional. Si al investigar resulta más grande que las 10 h previstas, el humano decide cómo se ajusta antes de seguir.
- **M8** arranca cuando el SENIAT publique su especificación. Si el **1-mar-2027** no la ha publicado, se activa la alternativa de la imprenta digital (se cotiza aparte), para estar operando antes del 19-abr-2027.
- **Antes de M1** hay que cerrar con el contador los `[abierto]`: alícuota de la charcutería, IGTF, moneda contable y validez del facturero manual.
- **Antes de M5** hay que tener la balanza elegida.

## Backlog (fase 3, sin fecha, se cotiza)
Delivery (8 h, US$ 100) · panel móvil del dueño · balanza por puerto serie · venta a crédito sin conexión · cuentas por pagar a proveedores.
