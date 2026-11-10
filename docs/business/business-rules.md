# Reglas de negocio

Cada regla se verifica con un test Pest que lleva su ID (C16). `[abierto]` = pendiente del contador o del cliente; se implementa como configuración con el valor por defecto indicado.

## General
- **BR-01** Peso en kg con 3 decimales; montos con 4 decimales internos y 2 en pantalla e impresión.
- **BR-02** La tasa BCV es diaria: carga automática con respaldo manual. Cada operación guarda la tasa que usó.
- **BR-03** Roles iniciales como conjuntos de permisos `module.action` (C14): administrador (todos los permisos asignados), cajero (POS y su propio cierre) y carnicero (recepción y despiece). Anular o devolver requiere un permiso propio, que de inicio solo tiene el administrador.

## Catálogo
- **BR-04** Tipos de producto: materia prima, derivado y reventa. Unidad: kg o pieza. Listas de precio: detal y mayor.
- **BR-05** El IVA va por producto. Pollo, res y cerdo en estado natural, refrigerados o congelados: exentos. Charcutería: `[abierto]`, 16 % por defecto.

## Compras
- **BR-06** Toda recepción se pesa. La diferencia entre el peso recibido y el facturado por el proveedor queda registrada.
- **BR-07** Costo del lote = compra + flete + otros costos asignados. El lote nace en la recepción.

## Despiece
- **BR-08** Cada materia prima tiene una plantilla: cortes con % estándar más merma esperada, que suman 100 %.
- **BR-09** Cada orden de despiece registra el lote, los kg de entrada y el carnicero, y muestra los kg esperados por corte.
- **BR-10** Merma real = kg de entrada − Σ kg de salida. Si las salidas superan la entrada, la orden se rechaza.
- **BR-11** Se alerta cuando un corte se desvía de la plantilla más que el umbral configurado (2 puntos por defecto).
- **BR-12** Costeo por valor relativo de mercado: `Costo_i = C_total × (kg_i·p_i) / Σ(kg_j·p_j)`. La merma no lleva costo. El residuo de redondeo va al corte de mayor valor, para que Σ costos = C_total exacto.
- **BR-13** Caso dorado: 20 kg de pollo a US$ 56,00 → filete 24,57 · muslo 19,16 · alas 5,51 · carapacho 4,16 · hígado y molleja 2,36 · piel 0,24 (tabla de la propuesta técnica).

## Inventario
- **BR-14** Stock = Σ movimientos por lote. Las salidas consumen primero el lote que vence antes (FEFO) y toman su costo.
- **BR-15** Mermas tipificadas, siempre con motivo y responsable: deshidratación, rebanado, vencimiento o decomiso, y ajuste por conteo.
- **BR-16** El conteo físico con pesaje genera un ajuste por la diferencia.
- **BR-17** Venta sin stock suficiente: `[abierto]`. Por defecto se permite, con alerta de ajuste pendiente.

## Ventas y POS
- **BR-18** Venta por kg (peso digitado o etiqueta EAN-13 de balanza con prefijo configurable) o por pieza.
- **BR-19** Pagos mixtos en Bs y divisas: Σ pagos ≥ total. El vuelto puede darse en otra moneda, a la tasa de la venta.
- **BR-20** IGTF: `[abierto]`, desactivado por defecto.
- **BR-21** El cierre de caja compara lo esperado con lo contado por método de pago y registra la diferencia.
- **BR-22** El ticket del POS no es comprobante fiscal y nunca dice «factura».
- **BR-23** Cliente: consumidor final con cédula; contribuyente con RIF y razón social.

## Facturación
- **BR-24** Cada venta facturada guarda los totales por alícuota (base, exento con «(E)», IVA). Si se pactó en divisa, guarda también el total en Bs, el total en divisa y la tasa.
- **BR-25** MVP: se registra el n.º de factura y de control del talonario. Se valida el consecutivo por talonario y se alertan saltos y repetidos.
- **BR-26** Por cada caja se controlan los talonarios: rango, usados, anulados y disponibles. Se usan hasta el **18-abr-2027**; después solo valen los formatos de contingencia.
- **BR-27** Fase 2: estados `borrador → pendiente → aceptado | rechazado → anulado`. El envío es idempotente (referencia única) y la evidencia se guarda 10 años. Una nota de crédito o débito usa el mismo medio que su factura.

## Reportes
- **BR-28** Margen por corte = precio de venta − costo asignado (BR-12). El rendimiento real se compara con la plantilla por orden, por materia prima y por carnicero.
