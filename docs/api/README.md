# Contrato API

**Origen:** secciones 58–63 del maestro v1.6.

## Convenciones obligatorias

- Prefijo `/api/v1`, JSON UTF-8 y fechas ISO 8601 UTC.
- Importes como enteros más moneda.
- Recursos por ID público; nunca bigint secuencial.
- Paginación cursor-based con `next_cursor` y `has_more`.
- Errores con `code` estable, mensaje humano, detalles y `correlation_id`.
- POST críticos con `Idempotency-Key`; concurrencia con `If-Match` o versión.

## Forma de respuesta

- Singular: `data` y `meta.correlation_id`.
- Colección: `data[]` y metadatos de cursor/filtros.
- 422: `validation_failed` y detalles por campo.
- 409: `invalid_transition`, `version_conflict` o `idempotency_mismatch`.
- 429: error y `Retry-After`.
- 202: `operation_id` y URL de estado.

## Superficie mínima prevista

Autenticación OTP; resolución de mercado; tiendas/catálogos; carrito/cotización; creación/consulta/cancelación de pedidos; aceptación/rechazo/preparación de tienda; ofertas/hitos de entrega; intervención administrativa.

Las rutas exactas del maestro se conservan como propuesta inicial, no como implementación. Antes de codificar se deberá producir un contrato versionado sin ampliar reglas de negocio.

## Contrato técnico implementado en 00D

[OpenAPI 3.0.3](openapi.yaml) describe exclusivamente readiness, firma técnica Reverb heredada de 00C y cookies CSRF de Sanctum. El archivo usa sintaxis JSON, subconjunto de YAML 1.2, para permitir su lectura sin instalar otro parser. Se valida contra el esquema oficial fijado y además contra rutas/respuestas reales.

GET `/api/v1/health/ready` devuelve `data: {type: health, id: ready, attributes: {status, checks}}` y `meta.correlation_id`. El identificador `ready` identifica el diagnóstico técnico, no un registro persistido. `attributes.status` conserva los estados y códigos HTTP de 00C. Los consumidores del payload anterior deben cambiar `status`/`checks` por `data.attributes.status`/`data.attributes.checks`.

Los errores REST usan `error: {code, message, details, correlation_id}`. Validación incluye `details: [{field, code, message}]`. Se conservan `Allow` en 405 y `Retry-After` en 429; 500 nunca devuelve mensaje de excepción, stack ni rutas, incluso con debug habilitado. Desde 00E, `X-Correlation-ID` conserva un ULID entrante válido en mayúsculas o genera uno nuevo cuando falta/es inválido. No refleja texto arbitrario. La correlación se propaga a logs, jobs y al evento técnico; no confiere autenticación ni autorización.

El helper de colección usa `data[]` y `meta: {correlation_id, next_cursor, has_more, filters}`. No existe un endpoint de colección ni una consulta comercial en este checkpoint.

POST `/api/v1/technical/broadcasting/auth` conserva la respuesta nativa `{auth}` de Pusher/Reverb probada y aprobada en 00C: es un adaptador de protocolo, no un recurso REST. Sus errores ahora usan el formato común. Conserva HTTP Basic local y el único canal `private-technical.v1`; no se convierte en un flujo de identidad. `/up` mantiene el diagnóstico nativo de Laravel fuera del contrato REST versionado.

Sanctum 4.3.3 registra la base cookie/CSRF y `auth:sanctum`, conforme al maestro §60 y la [documentación oficial](https://laravel.com/framework/docs/13.x/sanctum). GET `/sanctum/csrf-cookie` inicializa cookies sin autenticar. `SANCTUM_STATEFUL_DOMAINS` contiene solo los hosts locales configurados; ajustar los puertos si cambia `TRAEPE_HTTP_PORT`. No hay login, OTP, MFA, usuarios nuevos, emisión de tokens, migración de tokens ni permisos comerciales. No se incorpora `HasApiTokens` al modelo generado por Laravel.

```powershell
docker compose --env-file .env.docker exec api php tests/Support/lint-openapi.php
docker compose --env-file .env.docker exec api php artisan test
```

El lint usa el validador Draft-04 ya incluido en Composer 2.8.12 y el [esquema oficial](https://spec.openapis.org/oas/3.0/schema/2024-10-18), con prueba negativa de documento malformado. Véase [procedencia y licencia](schemas/README.md).

## Ampliación local de Marketplace — 02B

OpenAPI 1.3.0 incorpora GET `/api/v1/marketplace/local-coverage-probe`. Valida query `longitude`/`latitude`, números finitos dentro de [-180,180]/[-90,90]; invoca el ejercicio sintético de 02A. La lectura es pública exclusivamente local/testing, sin cookie, sesión o CSRF. La ruta operativa `/markets/resolve` continúa sin implementar.

200 devuelve `data: {type: local_coverage_probe, id: local-coverage-v1, attributes: {status, zone_id}}` y `meta.correlation_id`. selected usa un ULID fijo de fixture; outside/ambiguous llevan null. El ID del recurso es la versión del diagnóstico, no una entidad persistida. Las respuestas no reflejan coordenadas. Cache-Control no-store/private evita almacenar el diagnóstico. DP-027 conserva bordes/prioridad/empate aprobados y DP-001 sigue abierta.

Formato/rango inválido: 422; fallo interno: 500 sanitizado; límite técnico propio de 30/minuto por IP: 429 con Retry-After; métodos de mutación: 405 con Allow GET/HEAD. Fuera de local/testing: 404 antes de validación, throttling o PostgreSQL, incluso con una caché local reutilizada. No crea usuarios, grants, mercados o zonas operativas. Véase [evidencia de 02B](../sprints/sprint-02/checkpoint-02b-evidence.md).

## Diagnóstico de borradores persistidos autorizado — 02E

OpenAPI 1.4.0 agrega GET `/api/v1/marketplace/local-persisted-coverage-probe` exclusivamente local/testing. Query: market_public_id ULID mayúsculo válido, longitude [-180,180], latitude [-90,90], números finitos. Reutiliza 02D, con geography nativa sobre un mercado draft y sus zonas draft/fixture. Las sondas anteriores conservan su fuente inline y contrato.

La sesión cookie existente se autentica mediante web/auth:web; cabeceras Authorization no habilitan acceso. Capacidad exacta marketplace.local.persisted_coverage.read y scope/recurso técnicos fijos del [plan aprobado](../sprints/sprint-02/checkpoint-02e-plan.md), resueltos por servidor. Sin sesión devuelve 401; denegación DP-026 devuelve 403 antes de validar la entrada o consultar Marketplace. La capacidad autoriza el diagnóstico de cualquier mercado draft indicado, aislando su consulta, sin permisos operativos por mercado ni herencia.

200 devuelve `data: {type: local_persisted_coverage_probe, id: local-persisted-coverage-v1, attributes: {status, zone_id}}` y meta.correlation_id. market_not_found/outside/ambiguous llevan zone_id=null; selected devuelve el ULID de zona draft/fixture, sin elegibilidad de servicio. Formato/rango inválido: 422; límite propio 30/minuto por actor: 429 con Retry-After; fallo interno: 500 genérico. Respuestas locales no-store/private, sin coordenadas, SQL, permisos o bigint. Fuera del entorno permitido: 404, incluso con caché de rutas local reutilizada, antes de sesión/autorización/consulta. Los metadatos técnicos de sesión y limitador pueden actualizarse.

No hay API de asignación, usuarios/permisos nuevos en desarrollo, seeds ni activación. Pruebas positivas con sesión y datos reales únicamente en bases temporales propias. `/markets/resolve` sigue sin implementar y DP-001 abierta. [Evidencia de 02E](../sprints/sprint-02/checkpoint-02e-evidence.md).

## Webhooks

Validar tamaño, cuerpo crudo, timestamp, firma y cuenta; persistir inbox; responder 2xx a duplicados; procesar por job idempotente y enviar a dead-letter según política de reintentos.

## Integraciones lógicas

`PaymentGateway`, `RoutingProvider`, `NotificationProvider`, `StorageProvider`, `IdentityProvider` e `InvoicingProvider`. Los proveedores concretos son decisiones pendientes.

## Creación técnica local — 02F / OpenAPI 1.5.0

POST /api/v1/marketplace/local-draft-fixtures requiere cookie de sesión, X-XSRF-TOKEN e Idempotency-Key ASCII 1–255 sin espacios/control. Capacidad marketplace.local.draft_fixture.create independiente de read; scope/recurso técnicos fijos de servidor del [plan aprobado](../sprints/sprint-02/checkpoint-02f-plan.md). JSON cerrado: fixture_profile=synthetic-origin-a-v1 o synthetic-origin-b-v1. Autorización antes de validar y reservar. Medio no JSON: 415; formato/clave inválidos: 422 genérico; CSRF: 419; sin sesión con CSRF válido: 401; permiso denegado: 403; fuera de local/testing: 404 antes de sesión/CSRF/DI.

201 devuelve data.type=local_draft_fixture_operation, data.id=ULID de operación y attributes cerrados: profile_version, country_public_id, market_public_id y zone_public_ids (tres ULIDs nuevos en orden A/B/C); meta.correlation_id corresponde a la solicitud actual. Replay autorizado conserva exactamente data y 201, sin filas nuevas; distinto perfil válido bajo scope/actor/key: 409 idempotency_mismatch. Expiry 24h desde primer claim, sin extensión/reuso: 409 idempotency_expired. País ZZ incompatible: 409 fixture_country_conflict. No devuelve autor, bigint, hashes ni clave. No-store/private y límite propio 30/minuto por actor; 429 con Retry-After, 500 sanitizado.

[Snapshot v1](schemas/marketplace-local-draft-fixture-operation.v1.json), OpenAPI y [evidencia](../sprints/sprint-02/checkpoint-02f-evidence.md) documentan el contrato. Los cambios confirmados se auditan de forma append-only en la misma transacción; no existe evento/efecto externo. Desarrollo conserva tablas vacías y no tiene permisos positivos nuevos. Este ejercicio no define una API administrativa ni habilita el piloto real.
