# Arquitectura

**Origen:** secciones 13 y 51–64 del maestro v1.6, más ajustes de estructura aprobados para Sprint 00.

## Decisión principal

Backend maestro Laravel 13 con PHP 8.4 en `apps/api`, como monolito modular desplegado inicialmente como una unidad. Sus límites internos permiten extraer servicios solo cuando el volumen lo justifique. Esta decisión técnica sustituye solo la versión Laravel 12 indicada en el maestro v1.6; no modifica requisitos funcionales ni reglas de negocio.

## Estructura futura

```text
apps/api/app/
├── Modules/
│   ├── Identity/
│   ├── Marketplace/
│   ├── Catalog/
│   ├── Pricing/
│   ├── Ordering/
│   ├── Payments/
│   ├── Logistics/
│   ├── Settlements/
│   ├── Support/
│   ├── Engagement/
│   ├── Platform/
│   └── DataAI/
└── Shared/
```

Esos directorios no se crean en la fase documental.

## Capas

- **Domain:** entidades, value objects, políticas, estados, eventos y contratos; sin framework.
- **Application:** casos de uso, comandos, queries, DTO, autorización y transacciones.
- **Infrastructure:** persistencia y adaptadores de Redis, storage, pagos, mapas y mensajería.
- **Interfaces:** HTTP, webhooks, consola, jobs y broadcasting.
- **Shared:** Money, PublicId/ULID, Clock, Result, paginación y eventos base si son realmente transversales.

## Dependencias

Cada módulo depende solo de `Shared` y de contratos aprobados de módulos anteriores. Engagement y DataAI consumen eventos publicados; DataAI no modifica agregados directamente. Ningún módulo consulta tablas privadas ajenas.

## Detalle

- [Modelo de datos](data-model/README.md)
- [Seguridad](security/README.md)
- [No funcionales](non-functional/README.md)
- [Decisiones](decisions/README.md)
- [API](../api/README.md)
- [Eventos](../events/README.md)
