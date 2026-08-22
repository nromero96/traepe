# Estados y transiciones

**Origen:** secciones 18–22 del maestro v1.6.

Cada comando valida actor, alcance, origen, precondiciones, concurrencia e idempotencia y publica eventos al completarse. Pedido, pago, preparación y entrega evolucionan por separado.

## Pedido comercial

`draft → pending_payment → placed → merchant_pending → accepted → in_fulfillment → completed → closed`.

Finales alternos: `rejected` y `cancelled`. Cancelar solo es válido donde la política lo permita y debe calcular impactos económicos.

## Pago

Estados: `not_required`, `pending`, `authorized`, `captured`, `failed`, `voided`, `partially_refunded`, `refunded` y `chargeback`. Un pedido cancelado puede mantener pago capturado mientras el reembolso continúa.

## Preparación

`not_started → confirmed → preparing → ready → handed_off`, con `issue` para incidencias. No se puede marcar listo antes de aceptar. El handoff exige coincidencia/código cuando aplique.

## Entrega

`not_requested → searching → offered → assigned → to_pickup → at_pickup → picked_up → to_dropoff → at_dropoff → delivered`.

Alternos: `failed` y `cancelled`. Tras el recojo, cambios requieren intervención y cadena de custodia. Reasignar cierra la asignación previa sin crear otro pedido.
