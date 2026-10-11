# Plan de Checkpoint 03B — Productos adicionales y consulta del catálogo actual

Fecha: 10 de octubre de 2026. **Estado: plan completo aprobado mediante «Aprobar plan completo 03B (recomendado)»; implementado y validado localmente, con migración vacía aplicada y datos anteriores preservados. Commit/push/CI autorizados mediante «Autorizado» el 11 de octubre de 2026; [publicado en main ad395112e4f261ea4a5450068957eab1cf76e8d2 con CI completa correcta](checkpoint-03b-evidence.md#publicación-confirmada).**

Base publicada main/origin/main: 0690cc0ec63493a163652ec68db52c184f700e26, [03A](checkpoint-03a-evidence.md) y [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38109718961). Run 38109718961/job 114382575818 verificados sobre ese SHA, incluidos los cuatro pasos obligatorios. El usuario solicitó continuar el proyecto después de ingresar a PostgreSQL con pgAdmin.

Fuente: maestro v1.6 §§9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; ADR-002/ADR-008, DP-026/DP-035. §37 distingue catálogo del comercio, producto conceptual, variante vendible y publicación por sucursal. §58 requiere cursores y control de versión para mutaciones concurrentes. El maestro no concreta la ampliación local de 03A: [DP-036](../../architecture/decisions/pending-decisions.md#dp-036--productos-adicionales-y-consulta-del-catálogo-actual) registra las reglas del bloque, aprobadas explícitamente mediante «Aprobar plan completo 03B (recomendado)».

## Resultado del bloque

Agregar productos conceptuales a un catálogo draft existente creado por el mismo actor en 03A, consultar el perfil actual del catálogo, recorrer sus productos y consultar el snapshot original de cada alta adicional. Incluye API, dominio/casos de uso, persistencia, integridad, autorización, idempotencia, control de concurrencia, auditoría y pruebas HTTP/PostgreSQL reales.

Ejemplo: 03A crea catálogo versión 1 con un producto. 03B agrega otro producto usando esa versión; el catálogo pasa a versión 2, contiene ambos productos y conserva intacto el historial 03A. Repetir la misma solicitud idempotente devuelve la misma alta; otra solicitud basada en versión 1 rechaza el conflicto y debe consultar la versión actual.

La aprobación conjunta recibida abarca las reglas completas de este documento, implementación, pruebas y aplicación local aditiva de una tabla de diario vacía y un índice usado por la paginación. Positivos exclusivamente en bases temporales propias. Después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Autorizado», conforme a AGENTS.md; [registro de publicación](checkpoint-03b-evidence.md#aprobación-y-publicación-autorizadas).

Solo ficticio local/testing, sin nuevos usuarios/grants o registros positivos en desarrollo. Conserva los endpoints de alta/consulta existentes de Marketplace/Catalog y sus snapshots sin cambios. Sin editar/borrar/restaurar productos, categorías, variantes/SKU, restricciones operativas, precios, inventario, listings, publicación, discovery público, frontend, dependencias nuevas, activación o datos reales. DP-001/DP-002/DP-007/DP-012 y demás decisiones productivas mantienen responsable/fecha; administración sensible real sigue requiriendo MFA.

## API propuesta

Prefijo /api/v1/catalog. Rutas nuevas separadas de local-draft-catalogs/{operationPublicId}, que sigue siendo historial 03A:

| Método y ruta | Resultado |
|---|---|
| GET /local-draft-profiles/{catalogPublicId} | Perfil actual del catálogo propio draft, 200 |
| GET /local-draft-profiles/{catalogPublicId}/products | Colección paginada de productos actuales, 200 |
| POST /local-draft-profiles/{catalogPublicId}/products | Alta de producto adicional y operación, 201 |
| GET /local-draft-product-operations/{operationPublicId} | Snapshot original de alta adicional propia, 200 |

ULIDs mayúsculos estrictos como 03A. GET del perfil/operación no admite query; POST no admite query. GET products admite exclusivamente limit y cursor: limit decimal canónico entre 1 y 50, default 20; cursor opaco no vacío de hasta 4096 caracteres. No filtros libres, offset, búsqueda o orden del cliente.

POST requiere JSON cerrado con exactamente product: {name, description, brand}; todas las propiedades requeridas, description/brand nullable. Reutiliza exactamente los límites y preservación de texto aprobados en ADR-008: nombres/brand UTF-8 1–255, no blancos ASCII, sin C0/DEL; descripción NULL o 0–4000 caracteres, permite LF y rechaza los demás C0/DEL. No trim, normalización, unicidad de nombre ni interpretación HTML. Servidor fija ULID, status draft, version 1, product_type NULL y deleted_at NULL. No recibe merchant/actor, nuevos IDs, tipo, estado, versión de producto ni configuración.

If-Match obligatorio en POST: un único ETag fuerte exacto con forma "catalog:<catalogPublicId>:v<N>", N decimal canónico entre 1 y 2147483647, ligado al ULID de la ruta. Lo entrega GET del perfil mediante ETag. No acepta *, listas, ETag débil, signo o espacios internos. Falta: 428 precondition_required; formato/referencia inválidos: 422 validation_failed. Se compara bajo bloqueo dentro del callback de primera ejecución. Versión distinta o catálogo en el máximo integer: 409 version_conflict sin escrituras. Un alta exitosa incrementa versión exactamente en uno y actualiza updated_at UTC; created_at/nombre/status/comercio permanecen. No mutación del primer producto ni del diario 03A.

GET perfil: data.type=local_draft_catalog_profile, data.id=catalog ULID, attributes cerrados v1 {schema_version:1, merchant_public_id, name, status:draft, version:<entero positivo>}; meta.correlation_id actual. ETag no habilita 304/caché: todas las respuestas no-store/private.

Colección: data de recursos type=local_draft_product, id=product ULID y attributes v1 {schema_version:1, catalog_public_id, name, description, brand, status:draft, version:<entero positivo>}. Meta cerrada: correlation_id, catalog_version, next_cursor (string o NULL), has_more boolean y filters {catalog_public_id, limit}. Incluye el producto inicial y los adicionales con status draft/deleted_at NULL; no muestra IDs internos, product_type, deleted_at, actor o marcas temporales.

POST/GET operación: data.type=local_draft_product_operation, data.id=operation ULID; attributes cerrados v1 {schema_version:1, merchant_public_id, catalog:{public_id,name,status:draft,version_before,version_after}, product:{public_id,catalog_public_id,name,description,brand,status:draft,version:1}}. GET devuelve siempre este resultado original, aunque luego cambien perfiles o deleted_at. Conserva los contratos existentes y agrega OpenAPI 1.8.0, snapshot de operación adicional v1 y esquemas de perfil/colección.

Errores genéricos estables: 401 sesión, 403 capacidad, 419 CSRF, 415 medio, 422 validation_failed, 404 not_found, 428 precondition_required, 409 version_conflict/idempotency_mismatch/idempotency_expired/cursor_expired/catalog_changed, 429 tasa, 500 sanitizado. Catálogo ausente/ajeno/no creado en 03A/borrado/no draft y Merchant no elegible en una nueva alta dan el mismo 404 not_found. Operación ajena/ausente igualmente 404 tras permiso. No se devuelve input, SQL, hash, clave, token o condición privada en errores/logs.

## Autorización y propiedad

Sesión cookie Identity en todas las rutas; CSRF en POST. Capacidades nuevas independientes, scope platform técnico existente 01ARZ3NDEKTSV4RRFFQ69G5FB2 y contextos exclusivos de servidor:

| Capacidad | Recurso técnico ULID | Uso |
|---|---|---|
| catalog.local.draft_product.create | 01ARZ3NDEKTSV4RRFFQ69G5FB9 | POST y replay |
| catalog.local.draft_content.read | 01ARZ3NDEKTSV4RRFFQ69G5FBA | Perfil y colección actuales |
| catalog.local.draft_product.read | 01ARZ3NDEKTSV4RRFFQ69G5FBB | GET operación adicional |

Tipo de recurso exactamente igual a su capacidad. DP-026 íntegra: activo, deny por defecto, deny vigente prevalece, scope exacto, expiración UTC y contexto resuelto por servidor. Create no concede ninguna lectura; read de contenido no concede historial o creación; read histórico no concede contenido actual. No reutiliza ni amplía las capacidades 03A, no comodines/herencia, grants automáticos o reemplazo del resolver global Identity.

Para contenido/alta, Catalog acredita autoría por catalog_local_draft_operations de 03A, actor público exacto y mismo catalog_id/merchant_public_id. Catálogo insertado manualmente o ajeno no es elegible. Perfil/colección solo de Catalog draft/deleted_at NULL; ownership técnico no implica membresía comercial. Lecturas actuales no invocan Marketplace, Identity SQL privado ni Platform claims: la capacidad se verifica por contrato Identity y las filas se leen solo en Catalog. No prometen elegibilidad comercial del Merchant.

Primera ejecución de POST requiere adicionalmente Merchant draft creado por el mismo actor en 02I, mediante el contrato público existente OwnedLocalDraftMerchantV1 de Marketplace. Marketplace posee su comprobación y bloqueo; Catalog no consulta sus tablas. Reutiliza el adaptador público aprobado sin ampliar el contrato.

Reautoriza el mismo actor/capacidad en el callback antes de la escritura y después de obtener la referencia idempotente. GET historial filtra por actor del diario adicional y read vigente. Replay requiere create vigente y conserva el snapshot; no exige read ni reevalúa Merchant/catálogo/versiones actuales. No promete serialización con futura administración de grants.

Guard local/testing anterior a sesión/CSRF/DI/SQL, aun con caché local en production/staging; providers/adaptadores y cursores también protegidos. Rate limits 30/minuto por actor: create, contenido (perfil+colección comparten bucket) e historial, separados entre sí y de los buckets 03A/Marketplace/OTP.

## Paginación y consistencia

Orden fijo por products.id ascendente, keyset interno; el bigint nunca sale del backend en claro. Consulta limit+1 para has_more; next_cursor solo si hay otra página. Una única sentencia PostgreSQL relaciona catálogo elegible, revisión y página bajo el mismo snapshot, incluido catálogo sin productos. No carga todo el catálogo ni produce lecturas mezcladas de revisión/página. GET perfil usa asimismo una única consulta a Catalog. Estas lecturas no modifican tablas/claims/auditoría.

Cursor autenticado y cifrado usando Crypt existente, en envoltura URL-safe de forma estricta y sin dependencia adicional. Payload cerrado v1: purpose fijo, actor público, catálogo público, catalog_version, limit, último bigint interno y expires_at UTC. Expira 15 minutos después de la primera página; páginas posteriores conservan expiración, no la extienden. Corrupto/contexto/actor/catálogo/limit distinto o forma inválida: 422 genérico sin mostrar payload. Vencido: 409 cursor_expired. Revisión del catálogo distinta: 409 catalog_changed; cliente reinicia desde primera página. Reautoriza cada solicitud; cursor no concede acceso.

Incrementar versión al agregar productos invalida cursores anteriores y evita omitir/duplicar altas entre páginas sin prometer una colección congelada. No se generan tokens para catálogo vacío/última página. Perfil y primera página son solicitudes independientes y pueden ver revisiones diferentes; meta.catalog_version identifica cada consulta. Futuras ediciones/borrados deberán incrementar la versión del agregado para respetar este contrato.

## Transacción, idempotencia y concurrencia

Idempotency-Key y TTL 24h idénticos a 03A, sin purga/extensión/reuso. Scope nuevo catalog.local_draft_product.create.v1:<FB2>:<FB9>; actor identity.user:<ULID>. Fingerprint cerrado schema_version=1 con catalog_public_id, expected_catalog_version de If-Match y product exacto. NULL/vacío y versiones diferentes no equivalen; orden de propiedades no influye.

Platform bloquea claim; el callback reautoriza, encuentra la referencia propia de Catalog, bloquea Merchant vía contrato público y luego bloquea/revalida Catalog propio con FOR UPDATE. Orden de bloqueo: claim → Merchant → Catalog; no bloquea catálogo antes del Merchant. Lee versión/deleted_at/status/propiedad otra vez bajo bloqueo. Inserta Product, incrementa Catalog con control de versión y agrega diario en la misma conexión/transacción. Falla de cualquiera revierte claim/producto/versión/diario; Marketplace permanece intacto.

Igual clave/entrada/actor/alcance: una operación y replay 201 original, aun tras cambiar versión. Distinta entrada o If-Match con la misma clave: 409 idempotency_mismatch. Claves diferentes con la misma versión esperada compitiendo: una confirma y otra recibe version_conflict sin claim residual. Distintas claves basadas sucesivamente en revisión vigente pueden crear nombres iguales. Catálogos distintos permanecen aislados; expiración conserva rechazo 409 idempotency_expired aunque perfil ya cambió.

## Migración e historial propuestos

Una migración aditiva/transaccional/forward-only agrega catalog_local_draft_product_operations privada de Catalog y un índice products(catalog_id,id) parcial WHERE status='draft' AND deleted_at IS NULL usado por keyset. No renombra/modifica constraints, datos o diarios existentes. Desarrollo conserva catalogs/products/diario 03A vacíos y agrega una cuarta tabla Catalog vacía.

Diario nuevo: id bigint interno; public_id ULID válido/unique; catalog_id y product_id bigint FK restrict a tablas propias; product_id unique; merchant_public_id/actor_public_id ULID válidos; schema_version CHECK=1; catalog_version_before positivo y menor al máximo integer; catalog_version_after=before+1; key_hash/request_hash/response_hash SHA-256 válidos; correlation_id ULID válido; response_snapshot_ciphertext text no vacío; created_at timestamptz requerido/default UTC. Sin updated_at/deleted_at o FK a Identity.

FK compuestas existentes catálogo/comercio y producto/catálogo garantizan que el historial no pueda referir relaciones cruzadas. Unique(catalog_id,catalog_version_after) evita dos altas confirmadas con la misma revisión; índices actor/created_at y referencias necesarios. El UNIQUE producto cubre su FK; el UNIQUE compuesto cubre búsquedas por catálogo. Trigger propio rechaza UPDATE/DELETE; down rechaza destrucción. No copia perfiles comerciales/Identity ni añade campos sin consumidor.

Snapshot v1 completo cifrado con Crypt, hash verificado y restauración cerrada; correlación original inmutable y actual en respuesta. Platform conserva hashes/referencia pública exclusivamente. No eventos públicos/efectos externos nuevos: no outbox sin consumidor. No redefine retención o tratamiento de datos reales.

## Arquitectura y archivos previstos

Catalog conserva sus cuatro capas; Domain/Application puros con objetos y puertos propios. Nuevos casos de uso: agregar producto, leer operación adicional, leer perfil y listar productos. Infraestructura implementa Identity, Platform, persistencia y cursor; reutiliza el puente Marketplace existente. Interfaces valida formato y transforma respuestas mediante controladores delgados. Shared permanece intacto.

ADR-008 y ArchitectureTest se ampliarán explícitamente para los consumidores concretos de los mismos contratos públicos ya aprobados; no módulos/dependencias inversas nuevas, joins privados cruzados ni resolver global reemplazado. Nuevos objetos de entrada reutilizan reglas de texto existentes sin cambiar 03A.

Al aprobar: código utilizado bajo apps/api/app/Modules/Catalog, provider/rutas, una migración posterior a 000007, pruebas Unit/Feature/Contract/Integration y workers de concurrencia; contratos en docs/api, especificación catalog.md, ADR-008/decisiones, modelo/seguridad/arquitectura cuando corresponda, verificadores y evidencia/manifiesto Sprint 03. No archivos ajenos. No se asigna anticipadamente un conteo de archivos o pruebas como resultado validado.

## Criterios de aceptación y validación

1. HTTP real OTP → fixture → comercio → catálogo 03A → perfil/página → producto adicional → replay/historial; catálogo pasa 1→2, ambos productos son visibles y snapshot original 03A conserva versión 1.
2. Matriz de permisos independiente: sesión/CSRF, actor activo/bloqueado, default deny/deny wins/scope/recurso/expiry; reautorización, ownership, otras referencias e indistinguibilidad. Crear no concede leer, lectura actual no concede historial.
3. Límites y rechazos: ULID/JSON/texto/null-vacío/If-Match/query/limit/cursor; 428,422,404,409,429; sanitización de errores/logs y no exposición de bigint/secretos/tokens. Guards con rutas cacheadas y procesos nuevos fuera de local/testing.
4. Páginas vacías/completas/últimas, más de 50 productos, orden y ausencia de duplicados; cifrado/tamper/contexto/actor/catálogo/limit/expiry, cambio de revisión entre páginas y snapshot de una sentencia. GET no produce escrituras.
5. Idempotencia/TTL/aislamiento, replay de versión antigua y perfil borrado, distintos If-Match, claves diferentes, rollback parcial tras insert/increment/diario, revocación y carreras reales con dos procesos para misma clave y claves distintas.
6. Constraints/FK/índices/versiones máximas, append-only/cifrado/hash/forma cerrada/forward-only, diarios 03A/03B intactos y límites modulares sin SQL privado ajeno.
7. Pint, PHPStan nivel 8, lint oficial OpenAPI/snapshots positivos/negativos, suites Unit/Feature e Integration PostgreSQL/PostGIS reales, verificadores HTTP/fundación/runtime y puertas negativas pertinentes.
8. Antes/después de aplicación local aprobada: conteos/hashes de las 33 tablas no-migration anteriores y registros de migración preservados; una tabla vacía nueva y un registro de migración, segunda migrate sin cambios. Once tablas Marketplace/Catalog vacías, ocho servicios saludables y ninguna base temporal remanente. Sin fresh/reset/rollback/volúmenes eliminados.
9. Manifiesto exacto, UTF-8 sin BOM/LF, secretos ausentes, links/master/diff correctos. Resultado reviewable incluye archivos, verificaciones ejecutadas y riesgos pendientes. No commit/push hasta autorización de cierre independiente.

## Preparación y decisión requerida

[La evidencia](checkpoint-03b-evidence.md) conserva la preparación, aprobación explícita y resultados de implementación/validación. La preparación inicial fue exclusivamente documental; el código se inició después de resolver DP-036.

AGENTS.md dispone: «Detén la implementación afectada si la decisión modifica datos, dinero, seguridad o comportamiento del negocio». DP-036 afecta datos (productos/versión/diario), seguridad (capacidades/propiedad) y comportamiento (concurrencia/paginación). El usuario aprobó explícitamente el plan completo mediante «Aprobar plan completo 03B (recomendado)» antes de implementar. Esa aprobación autorizó implementación/pruebas y aplicación local aditiva vacía; la publicación recibió autorización separada mediante «Autorizado» después del resultado terminado.
