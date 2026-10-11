# Plan de Checkpoint 03C — Edición de productos draft e historial de cambios

Fecha: 11 de octubre de 2026. **Estado: plan completo aprobado mediante «aprobar y continuar» el 11 de octubre de 2026; implementado y validado localmente, migración vacía aplicada y 34 tablas anteriores preservadas. 329 pruebas/9445 aserciones correctas; commit/push/CI autorizados mediante «autorizado» el 11 de octubre de 2026; [publicado en main b88add660b33b8a94c612ae56fe6275272634c90 con CI completa correcta](checkpoint-03c-evidence.md#publicación-confirmada).**

## Base y fuente

Base publicada main/origin/main ad395112e4f261ea4a5450068957eab1cf76e8d2, [CI 03B completa correcta](https://github.com/nromero96/traepe/actions/runs/38115708725), run 38115708725/job 114400068772: ambos SHA exactos y cuatro etapas completed/success. 307 pruebas locales/8532 aserciones, Pint 277/PHPStan 8/contratos correctos. Al iniciar 03C había 34 tablas no-migrations y once tablas Marketplace/Catalog vacías; ocho servicios saludables. [Cierre de 03B](checkpoint-03b-evidence.md#publicación-confirmada).

Fuente: maestro v1.6 §§9, 25, 29, 32–33, 36–37, 47–50, 52–60, 64, 72, 78–80, 90 y 95; ADR-008 y DP-026/DP-035/DP-036. §37 distingue producto conceptual de variante vendible; §72 incluye editar catálogo; §58 exige control de versión y §49 conserva historia. No están concretados permisos, reemplazo de campos, operaciones sin cambios ni diario para edición del ejercicio local: [DP-037](../../architecture/decisions/pending-decisions.md#dp-037--edición-local-de-productos-draft) registra esta propuesta.

## Resultado propuesto y alcance de la aprobación

Editar nombre, descripción y marca de un producto conceptual draft propio, manteniendo su identidad y guardando antes/después inmutables. Las consultas actuales de 03B reflejan la edición y los snapshots originales 03A/03B permanecen originales. La revisión del catálogo cambia y obliga a reiniciar páginas anteriores.

Ejemplo: catálogo versión 2, producto versión 1. La edición usando If-Match de catálogo versión 2 deja catálogo versión 3/producto versión 2 y una operación histórica. Repetir igual clave/entrada devuelve esa misma operación sin editar otra vez. Otra solicitud basada en catálogo versión 2 recibe version_conflict. El historial de alta sigue mostrando el nombre original.

La aprobación conjunta recibida mediante «aprobar y continuar» comprende todas las reglas de este plan, implementación/pruebas y aplicación local aditiva de una tabla de diario vacía. Positivos exclusivamente en bases temporales propias; desarrollo vacío. Después de presentar el resultado terminado, commit/push/CI fueron autorizados mediante «autorizado» el 11 de octubre de 2026 conforme a AGENTS.md; [registro de autorización](checkpoint-03c-evidence.md#aprobación-y-publicación-autorizadas).

Solo ficticio local/testing. Sin borrar/restaurar, cambiar nombre de catálogo, reasignar producto/comercio, categorías/tipo/restricciones, SKU, precio/stock/listings, publicación, datos reales, activación, frontend/dependencias nuevas o administración sensible real. DP-001/DP-002/DP-007/DP-012 y demás decisiones productivas conservan responsables/fechas; no se concede una excepción al MFA del maestro §60.

## Rutas, entrada y respuestas

| Método y ruta bajo /api/v1/catalog | Resultado |
|---|---|
| PUT /local-draft-profiles/{catalogPublicId}/products/{productPublicId} | Reemplazo completo de los tres campos; 200 con operación de cambio original |
| GET /local-draft-product-change-operations/{operationPublicId} | Snapshot original de cambio propio; 200 |

Las rutas usan ULID canónicos y no aceptan query parameters. PUT requiere application/json, objeto cerrado product {name, description, brand}, con las tres propiedades presentes; description/brand nullable. No PATCH ni campos omitidos que impliquen conservar valores. IDs, catálogo/comercio, estado, tipo, versión de producto y cualquier propiedad adicional son rechazados. Se reutiliza DraftProductData de 03A/03B sin cambiar sus reglas.

Nombre y marca no NULL: UTF-8, 1–255 caracteres, no vacíos de espacios ASCII y sin C0/DEL. Descripción: NULL o UTF-8 de 0–4000 caracteres, solo LF permitido entre controles C0 y sin DEL. Valores exactos, sin trim/normalización; NULL difiere de cadena vacía y nombres duplicados siguen admitidos.

If-Match obligatorio, único y fuerte: "catalog:<ULID de la ruta>:v<N>", N decimal canónico entre 1 y 2147483647, mismo contrato de 03B. No se agrega una versión de producto enviada por cliente: la versión del catálogo protege toda la mutación y la de producto se obtiene del servidor bajo bloqueo. Falta: 428 precondition_required; formato/lista/weak/wildcard/ULID distinto/rango: 422 validation_failed. Revisión distinta o catálogo/producto al máximo integer: 409 version_conflict, sin escrituras parciales.

Para una primera ejecución válida se comparan estrictamente los tres campos actuales y solicitados. Si todos coinciden: 409 no_changes, sin incrementar versiones/timestamps ni insertar diario/claim. Se comprueba primero elegibilidad, revisión y límite de versiones; no_changes no revela recursos ajenos. Un replay devuelve el original antes de reevaluar perfiles o detectar igualdad actual.

Respuesta cerrada v1: data.type local_draft_product_change_operation, data.id ULID de operación; attributes {schema_version:1, merchant_public_id, catalog {public_id,name,status:draft,version_before,version_after}, product {public_id,catalog_public_id,before {name,description,brand,status:draft,version},after {name,description,brand,status:draft,version}}}. Las revisiones de catálogo y producto avanzan exactamente en uno; identidades/relación son iguales en ambos snapshots. Meta contiene correlation_id actual. No bigint, actor, hashes, claves, ciphertext o timestamps técnicos en público.

PUT y GET, errores incluidos, conservan no-store/private y correlation_id. PUT no devuelve un ETag del recurso de operación: la versión nueva del catálogo está en el snapshot y GET perfil 03B sigue emitiendo su ETag. Sin 304. Mantiene errores 401/403/419/415/422/404/409/428/429/500 sanitizados, sin entrada, SQL, campos anteriores, claves o tokens en logs. DTO/restauración cerrados y hash/ciphertext/metadata inválidos producen fallo genérico.

## Acceso y elegibilidad

Sesión cookie Identity en ambas rutas; CSRF para PUT. DP-026 completa: actor activo, deny por defecto, deny vigente prevalece, scope exacto, expiración UTC y recurso resuelto en servidor.

| Capacidad independiente | Scope platform técnico | Recurso técnico ULID |
|---|---|---|
| catalog.local.draft_product.update | 01ARZ3NDEKTSV4RRFFQ69G5FB2 | 01ARZ3NDEKTSV4RRFFQ69G5FBC |
| catalog.local.draft_product_change.read | 01ARZ3NDEKTSV4RRFFQ69G5FB2 | 01ARZ3NDEKTSV4RRFFQ69G5FBD |

Tipo de recurso igual a su capacidad. Update no concede lectura de contenido/historial o creación; read de cambios no concede update ni las lecturas de 03A/03B. Permisos anteriores siguen independientes. No herencia/comodines, grants automáticos, usuarios positivos en desarrollo ni sustitución del resolver global Identity. Límites separados de 30/minuto por actor para update e historial, sin compartir buckets anteriores.

Catalog prueba autoría del catálogo por su diario 03A, mismo actor/catalog_id/merchant_public_id. El catálogo debe seguir draft/no borrado y el producto pertenecer a ese catálogo, draft/no borrado, product_type NULL. Solo es editable el producto inicial referido en ese diario 03A o un producto adicional con diario 03B que coincida en actor/catálogo/comercio/producto. Inserciones manuales sin ese origen, producto de otro catálogo/actor, ausente, borrado o no draft reciben el mismo 404 not_found tras autorización.

La primera ejecución de PUT confirma y bloquea Merchant draft propio/02I mediante el contrato público OwnedLocalDraftMerchantV1 existente y su adaptador ya aprobado. Si el contrato no confirma elegibilidad, devuelve el mismo 404 not_found que otros recursos no disponibles, como en 03B. Sin SQL privado de Marketplace. Se comprueban elegibilidad y versiones de nuevo bajo bloqueo, sin aceptar autoridad de los IDs/versiones del cliente.

Update se reautoriza en el callback y tras obtener la referencia idempotente. Replay requiere update vigente, sin exigir read ni reevaluar perfiles actuales. GET historial requiere read vigente y actor propio del diario; operación ajena/ausente devuelve el mismo 404. Solo consulta el diario Catalog, sin reconstruir perfiles mutables o leer claims. No promete serialización con futura administración de grants.

Rutas, providers, adaptadores y workers quedan local/testing. Guard anterior a sesión/CSRF/DI/SQL incluso con caché local reutilizada en production/staging y procesos nuevos fuera del entorno permitido. No habilita administración real.

## Transacción, concurrencia e idempotencia

Idempotency-Key ASCII visible de 1–255 caracteres, reglas/TTL 24h desde primer claim de 03A/03B, sin extensión/purga/reuso. Scope exclusivo catalog.local_draft_product.update.v1:<FB2>:<FBC>; actor identity.user:<ULID>. Fingerprint cerrado schema_version=1, catalog_public_id, product_public_id, expected_catalog_version y product exacto. Orden de propiedades no influye; diferentes campos/NULL-vacío/producto/catálogo/revisión no equivalen.

Orden de bloqueo: claim Platform → Merchant público → Catalog → Product. Consulta preliminar Catalog permite conocer la referencia Merchant sin bloquearlo antes del orden aprobado. Callback reautoriza; Marketplace conserva su comprobación/lock; Catalog bloquea y revalida catálogo/propiedad/revisión, después el producto elegible y su origen. Serializa con el alta 03B, que usa claim → Merchant → Catalog. Una sola conexión/transacción PostgreSQL.

Cambia únicamente products.name/description/brand/version/updated_at y catalogs.version/updated_at, con incrementos exactos y timestamps UTC del servidor. IDs, relaciones, created_at, estado, tipo/deleted_at y nombre del catálogo se conservan. Inserta un diario cifrado del cambio y el claim. Fallo después de cualquiera de esas escrituras revierte todas, incluidos claim y timestamps; Marketplace y diarios previos permanecen intactos. no_changes también revierte la reserva de primera ejecución.

Igual clave/entrada/actor/scope: una edición y replay 200 original. Otra entrada/referencia/If-Match con esa clave: 409 idempotency_mismatch. Clave vencida: 409 idempotency_expired aunque el recurso ya cambió. Distintas claves con misma revisión compitiendo —mismo producto, productos distintos o alta 03B frente a edición— dejan un éxito y un version_conflict sin claim residual. Claves distintas sobre revisiones vigentes posteriores pueden editar de nuevo. Aislamiento entre actores/catálogos y prueba real con dos procesos.

El incremento del catálogo invalida cursores 03B anteriores mediante su 409 catalog_changed existente, incluso si se editó un producto de una página posterior. Reiniciar primera página muestra el contenido vigente. Orden keyset por id no cambia; las lecturas 03B conservan contrato/limit/TTL. GETs y rechazos no producen mutaciones.

## Persistencia e historial

Una migración posterior a 000008 agrega exclusivamente catalog_local_draft_product_changes privada de Catalog, inicialmente vacía, transaccional y forward-only. No modifica constraints/índices, filas o diarios existentes; no nuevos índices de products, porque no cambia la consulta/orden 03B.

Campos requeridos: id bigint; public_id ULID unique; catalog_id y product_id bigint FK restrict propias; merchant_public_id y actor_public_id ULID válidos; schema_version CHECK=1; catalog_version_before/after y product_version_before/after integer; key_hash/request_hash/response_hash SHA-256 lowercase válidos; correlation_id ULID válido; response_snapshot_ciphertext text no blanco; created_at timestamptz UTC/default del servidor. Sin updated_at/deleted_at o FK Identity.

CHECKs: ambas revisiones anteriores positivas y menores a 2147483647; posteriores=anteriores+1 comparadas como bigint para evitar overflow. Unique(catalog_id,catalog_version_after) y unique(product_id,product_version_after) dentro del diario de cambios. Producto no es unique por sí solo: puede editarse múltiples veces. Estos índices cubren FK por catálogo/producto; el índice public_id cubre GET exacto y filtro posterior de actor. Sin índices preventivos para búsquedas todavía inexistentes; nombres explícitos de constraints/índices menores a 64 caracteres.

FK compuestas (catalog_id,merchant_public_id) y (product_id,catalog_id) usan los unique propios existentes y evitan referencias cruzadas. La serialización de revisiones entre diarios de alta/cambio depende del lock/versión del agregado, no de un unique entre tablas distintas. Trigger propio rechaza UPDATE/DELETE; down rechaza destruir historia.

Snapshot v1 completo antes/después cifrado con Crypt existente, hash verificado y restauración estricta. Verifica forma, identidades/relaciones públicas, estados y versiones consistentes, además de merchant/pares de versiones contra metadata del diario; no lee perfiles actuales para GET/replay. Correlación inicial permanece en el diario; respuesta usa correlación actual. Platform guarda solo hashes y referencia pública. Sin evento publicado o efecto externo nuevo que requiera outbox; no inventar un consumidor. No redefine retención o política real de datos.

Después de la aplicación local aprobada: 34 tablas anteriores no-migrations y filas anteriores de migrations con conteos/hashes preservados, una tabla vacía y un registro de migración nuevos; doce tablas Marketplace/Catalog vacías. Segunda migrate sin cambios; sin fresh/reset/rollback ni borrado de volúmenes.

## Arquitectura, archivos y contrato

Domain/Application puros de Catalog: entrada, snapshot de cambio, políticas y casos de uso update/read con puertos propios. Reutiliza DraftProductData y DTOs actuales pertinentes sin modificar semántica 03A/03B. Infrastructure implementa persistencia/cifrado, acceso Identity e idempotencia Platform; Interfaces recibe formato e invoca casos de uso, sin reglas verticales en Shared.

ADR-008/ArchitectureTest documentarán exactamente tres consumidores adicionales: IdentityLocalDraftProductChangeAccess, LocalDraftProductChangeResourceResolver y PlatformLocalDraftProductChangeWriter, con los mismos tipos públicos Identity/Platform ya autorizados. Reutiliza MarketplaceLocalDraftMerchantSource/OwnedLocalDraftMerchantV1; sin contrato externo nuevo, acceso SQL privado cruzado, ciclo o dependencia inversa.

Al aprobar: archivos usados dentro de las cuatro capas Catalog, provider/rutas, migración nueva, pruebas Unit/Feature/Contract/Integration y workers necesarios; esquema cerrado catalog-local-draft-product-change-operation.v1.json y OpenAPI 1.9.0 aditivo; especificación catalog.md, ADR-008/decisiones, modelo/seguridad/arquitectura pertinentes, verificadores y evidencia/manifiesto Sprint 03. Se mantienen todos los contratos anteriores, endpoints/snapshots y dependencias instaladas. No se promete anticipadamente un conteo final de archivos/pruebas.

## Criterios de aceptación y validación

1. HTTP real OTP → fixture → comercio → catálogo 03A → alta 03B → edición del inicial y adicional → contenido actual/historial/replay; aumentos catálogo/producto, alta original intacta y cambios sucesivos consultables como originales.
2. Matriz independiente de permisos/cookies/CSRF/activo/bloqueado/default deny/deny wins/scope/recurso/expiry; reautorización, revocación y logout. Update-only puede mutar/repetir sin read; permisos anteriores no autorizan nuevas rutas.
3. Elegibilidad: catálogo/producto ajenos, cruzados, sin diario, no draft/borrados/type no NULL; Merchant no disponible por contrato público. 404 indistinguible y ningún claim residual.
4. Forma/ULID/textos/null-vacío/query/JSON/content type/If-Match/key, límites integer, comparación exacta sin cambios y orden de rechazos. 401/403/419/415/422/404/428/409/429/500, correlación/privacy y errores/logs sanitizados.
5. Atomicidad: fallos controlados tras UPDATE Product, incremento Catalog o diario revierten todos los hashes/conteos/campos/timestamps/claim. GETs/no_changes/rechazos no escriben. Misma clave y clave distinta compiten en procesos reales; incluir alta 03B contra update y productos distintos.
6. Idempotencia/TTL/aislamiento, entrada/versión distinta, replay tras ediciones/borrado/perfiles cambiados y permiso revocado. Diario cifrado/hash/restauración/metadata corruptos fallan cerrado; historial no reconstruye estados actuales.
7. Cursores 03B emitidos antes de edición rechazan catalog_changed; primera página reiniciada muestra nuevos valores/versiones, incluido cambio en otra página. Contratos/historial/carreras 03A/03B siguen correctos.
8. FK simples/compuestas, CHECK/unique/ULID/hashes/overflow, múltiples cambios del mismo producto, append-only/forward-only y consultas/índices efectivamente usados; límites modulares/guards con rutas cacheadas y nuevos procesos fuera de local/testing.
9. Pint, PHPStan nivel 8, validación oficial OpenAPI y snapshots positivos/negativos, Unit/Feature e Integration reales; verificadores HTTP/fundación/runtime y puertas negativas pertinentes.
10. Preservación de 34 tablas/filas migrations y tabla nueva vacía, segunda migrate idempotente, doce tablas Marketplace/Catalog vacías, ocho servicios saludables y sin bases temporales remanentes. Manifiesto exacto, UTF-8 sin BOM/LF, secretos ausentes, enlaces/master/diff correctos. Presentar resultado y solicitar commit/push/CI al terminar.

## Preparación y aprobación

[AGENTS.md](../../../AGENTS.md#alcance) exige: «Detén la implementación afectada si la decisión modifica datos, dinero, seguridad o comportamiento del negocio». DP-037 cambia datos editables/versiones/diario, seguridad (update/read/propiedad) y comportamiento (reemplazo, no_changes/concurrencia). Las aprobaciones 03A/03B no incluyen edición. Esta preparación registró todas las reglas antes de implementar; el usuario aprobó el plan completo mediante «aprobar y continuar», autorizando código, pruebas y migración local aditiva vacía. [Evidencia de preparación](checkpoint-03c-evidence.md).
