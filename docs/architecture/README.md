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

02B agrega una sonda HTTP local en Interfaces y su registro en el provider existente. El controlador valida formato e invoca LocalCoverageProbe; la selección permanece en Domain y el acceso espacial en Infrastructure. El gate de entorno se ejecuta antes de resolver el puerto, incluso al reutilizar rutas cacheadas. No introduce dependencias entre módulos ni tablas o reglas operativas.

02C incorpora countries, markets y service_zones privadas de Marketplace, conforme a DP-028 y [ADR-006](decisions/ADR-006-marketplace-geographic-foundation.md). La migración crea una base vacía; mercados/zonas admiten solo draft y las zonas solo tipo fixture. No incorpora modelos ni puertos sin uso. Las sondas siguen usando sus fixtures inline y no consultan estos borradores; la activación real depende de DP-001 y de decisiones posteriores.

02D, aprobado mediante DP-029, agrega un diagnóstico persistido de consola. MarketPublicId valida la referencia pública en Domain; Application recibe mercado/punto explícitos, define puerto y resultado técnico y reutiliza la política de selección. Infrastructure consulta markets/service_zones propias en una sola sentencia, con alcance por mercado draft, zonas draft/fixture y geography nativa. El comando y el adaptador deniegan otros entornos antes de DI/SQL. No accede a datos privados de otros módulos ni concede privilegios; las sondas 02A/02B siguen usando sus fuentes inline. Véase [plan de 02D](../sprints/sprint-02/checkpoint-02d-plan.md).

02E, aprobado mediante DP-030, integra la lectura persistida HTTP con autorización de Identity. Application de Marketplace define LocalPersistedCoverageAccess sin depender de Laravel ni Identity. Su adaptador Infrastructure consume los contratos Application de Identity AuthenticatedActorDirectory, AuthorizationDirectory y PermissionService, con ResourceReference/ResourceContext/Scope como DTOs públicos del contrato. El dueño Identity realiza sus consultas privadas; Marketplace no lee ni hace joins sobre sus tablas.

Marketplace resuelve su recurso/scope técnicos fijos y compone un PermissionService dedicado con LocalPersistedCoverageResourceResolver. No reemplaza el binding de ResourceContextResolver de Identity ni modifica su sonda. El controlador usa actor de sesión verificado, autorización antes de validación/consulta, y el caso de uso geográfico de 02D sin cambios. El binding y la ruta solo existen en local/testing, con gate de ejecución para cachés reutilizadas. [Plan de 02E](../sprints/sprint-02/checkpoint-02e-plan.md).

ArchitectureTest permite exclusivamente los siete tipos públicos de ese contrato desde los dos archivos Infrastructure que los consumen. Sigue rechazando cualquier referencia a infraestructura privada de Identity o una dependencia entre módulos desde Domain/Application/Interfaces; Shared sigue sin importar módulos.

## Índice de detalle

- [Modelo de datos](data-model/README.md)
- [Seguridad](security/README.md)
- [No funcionales](non-functional/README.md)
- [Decisiones](decisions/README.md)
- [API](../api/README.md)
- [Eventos](../events/README.md)

## Creación transaccional de fixtures — 02F

DP-031 aprobada permite una mutación técnica local/testing con dos perfiles cerrados; [plan](../sprints/sprint-02/checkpoint-02f-plan.md) y [evidencia](../sprints/sprint-02/checkpoint-02f-evidence.md). Domain valida perfil y snapshot; Application define acceso/store/writer y el caso de uso. Interfaces valida JSON crudo y clave antes de invocarlo. Infrastructure crea el conjunto únicamente dentro de la transacción del puerto público Platform IdempotencyStore y resuelve la respuesta desde su registro propio inmutable.

Los adaptadores específicos consumen los contratos públicos Identity y Platform permitidos por la lista exacta de ArchitectureTest, sin sustituir resolvers anteriores ni consultar tablas privadas cruzadas. El permiso create se revalida antes del callback transaccional y antes de resolver el snapshot, incluidos replays. No se promete serialización frente a una futura administración concurrente de grants. Sin eventos/efectos externos asociados a este ejercicio, no se agrega outbox ni consumidor sin uso. Desarrollo conserva vacías las cuatro tablas Marketplace; casos positivos solo en bases temporales propias.

## Base comercial vacía — 02G

DP-032 y [ADR-007](decisions/ADR-007-marketplace-commercial-foundation.md) aprobados mediante «Apruebo» el 10 de octubre de 2026. Marketplace agrega merchants/branches privadas, vacías y solo draft. Una Branch pertenece a Merchant/Market mediante FK restrict del mismo módulo; Merchant puede tener varias sucursales/mercados. No se impone un mercado a la raíz comercial ni herencias/unicidades no especificadas.

El checkpoint usa exclusivamente migración, pruebas y documentación/ADR; no crea modelos, repositorios, interfaces o carpetas sin consumidor. API, composición Identity/Platform, scopes DP-026 y fixtures 02F conservan sus contratos. Las regresiones comprueban que las sondas no consultan/modifican filas comerciales y que 02F no las crea. [Plan](../sprints/sprint-02/checkpoint-02g-plan.md) y [evidencia](../sprints/sprint-02/checkpoint-02g-evidence.md). No hay onboarding, administración, activación ni operación real.

## Diagnóstico de contexto comercial — 02H

DP-033 aprobada mediante «Apruebo implementarlo» concreta una consola local/testing de lectura privada de Marketplace. Domain conserva MerchantPublicId/BranchPublicId y CommercialContext inmutables con MarketPublicId existente; Application define ProbeLocalCommercialContext y LocalCommercialContextSource; Infrastructure implementa una sentencia parametrizada sobre branches/merchants/markets; Interfaces valida tres ULID, aplica guard antes de DI y devuelve solo versión y matched/not_found.

La coincidencia exige las FK exactas y draft en las tres entidades, en un snapshot. Provider y adaptador deniegan fuera de local/testing; sin consultas a tablas privadas de otros módulos ni contratos comerciales nuevos para Catalog. No hay caché, escritura, migración, HTTP, grant o membresía nuevos. La relación no es autorización ni disponibilidad. [Plan](../sprints/sprint-02/checkpoint-02h-plan.md), [ADR-007](decisions/ADR-007-marketplace-commercial-foundation.md) y [evidencia](../sprints/sprint-02/checkpoint-02h-evidence.md).

## Alta y consulta de comercio draft — 02I

DP-034 aprobada agrega un consumidor concreto a Commerce: Domain conserva entrada/operación inmutables y valida nombres, IDs, punto y snapshot cerrado; Application coordina create/read y puertos de acceso, escritura e historial. Interfaces aplica autorización antes de formato y transforma respuestas; Infrastructure compone contratos públicos de Identity/Platform mediante adaptadores específicos y almacena únicamente tablas privadas Marketplace. ArchitectureTest permite exactamente esos contratos y conserva los límites de los módulos.

POST local/testing crea Merchant y primera Branch en Market draft existente mediante transacción idempotente compartida, bloqueo del mercado y reautorización. GET lee el snapshot cifrado original por actor creador, con permiso read independiente; no reconstruye desde perfiles actuales ni toca claims. Rutas, bindings y guards deniegan antes de sesión/DI/SQL fuera de local/testing, incluso con caché local reutilizada. Sin nuevas reglas en Shared, modelos sin uso, dependencias, contratos para Catalog o eventos/outbox sin consumidor. [Plan](../sprints/sprint-02/checkpoint-02i-plan.md), [ADR-007](decisions/ADR-007-marketplace-commercial-foundation.md) y [evidencia](../sprints/sprint-02/checkpoint-02i-evidence.md).
