# Plan de Checkpoint 03E — Edición de variantes draft e historial

Fecha: 11 de octubre de 2026. **Estado: plan completo aprobado mediante «Si apruebo» el 11 de octubre de 2026; DP-039 resuelta antes del código. Implementación y validación local terminadas: 380 pruebas/12982 aserciones. Colección actual v2 y migración local vacía/CHECK ampliado aplicados, 37 tablas previas preservadas. Commit/push/CI autorizados mediante «si autorizo» después de presentar el resultado terminado; publicación en curso.**

## Base y fuente

Base publicada main/HEAD/origin/main/remote 268bae47233697f659be7600a4335f5bff702c2c, [cierre de 03D](checkpoint-03d-evidence.md#publicación-confirmada). [Foundation CI 38161448476](https://github.com/nromero96/traepe/actions/runs/38161448476), job 114535038415: run/job SHA exactos, completed/success y cuatro etapas correctas. Suites locales y remotas: 233 Unit/Feature/7958 aserciones y 122 Integration/3302 aserciones, total 355/11260. Pint completo con caché nueva: 322 archivos; PHPStan 8. La corrección final de 03D fue solo formato, dentro de los mismos 51 archivos.

Al preparar 03E: 37 tablas no-migrations, catorce Marketplace/Catalog vacías, ocho servicios saludables, sin DB temporal de prueba remanente y working tree inicialmente limpio. El CHECK real product_variants_version_check es CHECK ((version = 1)); la colección vigente v1 también fija version=1. Es necesario aprobar su evolución expresamente, sin presentar versiones inventadas o esconder variantes editadas.

Fuente: maestro v1.6 §§9, 25, 37, 49–50, 52–53, 56, 58–60, 64, 72, 78–79 y 90; ADR-008/DP-026/DP-035 a DP-038. §72 incluye editar variantes, pero no concreta mutabilidad de SKU, campos/revisiones, permisos, no_changes ni evolución del contrato ficticio. [DP-039](../../architecture/decisions/pending-decisions.md#dp-039--edición-local-de-variantes-draft) registra conjuntamente estas decisiones. El maestro conserva precedencia y su hash original.

## Resultado propuesto y aprobación conjunta

Editar cinco campos descriptivos de una variante draft propia creada en 03D: name, size, unit, barcode y attributes. SKU permanece inmutable en este ejercicio. Guardar antes/después originales y consultar cada cambio con permiso independiente. La colección actual pasa expresamente a un contrato v2 con versión real de variante; los historiales/replays de alta 03D permanecen v1 originales.

Ejemplo: catálogo v4/producto v3/variante v1. PUT con If-Match de catálogo v4 cambia nombre y attributes, deja catálogo v5/producto v4/variante v2 y una operación histórica 200. Repetir igual clave/entrada devuelve esa operación sin editar otra vez. Otra clave basada en catálogo v4 recibe version_conflict. El historial de alta conserva nombre, atributos y variante v1 originales.

El usuario aprobó conjuntamente mediante «Si apruebo» todas las reglas de este plan, código/pruebas en las cuatro capas, evolución explícita del contrato actual, una tabla de diario vacía y ampliación del CHECK de revisión de variantes. Aplicación local después de suites; datos positivos exclusivamente en bases temporales propias. Desarrollo sigue vacío, sin usuarios/grants nuevos. Commit/push/CI requieren autorización de cierre sobre el resultado terminado.

Solo ficticio local/testing. No modificación/reutilización de SKU, borrado/restauración, reasignación, categorías/tipos/restricciones, opciones/medios/combos, precio/stock/listing, venta/discovery/activación, datos reales, frontend/dependencias nuevas o administración sensible real. El criterio local de SKU inmutable no fija esa política para producción. DP-001/DP-002/DP-007/DP-012 y demás productivas conservan responsable/fecha y siguen abiertas; sin exención de MFA sensible del maestro §60.

## Rutas y entrada

| Método y ruta bajo /api/v1/catalog | Resultado |
|---|---|
| PUT /local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants/{variantPublicId} | Reemplazo completo de cinco campos; 200 con operación original de cambio |
| GET /local-draft-variant-change-operations/{operationPublicId} | Snapshot original de cambio propio; 200 |
| GET /local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants | Ruta existente; se actualiza deliberadamente su respuesta/cursor a v2 |

PUT/historial no aceptan query. ULIDs canónicos mayúsculos estrictos. PUT exige application/json y objeto cerrado variant con las cinco claves presentes; no PATCH ni conservación implícita de campos omitidos. SKU, IDs, merchant/actor, estado, versión, precios/cantidades, deleted_at y cualquier propiedad extra se rechazan, incluso si coinciden con los actuales.

| Campo editable | Regla de 03D conservada |
|---|---|
| name | UTF-8 1–255 caracteres; no blanco tras btrim de espacio ASCII, sin C0/DEL |
| size | NULL o mismo texto 1–255; etiqueta opaca |
| unit | UTF-8 1–64 con las mismas reglas; etiqueta opaca |
| barcode | NULL o ASCII visible !–~ 1–64; exacto, sin trim/unicidad/checksum |
| attributes | Objeto JSON 0–20 pares; claves ASCII ^[a-z][a-z0-9_]{0,63}$, valores UTF-8 1–255, no blancos ASCII y sin C0/DEL |

{} se conserva como objeto en input/API/snapshot/JSONB y [] se rechaza. No valores null, arrays, objetos, números o booleanos dentro del mapa. Claves ordenadas para comparación/fingerprint; el orden del JSON/mapa no representa un cambio. Textos/Unicode/NULL y valores exactos se conservan, sin normalización ni conversión de unidades. Campos/atributos descriptivos no determinan permisos, precio o disponibilidad.

If-Match único/fuerte obligatorio: "catalog:<ULID de ruta>:v<N>", N decimal canónico 1–2147483647, como 03B/03C/03D. Falta: 428 precondition_required; malformed/weak/lista/wildcard/referencia/rango: 422 validation_failed. No versiones de producto/variante del cliente: el catálogo protege toda la mutación y el servidor obtiene las revisiones bajo bloqueo.

Primera ejecución: elegibilidad y procedencia, revisión de catálogo/máximos de los tres perfiles, luego comparación exacta de los cinco campos. Revisión distinta o cualquiera al máximo integer: 409 version_conflict. Si los cinco coinciden: 409 no_changes, sin perfiles/timestamps/diario/claim residuales. SKU procede exclusivamente de la variante bloqueada; no hay nueva comprobación de SKU de entrada ni sku_conflict en PUT. Replay se resuelve antes de reevaluar perfiles/no_changes.

## Revisiones, respuesta e historial

Una edición incrementa exactamente +1 Catalog, Product y Variant, y sus updated_at UTC. Conserva todos sus IDs/relaciones, estados draft, created_at/deleted_at, nombre del catálogo, textos/type del producto y SKU de variante. Se reemplazan únicamente los cinco campos de variante. La creación 03D sigue iniciando cada variante en 1 y guardando su operación original v1.

Respuesta cerrada v1 de cambio: data.type=local_draft_variant_change_operation, id=operation ULID. Attributes:

- schema_version:1 y merchant_public_id.
- catalog:{public_id,name,status:draft,version_before,version_after}.
- product:{public_id,catalog_public_id,name,description,brand,status:draft,version_before,version_after}.
- variant:{public_id,product_public_id,before:{name,sku,size,unit,barcode,attributes,status:draft,version},after:{name,sku,size,unit,barcode,attributes,status:draft,version}}.

Tres pares positivos/consecutivos; SKU igual en before/after y referencias exactas. Snapshot usa los textos de catálogo/producto vigentes al editar, que no se modifican por esa edición. Se cifra y conserva como resultado original, sin reconstruir perfiles para GET/replay. Meta singular solo correlation_id actual. PUT/historial no emiten ETag de estado actual. No bigint, actor, hashes, claves, ciphertext, deleted_at o tiempos técnicos en público.

Errores 401/403/419/415/422/404/428/429/500 existentes y 409 version_conflict/no_changes/idempotency_mismatch/idempotency_expired; mensajes sanitizados, no-store/private y correlación, sin SQL/input/SKU privado/secretos en respuesta o logs. 429 conserva Retry-After. Corrupción de ciphertext/hash/forma/metadata falla con 500 genérico.

## Evolución explícita de la colección actual

La colección 03D actual no puede conservar el contrato que afirma version=1 tras una edición. Se propone una actualización coordinada del ejercicio local:

- Misma ruta GET y capacidad read_current; respuesta 200, incluso vacía, con recursos local_draft_variant, IDs y campos descriptivos actuales.
- Nuevo esquema cerrado catalog-local-draft-variants-page.v2.json, con schema_version=2 en cada recurso y meta.schema_version=2 también en páginas vacías. Variant.version es la revisión PostgreSQL real 1–2147483647.
- Los demás campos de meta se conservan: correlation_id, catalog_version, product_version, next_cursor, has_more y filters{catalog_public_id,product_public_id,limit}. Limit 1–50/default 20 y query solo limit/cursor; ETag de catálogo/no 304.
- Cursor cifrado/autenticado v2, forma cerrada y mismo contexto actor/catálogo/producto/ambas revisiones/limit/último bigint interno/expires_at UTC. TTL 15 minutos original sin extensión. Cursor v1 se rechaza 422 validation_failed y obliga a reiniciar; v2 inválido/contexto distinto 422, vencido cursor_expired 409, revisiones distintas catalog_changed 409.
- OpenAPI continúa en formato 3.0.3/prefijo API /api/v1, pero info.version pasa a 2.0.0 para declarar la incompatibilidad del contrato local actual. Se agrega el componente v2 y se cambia únicamente la respuesta de este GET previo; se añaden las dos operaciones 03E.
- El archivo/componente de página v1 queda congelado como contrato histórico de 03D; deja de describir el GET actual. Los consumidores y pruebas locales de esa colección adoptarán v2. No se presenta esta evolución como semánticamente idéntica ni compatible con validadores v1 que solo acepten versión 1.
- Todos los demás paths/componentes/esquemas y operaciones históricas 03A/03B/03C/03D conservan contrato y resultados originales. El POST 03D/historial de alta siguen devolviendo su variante original versión 1, incluso después de editarla.

Se mantiene lectura actual en una sentencia PostgreSQL de propiedad/procedencia/revisiones/página, keyset por id interno y limit+1, con índice existente (product_id,id). DTO actual v2 separado del snapshot de alta v1; no relajar su restore ni reescribir diarios originales. Solo variantes propias draft/no borradas de procedencia 03D, ahora con sus revisiones reales; no ocultar filas revisadas.

Una edición invalida todos los cursores anteriores del catálogo: productos 03B y variantes v2, incluso cambios fuera de página/producto. Altas 03B/03D y ediciones 03C también invalidan variantes v2. Reiniciar muestra valores actuales; GETs no escriben perfiles/claims o reparan datos.

No despliegue productivo ni convivencia de un lector antiguo que invente versión 1 con un escritor de revisiones >1. La aplicación local se coordina con lectores v2/migración vacía antes de habilitar el caso de uso nuevo en pruebas; no una migración de datos o activación comercial.

## Acceso y propiedad

Sesión cookie Identity en ambas rutas nuevas; CSRF en PUT. Dos capacidades/buckets independientes, 30/minuto por actor; scope técnico platform 01ARZ3NDEKTSV4RRFFQ69G5FB2:

| Capacidad | Recurso técnico ULID |
|---|---|
| catalog.local.draft_variant.update | 01ARZ3NDEKTSV4RRFFQ69G5FBH |
| catalog.local.draft_variant_change.read | 01ARZ3NDEKTSV4RRFFQ69G5FBJ |

Resource type igual a la capacidad. DP-026 íntegra: actor activo, default deny, deny vigente prevalece, scope/recurso exactos y expiración UTC. Update no concede create/read_current/read de altas ni lectura de cambios; read de cambios no concede update. Capacidades anteriores tampoco autorizan las rutas nuevas. Colección v2 conserva exclusivamente read_current/FBF ya aprobado, sin grants automáticos, usuarios nuevos, herencia/comodines o modificación del resolver global/limitadores anteriores.

Catalog acredita catálogo propio por diario 03A y producto inicial 03A o adicional 03B del mismo actor/catálogo/comercio, draft/no borrados/type NULL. Variante debe pertenecer exactamente a ambos, conservar merchant_public_id, ser draft/no borrada y tener diario de alta 03D coincidente en actor/catálogo/producto/comercio/variante. Manual/ajeno/cruzado/ausente/no elegible: mismo 404 tras autorización.

Primera ejecución confirma y bloquea Merchant draft propio/02I mediante OwnedLocalDraftMerchantV1 y puente Marketplace existente, sin SQL privado ajeno. Bajo bloqueos se revalidan todos los perfiles/procedencias y revisiones. Read_current v2 sigue leyendo solo Catalog, sin prometer elegibilidad operativa de Merchant.

Update se comprueba en Interfaces/Application, dentro del callback y tras obtener referencia idempotente. Replay requiere update vigente, sin read ni nueva elegibilidad de perfiles. Historial exige read de cambios vigente y actor del diario; solo consulta ese diario propio, sin claims/perfiles mutables/Marketplace. No promete serialización con futura administración de grants.

Guards local/testing antes de sesión/CSRF/DI/SQL, incluso con rutas cacheadas local reutilizadas en nuevos procesos production/staging. APP_ENV no concede permiso ni habilita administración real.

## Idempotencia y transacción

Idempotency-Key ASCII visible !–~ 1–255, exacta. Scope catalog.local_draft_variant.update.v1:<FB2 completo>:<FBH completo>; actor identity.user:<ULID>. Fingerprint v1 incluye catalog_public_id, product_public_id, variant_public_id, expected_catalog_version y cinco campos exactos. Attributes usa lista ordenada de pares [key,value] y [] para el mapa vacío; solo arrays/primitivas del contrato Platform, manteniendo {} en API/JSONB. Diferentes referencias/revisión/textos/NULL no equivalen.

TTL 24h desde primer claim UTC, sin extensión/purga/reuso. Misma clave/entrada: una edición y replay 200 original. Otra entrada/referencia/If-Match: idempotency_mismatch; vencida: idempotency_expired aun tras cambios de perfiles. Reautorización vigente antes de devolver replay.

Una conexión/transacción: claim → Merchant → Catalog → Product → Variant. Consulta preliminar Catalog solo obtiene referencia Merchant sin bloquear antes de él. Orden compatible con altas 03B/03D y cambios 03C. Variante/procedencia se comprueban bajo lock tras padres; todos los errores/no_changes revierten claim.

Actualiza Variant (cinco campos/version/updated_at), Product y Catalog (solo version/updated_at), inserta diario cifrado y confirma claim. Fallo tras cualquiera de esas escrituras revierte filas/revisiones/timestamps/diario/claim; no toca Marketplace o diarios anteriores.

Carreras de dos procesos reales: misma clave/input deja una operación/replay; distinta entrada con misma clave mismatch. Distintas claves/misma revisión, misma variante o variantes/productos distintos del catálogo: un éxito/version_conflict. PUT frente a alta 03B, edición 03C o alta 03D con misma revisión: un éxito/conflicto. Revisiones posteriores vigentes permiten ediciones sucesivas. Distintos catálogos pueden completar ambos bajo el mismo orden Merchant; aislamiento de actores/recursos y ningún claim fallido residual. Sin Redis/memoria como autoridad.

Diario audita el ejercicio ficticio con el criterio acotado ya aprobado: sin venta/publicación/efecto externo nuevo que requiera outbox o consumidor inventado. No extiende este criterio a futuras mutaciones críticas operativas ni exime de §64.1/MFA sensible.

## Migración e integridad

Una migración nueva posterior a 000010, transaccional y forward-only, agrega catalog_local_draft_variant_changes vacía y reemplaza únicamente product_variants_version_check de version=1 por version>=1. Default 1 e integer mantienen máximo 2147483647. No cambia columnas/filas/SKU/otros CHECK/FK/índices, función del mapa o trigger/diarios previos.

Tabla privada nueva:

- id bigint; public_id ULID unique; catalog_id/product_id/variant_id bigint propios; merchant_public_id/actor_public_id ULID, sin FK Identity/Marketplace.
- schema_version=1; catalog/product/variant_version_before/after integer positivos, before<2147483647 y after=before+1 con casts bigint.
- key_hash/request_hash/response_hash SHA-256 lowercase; correlation_id ULID; response_snapshot_ciphertext no blanco; created_at timestamptz UTC/default, sin updated_at/deleted_at.
- FK restrict compuestas (catalog_id,merchant_public_id) → catalogs; (product_id,catalog_id) → products; (variant_id,product_id) → product_variants(id,product_id).
- Unique(catalog_id,catalog_version_after), Unique(product_id,product_version_after), Unique(variant_id,variant_version_after); cubren también sus FK. Variante no unique por sí sola: admite múltiples cambios. Índice public_id unique usado por lookup/actor; sin índices preventivos o nuevos en product_variants.
- Trigger propio rechaza UPDATE/DELETE; down rechaza destruir historia o reducir revisiones sin un plan de datos revisado. Nombres explícitos <64 caracteres.

Restore cerrado verifica cifrado/hash/formas, mapa objeto, IDs/referencias/SKU iguales, estados y tres incrementos contra metadata. No consulta perfiles actuales para restaurar. El diario anterior de alta conserva su constraint/snapshot version=1; no se reinterpretan ni actualizan operaciones previas.

Aplicación local solo tras suites: conteos/hashes de las 37 tablas previas y filas migrations preservados, una tabla vacía y una fila de migración nuevas; exactamente el CHECK de variante ampliado y demás constraints previos intactos. Resultado: 38 tablas no-migrations y quince Marketplace/Catalog vacías. Segundo migrate sin cambios. Fixtures positivos solo en DB temporales propias; no fresh/reset/rollback ni volúmenes/usuarios/grants/datos positivos de desarrollo.

## Arquitectura y archivos previstos

Domain/Application puros: input editable, comparación, snapshot antes/después y DTO actual v2, puertos propios y update/read; separar creación original v1 de representación actual v2. Infrastructure: SQL/Crypt/composición de contratos públicos. Interfaces: formato/headers/acceso y transformación, controlador delgado. Provider/rutas/colección v2/guards/verificadores y pruebas usados; sin carpetas futuras o reglas verticales en Shared.

Se aprobaron tres consumidores Infrastructure adicionales, mismos tipos públicos existentes:

- IdentityLocalDraftVariantChangeAccess: AuthenticatedActorDirectory, AuthorizationDirectory y PermissionService de Identity.
- LocalDraftVariantChangeResourceResolver: ResourceContextResolver y ResourceContext/ResourceReference/Scope de Identity.
- PlatformLocalDraftVariantChangeWriter: IdempotencyStore y RequestFingerprint/IdempotencyMismatch/IdempotencyExpired de Platform.

ArchitectureTest enumerará trece → dieciséis consumidores Catalog. Reutiliza MarketplaceLocalDraftMerchantSource/OwnedLocalDraftMerchantV1, sin contrato externo nuevo, SQL privado cruzado/ciclo, dependencia instalada o resolver global reemplazado.

Especificación/modelo/API/seguridad/ADR-008/DP-039 se actualizaron después de la aprobación y antes del código. Nuevos esquemas catalog-local-draft-variant-change-operation.v1.json y catalog-local-draft-variants-page.v2.json; OpenAPI 2.0.0 y pruebas del único GET previo que cambia. Archivo de migración, cuatro capas, workers proporcionales, verificadores/evidencia/manifiesto. No prometer conteo final de archivos/pruebas anticipadamente.

## Criterios de aceptación y validación

1. Unit: cinco campos/límites/Unicode/NULL/mapa {} distinto de [], igualdad canónica/no_changes, SKU/referencias inmutables, fingerprint, restore cerrado y tres revisiones/máximos/cursor v2/TTL.
2. Feature/Contract: matriz independiente update/read/read_current/create/read de alta, actor/deny/revocación/expiry/scope/recurso, cookies/CSRF, formato/headers/query/errores/buckets/correlación/privacidad.
3. HTTP real OTP → comercio 02I → catálogo/productos 03A/B/C → variantes 03D → cambios sucesivos 03E → colección v2/historial/replay. Alta 03D original v1 y snapshots anteriores intactos.
4. PostgreSQL: propiedad/procedencia/padres/404 indistinguible, cinco actualizaciones exactas/SKU intacto, tres revisiones, no_changes sin escritura, replay/TTL/mismatch/aislamiento; fallos tras cada escritura revierten todo.
5. Dos procesos para todas las carreras descritas, sin duplicados/deadlocks/claims fallidos. Editar/repetir tras ediciones/borrado de perfiles conserva el original; permiso revocado impide replay.
6. Página vacía/52 variantes/editadas/soft-deleted/manuales, revisiones reales v2, ninguna fila escondida por revisión >1; una sentencia e índice keyset. V1 cursor rechazado, v2 contexto/manipulación/expiry/revisión y reinicio; invalidación mutua 03B/03C/03D/03E, cambios fuera de página/producto.
7. Contratos: esquema/componente v1 de página congelados; GET actual usa v2 con meta.schema_version también vacío; solo ese GET previo cambia. Historias/replays/otros paths/schemas anteriores sin cambios. Consumidores/pruebas actuales adoptan v2 sin versiones falsas.
8. FK/CHECK/unique/hash/ULID/cifrado/metadata, cambios sucesivos y append-only/forward-only; migración fresca/aditiva/idempotente y CHECK version=1 → >=1 con todas las filas/otros constraints preservados.
9. Guards/bindings/caché y procesos nuevos fuera de local/testing anteriores a middleware/DI/SQL; límites modulares/consumidores exactamente aprobados.
10. Pint completo con caché nueva, PHPStan 8, OpenAPI oficial y JSON Schema positivos/negativos, suites completas Unit/Feature e Integration reales, verificadores pertinentes; no sumar focalizados/reruns.
11. Migración local vacía aprobada, 37 tablas previas preservadas, quince Marketplace/Catalog vacías, verify-foundation/ocho servicios y sin DB temporal. Manifiesto final, UTF-8 sin BOM/LF, enlaces/master/diff/secretos correctos.
12. Presentar resultado terminado con archivos/validaciones/riesgos y pedir commit/push/CI; no publicar ni avanzar al siguiente bloque en esta preparación.

## Decisión aprobada

[AGENTS.md](../../../AGENTS.md#alcance) exige: «Detén la implementación afectada si la decisión modifica datos, dinero, seguridad o comportamiento del negocio». DP-039 modifica campos/revisiones/CHECK/diario, permisos y contrato actual; 03D excluyó edición. “Continuar” permite preparar el siguiente bloque, pero no determina estas reglas nuevas ni aprueba silenciosamente una incompatibilidad de contrato.

Aprobación conjunta recibida mediante «Si apruebo», antes del código: incluye colección actual v2/SKU inmutable y migración local de una tabla vacía/ampliación del CHECK. [Evidencia](checkpoint-03e-evidence.md). Autoriza implementación/pruebas/aplicación local; no autoriza commit/push/CI de 03E.## Aprobación recibida y publicación autorizada

El usuario aprobó el plan completo mediante «Si apruebo» el 11 de octubre de 2026. DP-039 quedó resuelta antes del código: todas las reglas, SKU inmutable local, colección actual v2, código/pruebas y migración aditiva vacía/CHECK ampliado están autorizados e implementados.

[Resultado y manifiesto](checkpoint-03e-evidence.md): 247 Unit/Feature/9249 aserciones y 133 Integration/3733, total 380/12982; sin sumar focos/reruns. Pint 343 con caché nueva, PHPStan 8/OpenAPI/JSON Schema correctos. Migración local: 37 tablas y filas migrations anteriores preservadas, exactamente una tabla vacía/una fila nueva, CHECK >=1 y demás constraints intactos; segundo migrate sin cambios, quince Marketplace/Catalog vacías, ocho servicios saludables y verify-foundation correcto.

La última reautorización y restauración del resultado permanecen dentro de la transacción hasta confirmar: un fallo revierte también campos/revisiones/timestamps, diario y claim. Las carreras reales con altas 03B/03D y cambios 03C/03E, replay y mismatch están verificadas; no se promete serialización con futura administración de grants.

[AGENTS.md](../../../AGENTS.md#restricciones) exige «No realizar commit, push, merge, publicación o despliegue sin aprobación explícita». Después de presentar este resultado terminado, el usuario autorizó commit/push/CI de 03E mediante «si autorizo» el 11 de octubre de 2026. La publicación está en curso y se verificará Foundation CI sobre el SHA exacto, incluidas sus cuatro etapas obligatorias. La publicación de 03D permanece confirmada e independiente.
