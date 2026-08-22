# ADR-002 — Datos, identificadores y mensajería

- **Estado:** Aprobado
- **Fecha:** 22 de agosto de 2026
- **Decisión:** PostgreSQL con PostGIS; bigint interno; `public_id` ULID único e indexado; outbox/inbox técnico.

## Reglas

- IDs internos bigint nunca se exponen públicamente.
- Recursos públicos usan ULID único e indexado.
- PostgreSQL es fuente de verdad; PostGIS administra geodatos.
- Outbox mantiene `event_id` y payload inmutables; sus metadatos de publicación pueden cambiar.
- Inbox deduplica por `event_id` mediante constraint único.
- La cola entrega al menos una vez; jobs y consumidores son idempotentes para evitar efectos duplicados.
- Sprint 00 no implementa consumidores comerciales.

## Consecuencias

Migraciones, contratos y pruebas deben verificar integridad, concurrencia, deduplicación y no exposición de IDs internos.
