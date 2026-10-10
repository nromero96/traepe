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

## Base ejecutable de 00D

Solo `Platform` existe como módulo: Application contiene los puertos de health/generación y el caso técnico de readiness; Infrastructure adapta los servicios de Docker y escribe el scaffold; Interfaces expone readiness y `make:module`. No se crea Domain mientras no existan reglas de dominio que lo justifiquen.

00E incorpora `Platform/Domain/Delivery` con fingerprint canónico, errores tipados de idempotencia y el evento técnico v1 validado/inmutable, sin framework. Application define los puertos de idempotencia, almacenamiento técnico, publicación y consumo; Infrastructure usa PostgreSQL/Redis/cifrado para implementarlos. Interfaces recibe comandos/jobs. Solo se implementa la operación ficticia `technical_probe`, sin reglas comerciales. Shared añade observabilidad de Laravel/Monolog y sigue fuera de las capas puras.

`Shared/Http` contiene el contrato reutilizable de respuesta, errores y correlación por solicitud. Su dependencia de Laravel pertenece a la frontera HTTP; no es código apto para importar en Domain o Application. No se crean Money, entidades, eventos ni identificadores comerciales anticipados.

`php artisan make:module Nombre` admite únicamente la lista de doce módulos aprobados, exige una raíz existente y rechaza un módulo existente, enlaces o nombres/rutas arbitrarios. Genera documentación de responsabilidades por capa y un provider PSR-4; no modifica `bootstrap/providers.php`, no genera reglas comerciales y no sobrescribe. Invocarlo solo al comenzar la implementación autorizada de ese módulo. Las pruebas lo ejecutan en una raíz temporal aislada y limpian exclusivamente su fixture; los once módulos futuros no existen en el árbol real.

`App\\` mantiene su autoload PSR-4 existente. Platform registra sus puertos y comando mediante un provider explícito. Las pruebas automatizadas verifican Application sin framework/adaptadores, Interfaces sin acceso directo a Infrastructure, Shared sin dependencias de módulos y ausencia de módulos prematuros. Los jobs y accesos técnicos de 00B/00C conservan su ubicación para evitar una reorganización ajena a este incremento.

## Capas

- **Domain:** entidades, value objects, políticas, estados, eventos y contratos; sin framework.
- **Application:** casos de uso, comandos, queries, DTO, autorización y transacciones.
- **Infrastructure:** persistencia y adaptadores de Redis, storage, pagos, mapas y mensajería.
- **Interfaces:** HTTP, webhooks, consola, jobs y broadcasting.
- **Shared:** Money, PublicId/ULID, Clock, Result, paginación y eventos base si son realmente transversales.

## Dependencias

Cada módulo depende solo de `Shared` y de contratos aprobados de módulos anteriores. Engagement y DataAI consumen eventos publicados; DataAI no modifica agregados directamente. Ningún módulo consulta tablas privadas ajenas.

## Marketplace local — 02A

Tras Identity local aprobado/publicado, 02A materializa Marketplace con código utilizado en las cuatro capas: punto/candidatos/selección puros, caso de uso y puerto, adaptador de lectura PostGIS y comando de diagnóstico. DP-027 gobierna únicamente polígonos sintéticos local/testing; no crea un mercado operativo ni tablas, seeds o APIs comerciales. No consulta tablas privadas de otro módulo. Véase [alcance de Sprint 02](../sprints/sprint-02/README.md).

Los checks de ausencia de módulos prematuros admiten exactamente Identity, Marketplace y Platform. Las descripciones anteriores de Platform conservan el estado histórico de Sprint 00.

## Índice de detalle

- [Modelo de datos](data-model/README.md)
- [Seguridad](security/README.md)
- [No funcionales](non-functional/README.md)
- [Decisiones](decisions/README.md)
- [API](../api/README.md)
- [Eventos](../events/README.md)
