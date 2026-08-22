# Idempotencia, concurrencia y SLA

**Origen:** secciones 26–30 del maestro v1.6.

## Temporizadores

Existen temporizadores para pago, respuesta y preparación de tienda, oferta de repartidor, espera en recojo/destino y cierre operativo. Sus valores son configurables por ciudad, categoría, modalidad o sucursal. Las automatizaciones ejecutan comandos; no escriben estados directamente.

## Concurrencia e idempotencia

- Checkout: clave por intento.
- Webhooks: deduplicación por proveedor, cuenta e ID externo.
- Aceptación/cancelación simultáneas: versión o bloqueo del pedido.
- Oferta logística: un ganador; las demás se cierran.
- Stock: compromiso atómico según política de inventario.
- Reembolsos y liquidaciones: libro de movimientos.

## Criterios

Toda transición identifica origen, comando, destino, actor y precondiciones. Cancelaciones conservan etapa, solicitante, causa, responsable y política. Eventos correlacionan dimensiones del pedido. Temporizadores y automatizaciones deben ser auditables e idempotentes.
