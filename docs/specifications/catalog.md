# Catálogo local y primer producto conceptual — 03A

Fuente: maestro v1.6 §§36–37, 49–50, 52–60; [DP-035](../architecture/decisions/pending-decisions.md#dp-035--catálogo-local-con-primer-producto-conceptual), [ADR-008](../architecture/decisions/ADR-008-catalog-local-draft-foundation.md) y [plan aprobado](../sprints/sprint-03/checkpoint-03a-plan.md).

## Alta

Un actor activo con sesión y permiso create específico envía merchant_public_id, catalog {name} y product {name, description, brand}. Todas las propiedades son requeridas; description/brand admiten NULL. El servidor rechaza propiedades adicionales, IDs/estados/versiones del cliente, textos inválidos y claves de idempotencia incorrectas.

Marketplace confirma y bloquea exclusivamente el comercio draft cuyo diario 02I pertenece al actor. Comercio ajeno, ausente o ajeno al ejercicio 02I devuelve merchant_not_available indistinguible. No existe búsqueda pública ni administración operativa.

Una transacción crea catálogo, primer producto conceptual, diario cifrado inmutable y claim de idempotencia. Ambos perfiles son draft/version 1; product_type permanece NULL. Las reglas completas de texto, FK, auditoría y concurrencia están en ADR-008 y el plan.

## Consulta y repetición

GET consulta el snapshot original del creador con permiso read independiente. No expone IDs internos, autor, hashes o datos Identity; operaciones ajenas o inexistentes devuelven el mismo not_found tras autorización. El resultado no cambia al modificar perfiles o deleted_at.

POST con igual clave, actor, alcance y entrada exacta devuelve la operación original con create vigente. Cambiar la entrada devuelve 409; una clave vencida no se reutiliza. Claves diferentes permiten catálogos/productos con nombres iguales. No se normalizan nombres, NULL ni descripción vacía.

## Límites del bloque

Solo ficticio local/testing; no SKU, precios, stock, categorías, restricciones favorables, medios, listings, publicación, disponibilidad o venta. Producto conceptual no es unidad vendible. No frontend/dependencias nuevas ni grants/usuarios de desarrollo. DP-001/DP-002/DP-007/DP-012 siguen abiertas; MFA sigue siendo obligatorio para administración sensible real.

[Contrato API](../api/README.md#alta-y-consulta-de-catálogo-ficticio--03a--openapi-170) y [evidencia](../sprints/sprint-03/checkpoint-03a-evidence.md).

## Productos adicionales y contenido actual — 03B

Fuente: maestro v1.6 §§9, 25, 37, 49–50, 58–60, 72 y 78–79; [DP-036 aprobada](../architecture/decisions/pending-decisions.md#dp-036--productos-adicionales-y-consulta-del-catálogo-actual), [plan completo 03B](../sprints/sprint-03/checkpoint-03b-plan.md) y ampliación ADR-008. La aprobación «Aprobar plan completo 03B (recomendado)» autoriza esta ampliación ficticia, sin alterar los snapshots originales 03A.

POST local-draft-profiles/{catalogPublicId}/products agrega un producto conceptual en catálogo draft/no borrado creado por el actor en 03A. JSON cerrado product {name,description,brand}, mismos textos/nulls exactos de 03A. If-Match fuerte obligatorio "catalog:<ULID>:v<N>" y clave idempotente; ausencia 428, formato 422, versión distinta/máxima 409. Bloqueo claim → Merchant → Catalog; primera ejecución verifica Merchant propio/02I por contrato público, reautoriza e inserta producto/versionado/diario atómicamente. Producto draft/version 1/type NULL; catálogo incrementa revisión en uno, conservando su identidad/nombre/comercio.

GET local-draft-profiles/{catalogPublicId} devuelve perfil actual propio y ETag; GET /products recorre productos draft/no borrados, incluidos iniciales, con limit 1–50 (20 default) y cursor. Una sentencia obtiene revisión/página, con orden fijo keyset interno y limit+1. Cursor cifrado autenticado v1 ligado a actor/catálogo/limit/revisión, caduca a los 15 minutos de primera página sin extensión; inválido/contexto 422, vencido 409 cursor_expired, revisión distinta 409 catalog_changed. Reiniciar desde primera página tras cambio; futuras mutaciones del agregado deberán incrementar revisión. No cursado por offset ni catálogo público.

POST y su replay usan catalog.local.draft_product.create; perfil/colección catalog.local.draft_content.read; GET local-draft-product-operations/{operationPublicId} catalog.local.draft_product.read y autoría propia. Capacidades independientes, sesión/CSRF/DP-026 y limitadores separados; perfil/colección comparten bucket de contenido. Un permiso no concede otro ni amplía los permisos 03A. No usuarios/grants de desarrollo.

Diario adicional cifrado append-only guarda snapshot de catálogo antes/después y nuevo producto, no el contenido completo mutable. GET historial y replay devuelven ese original incluso tras cambiar/borrar perfiles; replay exige create vigente, no reevalúa referencia o versión actual, conserva TTL de claim 24h. Claves distintas con igual revisión compitiendo producen una alta y un version_conflict sin claim residual. Folios nuevos no se confunden con la operación inicial 03A.

[OpenAPI 1.8.0](../api/openapi.yaml) agrega cuatro rutas y esquemas cerrados de [operación](../api/schemas/catalog-local-draft-product-operation.v1.json), [perfil](../api/schemas/catalog-local-draft-profile.v1.json) y [página](../api/schemas/catalog-local-draft-products-page.v1.json). Sin edición/borrado por API, SKU, dinero, stock, listings, publicación, datos reales, frontend o dependencias nuevos. [Evidencia de 03B](../sprints/sprint-03/checkpoint-03b-evidence.md).

## Edición de productos draft e historial — 03C

Fuente: maestro v1.6 §§37, 49, 58–60 y 72; [DP-037 aprobada](../architecture/decisions/pending-decisions.md#dp-037--edición-local-de-productos-draft), [ADR-008](../architecture/decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03c) y [plan completo](../sprints/sprint-03/checkpoint-03c-plan.md). Aprobación «aprobar y continuar», 11 de octubre de 2026.

PUT local-draft-profiles/{catalogPublicId}/products/{productPublicId} reemplaza los tres campos name/description/brand con entrada cerrada idéntica a 03B, Idempotency-Key e If-Match fuerte de catálogo. Catálogo propio/03A y producto inicial o adicional con procedencia 03A/03B, ambos draft/no borrados/type NULL; confirma Merchant propio/02I por contrato público. Sin propiedades omitidas o normalización. Update/read de cambios son capacidades nuevas independientes.

Bloquea claim → Merchant → Catalog → Product, reautoriza y aumenta en uno revisiones de catálogo/producto. Valores idénticos devuelven 409 no_changes sin escrituras; versión antigua/máxima devuelve version_conflict; inválido/no disponible devuelve rechazo sin claim residual. Cualquier fallo parcial revierte toda la transacción. Repetición de igual clave/entrada/revisión devuelve operación 200 original con update vigente; mismatch/expired conservan 409 y TTL 24h sin extensión/reuso.

Diario adicional cifrado append-only conserva antes/después, identidad/relación y pares de versiones. GET local-draft-product-change-operations/{operationPublicId} requiere read y autor propio, incluso tras nuevas ediciones o borrado de perfiles. Las operaciones de alta 03A/03B siguen originales. Las consultas actuales 03B ven los textos/versiones nuevos; cursores anteriores devuelven catalog_changed y requieren reiniciar.

Reglas completas, contratos, constraints, errores y pruebas en el plan. Migración local aditiva vacía y forward-only; positivos solo en bases temporales propias, desarrollo vacío. Sin borrado, venta, SKU/precio/stock, activación, frontend/dependencias o datos reales; decisiones productivas y MFA sensibles reales permanecen vigentes.

## Variantes draft y consulta por producto — 03D

Fuente: maestro v1.6 §§37, 49, 58–60, 72 y 90; [DP-038 aprobada](../architecture/decisions/pending-decisions.md#dp-038--variantes-draft-locales), [ADR-008](../architecture/decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03d) y [plan completo](../sprints/sprint-03/checkpoint-03d-plan.md). «si apruebo» autoriza todas las reglas antes del código.

POST agrega una variante draft a producto inicial/adicional propio, GET variants consulta la colección y GET local-draft-variant-operations consulta el alta original. Entrada cerrada variant {name,sku,size,unit,barcode,attributes}; reglas exactas y mapa objeto acotado en el plan. SKU único por comercio, también soft-deleted, sin normalización ni reutilización; otros comercios pueden repetirlo.

Tres permisos nuevos independientes, sesión/CSRF/DP-026, If-Match de catálogo, claim 24h y bloqueo claim → Merchant → Catalog → Product. Revalida procedencia/propiedad y comercio mediante contrato público; incrementa catálogo/producto +1 sin cambiar sus textos. Revisión antigua/máxima produce version_conflict antes de sku_conflict; errores revierten toda escritura y claim.

Colección keyset con dos revisiones y página en una sentencia, variantes propias con diario 03D, cursor cifrado ligado a actor/catálogo/producto/limit/revisiones, TTL 15 minutos original. Mutaciones 03B/03C/03D invalidan cursores anteriores del catálogo. Historial cifrado/append-only y replay conservan original aunque cambien perfiles; snapshots/contratos previos no cambian.

Dos tablas locales vacías aditivas y forward-only, pruebas positivas solo en bases temporales propias. No edición/borrado de variante, precio/stock/listing, venta/activación, categorías/tipos/restricciones, frontend/dependencias o datos reales. [Evidencia](../sprints/sprint-03/checkpoint-03d-evidence.md).

## Edición de variantes draft e historial — 03E

Fuente: maestro v1.6 §§37, 49–50, 58–60, 72 y 90; [DP-039 aprobada](../architecture/decisions/pending-decisions.md#dp-039--edición-local-de-variantes-draft), [plan completo](../sprints/sprint-03/checkpoint-03e-plan.md). «Si apruebo» autoriza todas las reglas antes del código. PUT reemplaza name/size/unit/barcode/attributes de variante propia 03D, con SKU inmutable local; dos permisos update/read de cambios independientes, If-Match de catálogo, no_changes sin escritura, idempotencia 24h y lock claim → Merchant → Catalog → Product → Variant. Tres revisiones +1, diario original cifrado antes/después append-only y GET/replay sin reconstruir perfiles.

GET de colección actual evoluciona expresamente a v2 (recursos/meta.schema_version=2 y versión real 1–2147483647); cursor v2/TTL original 15 minutos, v1 rechazado 422 y reinicio. Página v1 congelada y consumidores actuales actualizados; OpenAPI 2.0.0 declara la incompatibilidad de ese único GET previo. Historias/replays de alta 03D siguen originales v1/versión 1; demás contratos anteriores intactos. Todo cambio de catálogo invalida páginas anteriores.

Una tabla de cambios vacía y CHECK de variante ampliado de version=1 a >=1, preservando 37 tablas/filas migrations/demás constraints. Solo ficticio local/testing, positivos en DB temporales y desarrollo vacío. Sin editar/reusar SKU, borrar/restaurar, precio/stock/listing, venta/activación, frontend/dependencias/datos reales o excepción MFA. Implementación y validación autorizadas; publicación reservada al cierre.
