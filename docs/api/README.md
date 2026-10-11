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

## Alta y consulta comercial ficticia — 02I / OpenAPI 1.6.0

POST /api/v1/marketplace/local-draft-commerces exige cookie, X-XSRF-TOKEN, Idempotency-Key ASCII !–~ de 1–255 y create vigente. JSON cerrado con merchant {legal_name, trade_name} y branch {market_public_id, name, longitude, latitude, timezone}. Nombres UTF-8 de 1–255 caracteres sin controles/no vacíos tras btrim ASCII, preservados exactamente; Market ULID draft existente; coordenadas números JSON finitos WGS84 validados antes de geography; timezone requerida Etc/UTC. Sin IDs nuevos/estado/fiscalidad/riesgo/actor desde cliente.

201 devuelve data.type=local_draft_commerce_operation, data.id=ULID de operación y attributes cerrados v1 {schema_version:1, merchant:{public_id,legal_name,trade_name,status:draft,version:1}, branch:{public_id,market_public_id,name,longitude,latitude,timezone:Etc/UTC,status:draft,version:1}}, más meta.correlation_id actual. GET /api/v1/marketplace/local-draft-commerces/{operationPublicId} devuelve el mismo data original 200 al creador con read independiente vigente, sin reconstruir perfiles actuales ni consultar claim. Otro actor/inexistente: 404 indistinguible tras acceso.

Claim, Merchant, Branch y diario cifrado append-only son atómicos; replay con create vigente conserva data/201 y TTL 24h desde primer claim, sin extender, purgar o reutilizar. JSON numérico equivalente/signed zero comparte fingerprint; nombres no se normalizan. 409 market_not_available/idempotency_mismatch/idempotency_expired; 401 sesión, 403 permiso, 419 CSRF, 415 medio, 422 formato, 429 tasa y 500 sanitizado. No-store/private, correlación actual y límites independientes create/read de 30/minuto por actor. Solo local/testing con guard antes de sesión/DI/SQL aun con caché local reutilizada. [Snapshot v1](schemas/marketplace-local-draft-commerce-operation.v1.json), [plan aprobado](../sprints/sprint-02/checkpoint-02i-plan.md) y [evidencia](../sprints/sprint-02/checkpoint-02i-evidence.md). Casos positivos solo en bases temporales propias; no habilita operación real ni MFA administrativa.

## Alta y consulta de catálogo ficticio — 03A / OpenAPI 1.7.0

POST /api/v1/catalog/local-draft-catalogs requiere sesión cookie, X-XSRF-TOKEN, Idempotency-Key ASCII !–~ de 1–255 y capacidad catalog.local.draft_catalog.create. JSON cerrado: merchant_public_id, catalog {name}, product {name, description, brand}; description/brand requeridos pero nullable. ULID mayúsculo estricto; nombres/brand UTF-8 1–255 sin controles y no blancos ASCII; description NULL o 0–4000 con LF permitido. Se conserva el texto exacto. Marketplace bloquea el comercio draft creado por el mismo actor en 02I; otra referencia devuelve 404 merchant_not_available.

201 devuelve data.type=local_draft_catalog_operation, data.id de operación y attributes v1 cerrados: schema_version=1, merchant_public_id, catalog {public_id,name,status:draft,version:1}, product {public_id,catalog_public_id,name,description,brand,status:draft,version:1}. Product_type no se expone. GET /api/v1/catalog/local-draft-catalogs/{operationPublicId} devuelve ese data original 200 al creador con catalog.local.draft_catalog.read vigente. Otra operación/actor devuelve 404 not_found; no reconstruye perfiles ni lee claims.

Claim, catálogo, producto y diario cifrado son atómicos. Replay autorizado conserva data/201 con correlación actual y TTL 24h sin extender/purgar/reusar; 409 idempotency_mismatch/idempotency_expired. 401 sesión, 403 permiso, 419 CSRF, 415 medio, 422 formato, 429 tasa, 500 genérico. No-store/private y límites independientes 30/minuto por actor. Solo local/testing, guard antes de sesión/puertos aun con caché reutilizada. [Snapshot v1](schemas/catalog-local-draft-operation.v1.json), [ADR-008](../architecture/decisions/ADR-008-catalog-local-draft-foundation.md), [plan](../sprints/sprint-03/checkpoint-03a-plan.md) y [evidencia](../sprints/sprint-03/checkpoint-03a-evidence.md). Desarrollo vacío; ningún producto vendible/publicable, frontend o dato real.

## Productos adicionales y contenido actual — 03B / OpenAPI 1.8.0

[Plan completo aprobado](../sprints/sprint-03/checkpoint-03b-plan.md) incorpora cuatro rutas locales, manteniendo 03A:

| Método/ruta bajo /api/v1/catalog | Capacidad | Resultado |
|---|---|---|
| GET /local-draft-profiles/{catalogPublicId} | catalog.local.draft_content.read | Perfil draft actual propio, ETag fuerte |
| GET /local-draft-profiles/{catalogPublicId}/products | catalog.local.draft_content.read | Página actual; limit/cursor y revisión |
| POST /local-draft-profiles/{catalogPublicId}/products | catalog.local.draft_product.create | Alta adicional 201/versionado/snapshot |
| GET /local-draft-product-operations/{operationPublicId} | catalog.local.draft_product.read | Snapshot original propio 200 |

POST requiere cookie/CSRF, clave ASCII exacta y un único If-Match fuerte "catalog:<ULID>:v<N>" ligado al catálogo (N=1–2147483647). JSON cerrado product {name,description,brand}, mismos límites/nulls/textos exactos 03A. Faltante 428, formato 422, versión distinta/máxima 409 version_conflict. Claim, comprobación/bloqueo Merchant por contrato, bloqueo Catalog propio de 03A, producto, incremento de revisión y diario cifrado se confirman juntos. Replay conserva 201/snapshot original con create vigente y TTL 24h; entrada/revisión distinta en misma clave produce mismatch. No revalida estado actual en replay.

GET perfil devuelve ETag/revisión actual; GET products admite solo limit decimal 1–50 (default 20) y cursor, orden keyset fijo interno. Incluye inicial/adicionales draft no borrados, una sentencia con revisión/página y limit+1. Meta cerrada correlation_id, catalog_version, next_cursor, has_more, filters {catalog_public_id,limit}. Cursor autenticado/cifrado, actor/catálogo/limit/revisión, expiración original 15 min; manipulación/contexto 422, vencido 409 cursor_expired, revisión cambiada 409 catalog_changed y reinicio. ID interno nunca en claro; cursor no concede acceso.

Historial adicional incluye catalog {public_id,name,status,version_before,version_after} y product original version 1; GET no lee perfiles/claim. Ownership/404 indistinguible, capacidades independientes, actor activo/DP-026 y reautorización; guard anterior a sesión/CSRF/DI/SQL en local/testing. No-store/private y correlación actual; buckets alta/contenido/historial 30/minuto independientes, perfil/colección comparten contenido. Otros errores 401/403/404/415/419/422/429/500 sanitizados.

Esquemas cerrados: [operación v1](schemas/catalog-local-draft-product-operation.v1.json), [perfil v1](schemas/catalog-local-draft-profile.v1.json), [página v1](schemas/catalog-local-draft-products-page.v1.json). No SKU/dinero/stock/listings/activación o datos reales; desarrollo vacío y positivos solo en bases temporales propias. [Evidencia](../sprints/sprint-03/checkpoint-03b-evidence.md).

## Edición de producto draft e historial — 03C / OpenAPI 1.9.0

[DP-037 aprobada](../architecture/decisions/pending-decisions.md#dp-037--edición-local-de-productos-draft) y [plan completo](../sprints/sprint-03/checkpoint-03c-plan.md), «aprobar y continuar» el 11 de octubre de 2026.

| Método y ruta bajo /api/v1/catalog | Capacidad | Éxito |
|---|---|---|
| PUT /local-draft-profiles/{catalogPublicId}/products/{productPublicId} | catalog.local.draft_product.update | 200 operación original de cambio o replay |
| GET /local-draft-product-change-operations/{operationPublicId} | catalog.local.draft_product_change.read | 200 snapshot original propio |

PUT reemplaza product {name,description,brand} requerido/cerrado, reglas de texto/null exactas de 03B; sin query, campos adicionales o versión de producto del cliente. Cookie/CSRF, Idempotency-Key e If-Match fuerte de catálogo. Referencias elegibles solo por autoría/procedencia 03A/03B y Merchant/02I público. 428 sin If-Match; 422 formato/entrada; 404 no disponible indistinguible; 409 no_changes/version_conflict/idempotency_mismatch/idempotency_expired. Versiones catálogo/producto incrementadas en uno e historial atómicos; replay sin read o reevaluación mutable.

[Snapshot de cambio v1](schemas/catalog-local-draft-product-change-operation.v1.json) con catálogo/revisiones y producto antes/después, cifrado/hash/forma/metadata verificados en servidor. GET histórico propio incluso tras cambios/borrado; sin IDs internos, actor/hashes/clave/ciphertext. Los contratos de alta y lecturas 03A/03B se conservan; los cursores viejos se invalidan con catalog_changed tras edición. Aritmética entre revisiones/igualdad de referencias se comprueba en dominio/DB; JSON Schema valida forma, tipos y límites.

Todas las respuestas/errores mantienen no-store/private y correlación actual; buckets separados 30/minuto por actor. Guard local/testing antes de sesión/CSRF/DI/SQL con caché reutilizada. Solo ficticio, sin grants en desarrollo o administración real; commit/push/CI se solicitan al cierre.

## Variantes draft y colección local — 03D — OpenAPI 1.10.0

[Plan completo](../sprints/sprint-03/checkpoint-03d-plan.md)/DP-038 aprobados mediante «si apruebo». Implementados y probados: POST y GET /api/v1/catalog/local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants y GET /api/v1/catalog/local-draft-variant-operations/{operationPublicId}. Primer POST y replay 201, GET 200; permisos create/read_current/read independientes.

Input variant cerrado con seis campos requeridos; attributes es un objeto acotado y {} no equivale a []. POST exige Idempotency-Key e If-Match de catálogo. SKU único por comercio, revisión antigua/máxima version_conflict, SKU repetido sku_conflict sin datos existentes. Colección usa limit/cursor y ETag de catálogo, ambas revisiones y meta de paginación; historial conserva snapshot original cifrado v1.

[OpenAPI 1.10.0](openapi.yaml) y esquemas v1 de [operación](schemas/catalog-local-draft-variant-operation.v1.json)/[página](schemas/catalog-local-draft-variants-page.v1.json) verificados, conservando todos los paths/componentes anteriores. OpenAPI 3.0 acota los valores del mapa y describe el patrón de claves; los esquemas JSON v1, servidor y DB también validan ese patrón. Reglas de textos/mapa/propiedad/versiones/errores/cursor/headers y pruebas en el plan; no bigint ni actor/secretos/ciphertext en respuesta, no-store/private y correlación actual. Sin precio/stock/listing, catálogo público o operación real.
