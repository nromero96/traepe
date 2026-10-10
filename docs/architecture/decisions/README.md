# Decisiones de arquitectura

## Aprobadas

- Backend maestro Laravel 13 con PHP 8.4 en `apps/api`; sustituye únicamente la versión técnica Laravel 12 del maestro v1.6.
- Monolito modular en `apps/api/app/Modules`.
- Doce módulos iniciales y `apps/api/app/Shared` restringido a elementos transversales.
- PostgreSQL/PostGIS como persistencia principal.
- API central versionada y arquitectura orientada a dominios/eventos.
- `admin-dashboard` como interfaz exclusiva del administrador maestro.

## ADR aprobados

- [ADR-001 — Runtime y framework](ADR-001-runtime-framework.md)
- [ADR-002 — Datos, identificadores y mensajería](ADR-002-data-identifiers-messaging.md)
- [ADR-003 — Infraestructura local](ADR-003-local-infrastructure.md)
- [ADR-004 — Calidad y CI](ADR-004-quality-ci.md)
- [ADR-005 — Componentes oficiales de Laravel](ADR-005-laravel-components.md)
- [ADR-006 — Base geográfica vacía de Marketplace](ADR-006-marketplace-geographic-foundation.md)
- [ADR-007 — Base comercial local vacía de Marketplace](ADR-007-marketplace-commercial-foundation.md)

## Pendientes

Consultar [registro de decisiones pendientes](pending-decisions.md). No deben resolverse implícitamente durante implementación.

## ADR futuros

Los ADR se crearán al aprobar cada decisión técnica concreta. Formato mínimo: estado, contexto, decisión, alternativas, consecuencias, fecha y referencia al maestro.
