# Plan de Checkpoint 03D — Variantes draft y consulta por producto

Fecha: 11 de octubre de 2026. **Estado: plan completo aprobado mediante «si apruebo»; DP-038 resuelta antes del código. Implementado y validado: 355 pruebas/11260 aserciones. Migración local de dos tablas vacías aplicada y 35 tablas anteriores preservadas. Commit/push/CI autorizados mediante «Autorizado y continuar»; publicación en curso.**

## Base y fuente

Base publicada main/HEAD/origin/main/remote b88add660b33b8a94c612ae56fe6275272634c90, [cierre de 03C](checkpoint-03c-evidence.md#publicación-confirmada). [Foundation CI 38120914021](https://github.com/nromero96/traepe/actions/runs/38120914021), job 114415279745, run/job con SHA exacto y cuatro etapas completed/success: build/migración, calidad/integración, reinicio/persistencia y limpieza. 329 pruebas locales/9445 aserciones, Pint 296/PHPStan 8/contratos correctos; 44 archivos publicados. Al preparar 03D: 35 tablas no-migrations, doce tablas Marketplace/Catalog vacías, ocho servicios saludables y working tree inicialmente limpio.

Fuente: maestro v1.6 §§9, 25, 32–33, 36–37, 49–50, 52–60, 64, 72, 78–79 y 90; ADR-008, DP-026 y DP-035/DP-036/DP-037. §37 distingue producto conceptual, variante/SKU y listing por sucursal; §90 exige SKU único en alcance pero no determina ese alcance ni las reglas de variantes del ejercicio local. [DP-038](../../architecture/decisions/pending-decisions.md#dp-038--variantes-draft-locales) registra conjuntamente los vacíos de datos, permisos, revisión, lectura e integridad. El maestro conserva precedencia y su hash original.

## Resultado y aprobación recibida

Crear variantes draft de un producto conceptual propio de 03A/03B, recorrer sus variantes actuales y consultar el snapshot original de cada alta. Ejemplo: producto conceptual “Artículo ficticio”, variante “Presentación pequeña”, SKU DEMO-001, size “250”, unit “unidad ficticia”, barcode NULL y attributes {"color":"azul"}. Las etiquetas descriptivas no producen conversiones, cantidades, importes ni reglas de venta.

La aprobación inicial mediante «si apruebo» abarcó todas las reglas de este plan, código en las cuatro capas, pruebas reales, contratos y aplicación local aditiva de dos tablas inicialmente vacías. Positivos exclusivamente en bases temporales propias; desarrollo permanece vacío, sin usuarios/grants nuevos. Después de presentar el resultado terminado conforme a AGENTS.md, el usuario autorizó commit/push/CI mediante «Autorizado y continuar» el 11 de octubre de 2026; publicación en curso.

Solo ficticio local/testing. No edición/borrado/restauración de variantes, reasignación de catálogo/producto, categorías/tipos/restricciones, opciones/modificadores, medios/combos, precios, stock, listings por sucursal, disponibilidad, venta, discovery público, activación, datos reales, frontend o dependencias nuevas. El precio base y demás campos operativos de T-13/§90 se implementarán en bloques posteriores con sus decisiones; no se considera completada la administración productiva de variantes. DP-001/DP-002/DP-007/DP-012 y restantes decisiones productivas mantienen responsable/fecha. No exime del MFA de administración sensible real.

## API e input

Prefijo /api/v1/catalog; tres rutas nuevas:

| Método y ruta | Resultado |
|---|---|
| POST /local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants | Alta draft y operación original, 201; replay conserva 201 |
| GET /local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants | Colección actual paginada, 200, incluso vacía |
| GET /local-draft-variant-operations/{operationPublicId} | Snapshot original de alta propio, 200 |

ULIDs canónicos mayúsculos estrictos. POST e historial no admiten query. La colección admite únicamente limit y cursor: limit decimal canónico 1–50, default 20; cursor opaco no vacío, hasta 4096 caracteres. No filtros libres, offset, búsqueda ni orden elegido por el cliente.

POST JSON cerrado con una sola propiedad variant y sus seis propiedades requeridas:

| Campo | Regla aprobada |
|---|---|
| name | String UTF-8 1–255 caracteres, no blanco tras btrim de espacio ASCII, sin C0/DEL; nombres repetidos permitidos |
| sku | String ASCII exacta con patrón ^[A-Z0-9][A-Z0-9._-]{0,63}$; mayúsculas obligatorias, sin conversión ni trim |
| size | NULL o string UTF-8 1–255, mismas reglas de texto de name; etiqueta opaca, sin interpretación numérica |
| unit | String UTF-8 1–64, mismas reglas de texto de name; etiqueta opaca, sin catálogo global de unidades ni conversión |
| barcode | NULL o string ASCII visible !–~ de 1–64; exacta, sin trim, unicidad ni validación de estándar/checksum |
| attributes | Objeto JSON de 0–20 pares; claves ASCII ^[a-z][a-z0-9_]{0,63}$ y valores string UTF-8 1–255, no blancos ASCII y sin C0/DEL |

Todas las claves son obligatorias, incluidos los valores nullable. No null, array, objetos anidados, números, booleanos ni valores vacíos dentro de attributes. El objeto vacío debe conservar {} en API/snapshot/JSONB, y [] se rechaza como input. Interfaces distingue objetos/listas antes de transformar al DTO; Domain representa un mapa de strings ordenado por clave al serializar/fingerprinting. El orden de propiedades del JSON y del mapa no altera la identidad de una solicitud; los valores, Unicode y NULL se preservan exactamente. No normalización de textos. Los atributos son descriptivos: ninguna clave determina precio, permiso, disponibilidad o regla operativa.

SKU único por merchant_public_id en todos sus catálogos/productos/variantes, incluidos soft-deleted; no se reutiliza. Otro comercio puede usar igual SKU. Un conflicto se responde 409 sku_conflict sin devolver identificadores, perfiles ni datos de la variante existente. Barcode, name, size, unit y combinaciones de atributos pueden repetirse con un SKU diferente.

Servidor fija public_id ULID, status draft, version 1, deleted_at NULL y timestamps UTC. No acepta merchant/actor/IDs, status, version, deleted_at, precios, cantidades de inventario ni propiedades extra.

## Versiones, errores y respuestas

POST exige un solo If-Match fuerte exacto "catalog:<catalogPublicId>:v<N>", N decimal canónico 1–2147483647, ligado al ULID de ruta; misma regla de 03B/03C. Sin header: 428 precondition_required. Header/formato/referencia inválidos: 422 validation_failed. Comparación bajo bloqueo de primera ejecución: catálogo distinto/máximo o producto máximo integer produce 409 version_conflict. Después se verifica SKU; así, una revisión antigua prevalece frente a un SKU repetido.

Una alta aumenta exactamente en uno las versiones de Catalog y Product y sus updated_at UTC; la versión de Product pasa a representar también cambios de su colección de variantes. Conserva textos, identidad, relaciones, estados, created_at y deleted_at de ambos; la variante inicia en 1. Las ediciones 03C siguen aumentando ambas versiones y rechazando no_changes según sus tres campos. La colección 03B de productos reflejará la nueva revisión del producto sin agregar campos; sus cursores anteriores reciben catalog_changed. Las operaciones 03A/03B/03C conservan su resultado original.

POST/historial: data.type=local_draft_variant_operation, id=operation ULID. Attributes cerrados v1:

- schema_version:1 y merchant_public_id.
- catalog:{public_id,name,status:draft,version_before,version_after}.
- product:{public_id,catalog_public_id,name,description,brand,status:draft,version_before,version_after}.
- variant:{public_id,product_public_id,name,sku,size,unit,barcode,attributes,status:draft,version:1}.

Los dos pares de versiones son positivos y consecutivos, y todas las referencias deben coincidir. El snapshot del producto incluye sus textos vigentes al crear la variante; no se reconstruye después. Meta singular contiene solo correlation_id actual. POST/historial no emiten ETag de estado actual.

Colección: data es array de recursos type=local_draft_variant, id=variant ULID y attributes cerrados v1 {schema_version:1,catalog_public_id,product_public_id,name,sku,size,unit,barcode,attributes,status:draft,version:1}. Meta cerrada {correlation_id,catalog_version,product_version,next_cursor,has_more,filters:{catalog_public_id,product_public_id,limit}}. next_cursor string o NULL, has_more boolean. ETag expresa la revisión actual del catálogo con la misma forma anterior; no implementa 304. No expone bigint, actor, hashes, claves, ciphertext, tiempos técnicos o deleted_at.

Errores: 401 unauthenticated, 403 forbidden, 419 csrf_token_mismatch, 415 medio no JSON, 422 validation_failed, 428 precondition_required, 404 not_found, 409 version_conflict/sku_conflict/idempotency_mismatch/idempotency_expired/cursor_expired/catalog_changed, 429 con Retry-After y 500 internal_error sanitizado. Rechazos conservan no-store/private y correlación; no devuelven input, SQL, SKU existente, claves o estado privado. Se mantienen las convenciones de error y privacy de 03C.

OpenAPI 1.10.0 implementada, esquema v1 de operación y esquema v1 de página con formas cerradas, salvo el mapa attributes expresamente acotado. Esquemas estructurales y pruebas de Domain/DB cubren referencias y aritmética de versiones. Ningún path/schema previo cambia de significado.

## Permisos y propiedad

Sesión cookie Identity en las tres rutas; CSRF en POST. Tres capacidades nuevas independientes, scope técnico platform 01ARZ3NDEKTSV4RRFFQ69G5FB2 y recursos definidos exclusivamente por servidor:

| Capacidad | Recurso técnico ULID | Uso |
|---|---|---|
| catalog.local.draft_variant.create | 01ARZ3NDEKTSV4RRFFQ69G5FBE | POST y replay |
| catalog.local.draft_variant.read_current | 01ARZ3NDEKTSV4RRFFQ69G5FBF | Colección actual |
| catalog.local.draft_variant.read | 01ARZ3NDEKTSV4RRFFQ69G5FBG | GET operación original |

Resource type es exactamente la capacidad correspondiente. DP-026 conserva actor activo, default deny, deny vigente prevalece, alcance exacto y expiración UTC. Ninguna capacidad concede otra ni amplía 03A/03B/03C. Bucket 30/minuto por actor independiente para create, read_current y read histórico; no altera los limitadores anteriores, login/OTP ni Marketplace. No grants automáticos, comodines/membresías implícitas o sustitución del resolver global.

Catalog verifica catálogo draft/no borrado propio mediante diario 03A (actor/catalog_id/merchant_public_id exactos). Product debe pertenecer al catálogo, ser draft/no borrado/type NULL y tener procedencia inicial 03A o alta adicional 03B del mismo actor, catálogo y comercio. Recursos manuales/ajenos/cruzados/ausentes/no elegibles reciben el mismo 404 tras comprobar capacidad.

Primera ejecución de POST confirma y bloquea Merchant draft/propio/02I mediante OwnedLocalDraftMerchantV1 existente y el puente Marketplace aprobado. Catalog no consulta sus tablas. La lectura actual solo consulta Catalog y no promete elegibilidad operativa de Merchant. Solo incluye variantes propias creadas mediante diario 03D, draft/no borradas y con referencias coincidentes; filas manuales no aparecen. GET histórico requiere capacidad read vigente y actor del diario, sin consultar perfiles mutables, claims o Marketplace.

Acceso se verifica en Interfaces y Application; create se reautoriza dentro del callback transaccional y tras recuperar la referencia de idempotencia. Replay usa create vigente, no read ni nueva evaluación del catálogo/producto/Merchant. No se promete serialización frente a una futura administración de grants.

Rutas, bindings y adaptadores solo local/testing; guard previo a sesión/CSRF/DI/SQL también con route cache local usada por nuevos procesos production/staging. El entorno por sí mismo no concede capacidad.

## Idempotencia, transacción y concurrencia

Idempotency-Key ASCII visible !–~ de 1–255 sin normalización. Scope catalog.local_draft_variant.create.v1:<FB2 completo>:<FBE completo>; actor identity.user:<ULID>. Fingerprint v1 incluye catalog_public_id, product_public_id, expected_catalog_version y los seis campos exactos de variant, con attributes canónico por clave. Referencias, revisión o valor distintos producen mismatch; otra clave permite otra variante si SKU es libre.

El payload interno de RequestFingerprint usa solo arrays y primitivas: attributes es una lista de pares [key,value] ordenados por clave, y [] para el mapa vacío. Así no se modifica el contrato Platform que rechaza objetos PHP; API, JSONB y snapshot mantienen attributes como objeto JSON, incluido {}. La forma JSON original del mapa se verifica también al restaurar el snapshot antes de convertirlo a su representación de dominio.

TTL 24h desde primer claim UTC, sin extensión, purga ni reutilización. Replay dentro del TTL devuelve 201 y snapshot original con correlación actual, incluso después de editar/borrar perfiles; una clave vencida mantiene idempotency_expired.

Transacción compartida con Platform, orden claim → Merchant → Catalog → Product, compatible con altas 03B y cambios 03C. La consulta preliminar propia de catálogo no lo bloquea antes de Merchant. Bajo bloqueo se revalida catálogo/procedencia de producto, versión/máximos y SKU del comercio; luego variante, incremento de Product/Catalog y diario se confirman junto al claim. Conflicto o fallo después de cualquiera de estas escrituras revierte filas, revisiones, timestamps, diario y claim.

SKU se protege además por unique de PostgreSQL. La comprobación y conversión de conflicto se limitan a ese constraint; no se ocultan otros errores SQL como sku_conflict. La serialización por Merchant coordina variantes de distintos catálogos del mismo comercio, con orden estable y sin consulta privada cruzada.

Dos procesos reales: misma clave/input dejan una operación y replay; distintas claves/misma revisión en mismo producto o productos distintos dejan un éxito y version_conflict. Alta de variante frente a alta 03B o edición 03C con misma revisión deja un éxito/un conflicto. Mismo SKU en distintos catálogos del mismo comercio con revisiones respectivas vigentes deja un éxito/sku_conflict; no quedan claims residuales. Distintos SKU en catálogos distintos pueden completar ambos. No locks de Redis ni memoria como autoridad.

## Página y cursor

Una sentencia PostgreSQL lee propiedad/elegibilidad del catálogo/producto, ambas revisiones y página de variantes propias 03D. Orden keyset creciente por bigint interno de variante, limit+1; ese bigint nunca se expone en claro. Catálogo/producto elegibles sin variantes devuelven colección vacía con revisiones vigentes.

Cursor Crypt autenticado, payload v1 cerrado: actor público, catálogo público, producto público, revisión de catálogo, revisión de producto, limit, último id interno y expires_at UTC en segundos Unix. TTL 15 minutos desde primera página, conservado en páginas siguientes; no extensión. Contexto/forma/manipulación inválidos 422; expiración 409 cursor_expired; revisión vigente distinta 409 catalog_changed. Parent ausente/no elegible conserva 404; un cursor no concede acceso.

No colección congelada ni caché como autoridad: tras crear variante, alta de producto 03B o edición 03C en cualquier producto del catálogo, se reinicia desde primera página. Se conserva invalidación de cursores anteriores de productos 03B. GET no escribe perfiles/claims ni repara datos. Pruebas verifican una sentencia, consistencia de revisión/página y uso del índice consumido.

## Persistencia e integridad

Una migración aditiva forward-only, dos tablas privadas de Catalog inicialmente vacías; no modifica constraints/filas de tablas anteriores. PostGIS/geografía no participan.

product_variants:

- id bigint, public_id char(26) unique; catalog_id/product_id bigint propios, merchant_public_id público.
- name varchar(255), sku varchar(64), size varchar(255) nullable, unit varchar(64), barcode varchar(64) nullable, attributes JSONB objeto.
- status solo draft, version exactamente 1 en este bloque; created_at/updated_at timestamptz UTC, deleted_at nullable/no anterior a created_at.
- FK restrict (catalog_id,merchant_public_id) → catalogs(id,merchant_public_id); (product_id,catalog_id) → products(id,catalog_id). No nueva FK a tablas privadas Identity/Marketplace.
- Unique (merchant_public_id,sku) sin condición de deleted_at; unique (id,product_id) consumido por FK de diario.
- Índice completo (product_id,id) consumido por keyset y protección de FK, e índice catalog_id para FK de catálogo. No índice de barcode o atributos sin consumidor.
- CHECK de ULIDs, patrón SKU, límites/caracteres de textos y forma del mapa. Una función SQL propia immutable, usada por CHECK, valida attributes: solo objeto, máximo 20 claves/patrones/strings/límites/no blancos/C0/DEL; rechaza otros tipos antes de expandirlos. No JSONB como relación principal.

catalog_local_draft_variant_operations:

- id bigint, public_id ULID unique, catalog_id/product_id/variant_id bigint propios; merchant_public_id/actor_public_id ULID sin FK Identity.
- schema_version=1; catalog_version_before/after y product_version_before/after integer positivos, antes menor que 2147483647/después exactamente +1, comparados con cast bigint para evitar overflow.
- key_hash/request_hash/response_hash SHA-256 lowercase; correlation_id ULID, response_snapshot_ciphertext no blanco, created_at timestamptz UTC.
- FK restrict (catalog_id,merchant_public_id) a catalogs; (product_id,catalog_id) a products; (variant_id,product_id) a product_variants(id,product_id).
- variant_id unique: una operación inicial por variante; unique (catalog_id,catalog_version_after) y (product_id,product_version_after), que cubren también índices de FK. Lookup por public_id unique y actor.
- Trigger rechaza UPDATE/DELETE; down rechaza rollback sin plan de datos revisado. Contenido histórico append-only.

Snapshot original cifrado autenticado con Crypt y hash exacto; restore valida forma cerrada, versión 1, reglas de variante/textos, referencias y pares de revisión contra metadata del diario. Corrupción de ciphertext/hash/forma/metadata produce 500 genérico sin datos. Leer historial no reconstruye variantes/productos/catalogs actuales.

Aplicación local solo después de pruebas: preservar conteos/hashes de las 35 tablas previas y filas históricas de migrations, agregar exactamente dos tablas vacías y una fila de migración; segundo migrate Nothing to migrate y sin cambios. Desarrollo quedará con 37 tablas no-migrations y catorce tablas Marketplace/Catalog vacías. Bases limpias/aditivas se prueban en recursos temporales propios; no fresh/reset/rollback ni eliminación de volúmenes de desarrollo.

## Límites modulares

Domain: reglas/DTO de entrada, variantes/operación/página y cursor lógico, sin Laravel/HTTP/Eloquent/SQL/proveedores. Application: crear, listar y leer operación con puertos propios de acceso, store, writer/cursor. Infrastructure: PostgreSQL, Crypt y composición de contratos públicos aprobados. Interfaces: formato/headers/query/acceso y transformación; sin reglas de negocio en controlador.

Se aprobaron e implementaron exactamente tres consumidores adicionales de los mismos tipos públicos existentes:

- IdentityLocalDraftVariantAccess: AuthenticatedActorDirectory, AuthorizationDirectory y PermissionService de Identity/Application/Authorization.
- LocalDraftVariantResourceResolver: ResourceContextResolver y ResourceContext/ResourceReference/Scope de Identity.
- PlatformLocalDraftVariantWriter: IdempotencyStore y RequestFingerprint/IdempotencyMismatch/IdempotencyExpired de Platform/Delivery.

ArchitectureTest enumera esos archivos y tipos; total de consumidores Catalog pasó de diez a trece. Reutiliza MarketplaceLocalDraftMerchantSource/OwnedLocalDraftMerchantV1, sin nuevo contrato externo o SQL privado cruzado, dependencia inversa/ciclo, resolver global reemplazado ni regla vertical en Shared. Solo cuatro módulos materializados y archivos usados.

Se mantiene el criterio aprobado del ejercicio ficticio: diario audita la creación; no publicación/venta ni efecto externo nuevo, evento/outbox sin consumidor o administración sensible real. Esta propuesta no aplica ese criterio a las futuras mutaciones operativas.

## Validación y criterios de terminado

1. Actualizar especificación/modelo/API/ADR-008 y DP-038 como aprobados antes del código, una vez recibida la decisión explícita.
2. Unit: todos los límites de textos/Unicode/NULL/SKU/mapa ({} distinto de []), fingerprint canónico, closed restore/referencias/versiones/max, cursor/contexto/TTL.
3. Feature/Contract: tres permisos independientes y buckets, deny/revocación/expiry/actor inactivo/alcance, sesión/CSRF/formatos/headers/query/errores/privacidad y esquemas positivos/negativos; guards cacheados/nuevos procesos no locales anteriores a middleware/DI/SQL.
4. Integration PostgreSQL real: producto inicial/adicional y editado, ownership/404, SKU repetido y SKU igual en otro comercio, claves/replay/expiry/mismatch, todas las carreras descritas y rollback después de cada escritura. FK/CHECK/unique/mapa/append-only/cifrado/hashes/metadata y migración fresca/aditiva/idempotente.
5. Colecciones: 52 variantes sin duplicados/omisiones, vacía, eliminación lógica/manuales excluidos, cursor manipulado/actor/producto/limit/expiry/revisión, edición fuera de página e invalidación mutua con 03B/03C. Una sentencia y EXPLAIN del índice consumido.
6. HTTP real OTP → comercio 02I → catálogo 03A → producto adicional 03B → edición 03C → variantes/lecturas/replay; historial anterior preservado, revocación/logout/rechazos y canarios de privacidad en logs.
7. Pint, PHPStan nivel 8, contratos oficiales/JSONSchema, suites completas Unit/Feature e Integration. No sumar reruns focalizados en totales.
8. Migración local aditiva vacía aprobada, 35 tablas previas preservadas, dos nuevas vacías, catorce Marketplace/Catalog vacías; verify-foundation y ocho servicios saludables, sin dependencias ni datos positivos en desarrollo.
9. Manifiesto final y revisión completa, UTF-8 sin BOM/LF, enlaces/anclas, secretos/PII ausentes, maestro intacto, git diff --check y pruebas proporcionales. Presentar archivos, validaciones y riesgos.
10. Solicitar commit/push/CI tras el resultado terminado; no publicar en esta preparación ni avanzar al siguiente checkpoint.

## Decisión resuelta y resultado

[AGENTS.md](../../../AGENTS.md#alcance) dispone: «Detén la implementación afectada si la decisión modifica datos, dinero, seguridad o comportamiento del negocio». DP-038 afecta datos/uniqueness/revisiones y permisos de variantes; los planes anteriores las excluyeron. El usuario aprobó explícitamente el bloque completo mediante «si apruebo» antes de crear código o tablas. La preparación documental y el cierre retrospectivo de 03C fueron verificados previamente.

[Evidencia](checkpoint-03d-evidence.md). La aprobación resuelve conjuntamente las reglas de DP-038, implementación/pruebas y migración local vacía; no resuelve las decisiones productivas abiertas.

Resultado local terminado en 51 archivos: Pint 322, PHPStan nivel 8, contratos y suites completas correctos (233 Unit/Feature/7958 aserciones y 122 Integration/3302 aserciones). Migración aplicada con dos tablas vacías, 35 tablas previas preservadas y segunda ejecución sin cambios; verify-foundation correcto, catorce tablas Marketplace/Catalog vacías y ocho servicios saludables. Publicación autorizada mediante «Autorizado y continuar» después de presentar esta evidencia.
