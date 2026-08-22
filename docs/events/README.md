# Eventos y procesamiento asíncrono

**Origen:** secciones 26–29, 45 y 61–62 del maestro v1.6.

## Contrato mínimo

Cada evento incluye `event_id`, nombre/versión, agregado, tiempos de ocurrencia/registro, actor, `correlation_id`, `causation_id`, clave de idempotencia, contexto de mercado/sucursal y payload/metadata versionados.

## Familias iniciales

- Ordering: colocado, enviado, aceptado, rechazado, cancelado, completado y cerrado.
- Payments: intención, autorización, captura, fallo, reembolso y contracargo.
- Preparation: inicio, listo, handoff e incidencia.
- Logistics: solicitud, ranking, oferta, asignación, llegada, recojo, entrega y fallo.
- Pricing: cotización, expiración, promoción, tarifa y comisión.
- Settlements: movimientos, ganancias, payout y ajustes.
- Support/Security: incidencia, evidencia, compensación, riesgo y acción sensible.

## Garantías

Outbox se guarda en la misma transacción del cambio; publicación ocurre desde cola. Inbox deduplica entradas externas. Consumidores deben ser idempotentes. Engagement y DataAI consumen eventos públicos; DataAI no modifica agregados directamente.

## Colas previstas

`critical`, `orders`, `logistics`, `payments`, `notifications`, `search`, `analytics` y `maintenance`. Redis/Horizon es la propuesta del maestro para MVP, pero su instalación no pertenece a esta fase.

## Tiempo real

Eventos visibles previstos: `order.updated`, `delivery.location` y `merchant.new_order`. El contenido se autoriza antes de publicar y nunca mezcla comercios en canales compartidos.
