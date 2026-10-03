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

## Evento técnico de 00E

`platform.technical_probe.v1` tiene [schema versionado](../api/schemas/platform-technical-probe.v1.json). Incluye event_id ULID, nombre/versión, agregado público ficticio, tiempos UTC, actor system sin identidad comercial, correlation_id, causation_id nulo, hash de clave, contexto mercado/sucursal nulo, payload operation_id y metadata schema_version. No contiene PII ni datos de negocio.

El envelope validado es inmutable en memoria y PostgreSQL. Se cifra al guardar el outbox; la cola transporta únicamente event_id más contexto de correlación saneado. El consumidor técnico lee el evento persistido, valida versión/hash/identidades y guarda inbox + un efecto ficticio en la misma transacción.

`platform:dispatch-outbox --limit=100` encola un job técnico; Horizon publica el lote y consume sus eventos. Un fallo de publicación conserva el evento pendiente, incrementa intentos y programa `next_attempt_at` con backoff técnico acotado de 1–60 s. Volver a encolar un lote después del plazo permite recuperarlo. No se incorpora un daemon/scheduler nuevo ni se decide una cadencia productiva; la operación local es explícita. La entrega sigue siendo al menos una vez y la deduplicación asegura un único efecto técnico.
