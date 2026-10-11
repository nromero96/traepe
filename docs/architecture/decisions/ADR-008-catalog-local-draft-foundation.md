# ADR-008 — Catálogo y primer producto conceptual local

- Estado: aprobado mediante «Aprobar plan completo 03A (recomendado)».
- Fecha: 10 de octubre de 2026.
- Fuente: maestro v1.6 §§3.1, 9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; DP-035, ADR-002/ADR-007 y DP-026/DP-034.
- Alcance exacto: [plan aprobado 03A](../../sprints/sprint-03/checkpoint-03a-plan.md).

## Contexto

§37 separa el catálogo del comercio, el producto conceptual, la variante vendible y el listing por sucursal. 02I permite crear un comercio ficticio con su primera sucursal. Este bloque conecta ese comercio con su catálogo y primer producto conceptual mediante un consumidor real de Catalog. Las reglas nuevas de datos, propiedad y composición se aprobaron expresamente en DP-035.

## Decisión

Catalog posee catalogs, products y catalog_local_draft_operations. IDs internos bigint, públicos ULID únicos, timestamps UTC; catálogo/producto exclusivamente draft con versión inicial 1. Names y brand no nulos admiten 1–255 caracteres UTF-8, sin C0/DEL ni nombres vacíos después de btrim ASCII; se preservan exactamente y pueden repetirse. Description admite NULL o 0–4000 caracteres, con LF permitido y otros C0/DEL rechazados. Brand es nullable; product_type exclusivamente NULL, sin exponerse ni interpretarse como ausencia de restricciones.

Catalog referencia al Merchant mediante merchant_public_id. La FK restrict hacia merchants.public_id único constituye el contrato público de integridad aprobado por §56.1/DP-035; no permite consultas, joins o escrituras privadas cruzadas en tiempo de ejecución. Products referencia al catálogo mediante bigint propio. El diario usa FK simples y compuestas para impedir asociar catálogo/comercio y producto/catálogo incompatibles; no tiene FK hacia Identity. Las referencias públicas no trasladan IDs internos entre módulos.

El contrato público versionado Marketplace OwnedLocalDraftMerchantV1 devuelve únicamente bool y bloquea la fila Merchant en la transacción compartida. Marketplace conserva la consulta a sus tablas: solo acepta Merchant draft creado mediante el diario 02I por el mismo actor verificado. Un comercio insertado por otro ejercicio, ajeno o inexistente no es elegible y produce el mismo 404. No define membresías ni permisos comerciales reales.

Se aprueba una extensión técnica acotada de §52.1: los adaptadores Infrastructure de Catalog pueden consumir los contratos públicos de autorización de Identity y de idempotencia de Platform, además del contrato Marketplace anterior. ArchitectureTest enumera exactamente consumidores y tipos permitidos; Domain/Application siguen puros, sin SQL/Laravel ni dependencia privada cruzada, Shared sin reglas verticales y sin ciclos. No se sustituye el resolver global Identity.

POST y GET requieren sesión cookie y capacidades independientes catalog.local.draft_catalog.create/read. Scope platform técnico 01ARZ3NDEKTSV4RRFFQ69G5FB2; recursos create 01ARZ3NDEKTSV4RRFFQ69G5FB7 y read 01ARZ3NDEKTSV4RRFFQ69G5FB8 resueltos por servidor. DP-026 conserva deny-by-default, deny wins, alcance exacto, expiración UTC y actor activo. POST exige CSRF; GET exige además autor del diario. Los limitadores create/read son independientes, 30/minuto por actor.

Idempotency-Key ASCII !–~ de 1–255, sin normalización. Scope catalog.local_draft_catalog.create.v1:<scope>:<create-resource> y actor identity.user:<ULID>; fingerprint v1 de entrada exacta, incluyendo NULL. Claim Platform, reautorización del callback, bloqueo del comercio, catálogo, producto y diario se confirman o revierten juntos. TTL 24h desde el primer claim, sin extensión/purga/reuso. Replay requiere create vigente, devuelve el snapshot original y no vuelve a evaluar el comercio. GET requiere read vigente, no lee claim ni reconstruye perfiles actuales.

El snapshot v1 completo se cifra con Crypt, con hash verificado y restauración cerrada. El diario conserva correlación inicial y rechaza UPDATE/DELETE mediante trigger; Platform guarda solo hashes y referencia pública. La respuesta usa correlación actual y no-store/private. No hay evento o efecto externo que requiera outbox en este caso de uso.

Catalog y Product tienen deleted_at nullable, inicialmente NULL y no anterior a created_at. Este bloque no expone borrar/restaurar/purgar; GET es lectura del historial original incluso si cambia el perfil o deleted_at. La migración es aditiva y forward-only, con tres tablas vacías en desarrollo, sin seeds/usuarios/grants. Todos los casos positivos usan bases temporales propias.

Rutas, bindings y adaptadores solo local/testing; guard antes de sesión/CSRF/DI/SQL, incluso con caché de rutas local reutilizada en production/staging. Este ejercicio no habilita administración sensible ni modifica el requisito de MFA del maestro §60.

## Alternativas

Se descartó anticipar SKU, precios, stock, publicación por sucursal, estados operativos o permisos por membresía: carecen de decisiones aprobadas para este bloque. Leer directamente merchants/users/claims desde Catalog rompería los límites modulares. La proyección duplicada de Merchant necesitaría sincronización sin consumidor justificado; se eligieron el contrato booleano y la FK pública explícita.

## Consecuencias

El flujo backend permite crear comercio, catálogo y primer producto ficticios con trazabilidad y consulta protegida. Las restricciones locales deberán evolucionar mediante decisiones y migraciones compatibles antes de operación real. Retención productiva, categorías/restricciones, venta, mercado piloto y demás decisiones abiertas conservan sus responsables y revisión. Commit/push/CI requieren autorización de cierre independiente conforme a AGENTS.md.

## Ampliación aprobada — 03B

Aprobación explícita: «Aprobar plan completo 03B (recomendado)», 10 de octubre de 2026, [DP-036](pending-decisions.md#dp-036--productos-adicionales-y-consulta-del-catálogo-actual) y [plan completo](../../sprints/sprint-03/checkpoint-03b-plan.md). Amplía exclusivamente el ejercicio local: agregar producto a catálogo propio de 03A, perfil/colección actuales y snapshot de alta adicional. Los contratos y diarios 03A conservan su significado.

Catalog incorpora catalog_local_draft_product_operations append-only y un índice parcial products(catalog_id,id) para páginas draft/no borradas. Diario tiene producto unique, revisión antes/después consecutiva, unique catálogo/revisión posterior, FK simples/compuestas propias, actor/merchant públicos, hashes/correlación/ciphertext y created_at. Migración aditiva forward-only vacía; reglas completas en plan, sin alterar constraints/filas de 03A.

Nueva mutación requiere revisión If-Match ligada al catálogo y serializa claim → Merchant → Catalog. Merchant usa el mismo contrato público Marketplace; Catalog verifica autoría 03A propia y revalida bajo bloqueo. Product, incremento de Catalog y diario se confirman junto al claim Platform. Conflicto o fallo parcial revierte todas las escrituras. Replay conserva snapshot original/versiones históricas con create vigente y TTL 24h, sin consultar perfiles actuales.

Tres capacidades nuevas independientes para alta, contenido actual e historial; scope/recurso fijos aprobados en plan, sin extender las de 03A. GET actual solo lee tablas Catalog propias. Página/revisión en una sentencia; cursor cifrado con Crypt existente y payload v1 cerrado, actor/catálogo/limit/revisión/último id interno/expiración UTC en segundos Unix, TTL 15 minutos original. No bigint en claro, autoridad por cursor o colección congelada: cambios de revisión exigen reiniciar.

Se aprueban exactamente tres consumidores Infrastructure adicionales de los mismos tipos públicos: IdentityLocalDraftProductAccess (directorios/PermissionService), LocalDraftProductResourceResolver (resolver/DTOs de autorización) y PlatformLocalDraftProductWriter (IdempotencyStore/fingerprint/fallos). ArchitectureTest enumera archivos/tipos; reutiliza MarketplaceLocalDraftMerchantSource, sin nuevo contrato o dependencia privada cruzada. Domain/Application puros, Shared y resolver global intactos.

Reglas de texto de producto se concentran en DraftProductData dentro de Domain Catalog y se reutilizan en 03A/03B con comportamiento idéntico. Perfiles actuales y operación adicional son DTOs propios cerrados; no modelos Eloquent compartidos. No efectos externos/outbox sin consumidor, MFA administrativo real, negocio operativo, dependencias nuevas o permisos positivos de desarrollo.

## Ampliación aprobada — 03C

Aprobación explícita: «aprobar y continuar», 11 de octubre de 2026, [DP-037](pending-decisions.md#dp-037--edición-local-de-productos-draft) y [plan completo](../../sprints/sprint-03/checkpoint-03c-plan.md). Autoriza editar nombre/descripción/marca de productos conceptuales draft propios con procedencia de alta 03A/03B; PUT de reemplazo cerrado y GET de operación histórica propia. Las reglas de texto previas permanecen exactas.

Dos capacidades independientes update/read de cambio, recursos técnicos FBC/FBD y scope FB2 del plan; sesión/CSRF/DP-026, reautorización y buckets 30/minuto propios. No amplía permisos 03A/03B ni concede usuarios/grants en desarrollo. Catálogo/producto elegibles se verifican en Catalog; Merchant/02I se confirma mediante OwnedLocalDraftMerchantV1 existente. Recursos manuales, ajenos, ausentes, borrados o no elegibles devuelven 404 indistinguible.

Claim → Merchant → Catalog → Product bajo una transacción compartida. If-Match de catálogo obligatorio; versión antigua/máxima de catálogo/producto produce conflicto. Cambia solo tres textos y versión/updated_at de producto, más revisión/updated_at del catálogo. Ambos incrementos son exactamente uno. Valores idénticos producen no_changes sin escrituras; fallo parcial revierte claim/producto/catálogo/diario. Idempotencia 24h y fingerprint de referencias/revisión/campos exactos; replay conserva original con update vigente sin read ni reevaluar perfiles.

catalog_local_draft_product_changes agrega una tabla privada vacía y forward-only: FK propias simples/compuestas, revisiones consecutivas, unique catálogo/revisión y producto/revisión, hashes/correlación/ciphertext y created_at UTC. Permite múltiples cambios por producto; trigger rechaza UPDATE/DELETE. Snapshot cerrado v1 antes/después cifrado/hash y metadata verificados; GET no reconstruye perfiles mutables. No índices preventivos, eventos/outbox sin consumidor o cambios a constraints/filas de alta previas.

Los cursores 03B se invalidan con catalog_changed al cambiar la revisión; las lecturas actuales muestran textos/versiones vigentes y las operaciones de alta conservan snapshots originales. ArchitectureTest enumera exactamente IdentityLocalDraftProductChangeAccess, LocalDraftProductChangeResourceResolver y PlatformLocalDraftProductChangeWriter con los mismos tipos públicos autorizados. Reutiliza el puente Marketplace, sin ciclo/dependencia privada o regla vertical en Shared.

Solo ficticio local/testing; guard antes de sesión/CSRF/DI/SQL incluso con caché local reutilizada. Sin borrado, SKU, precios/stock/listings, activación, datos reales, frontend/dependencias nuevas o excepción MFA. Commit/push/CI requieren autorización de cierre después del resultado terminado.

## Ampliación aprobada — 03D

Aprobación explícita «si apruebo», 11 de octubre de 2026, [DP-038](pending-decisions.md#dp-038--variantes-draft-locales) y [plan completo](../../sprints/sprint-03/checkpoint-03d-plan.md). Autoriza alta de variante draft propia de producto inicial/adicional, colección actual paginada y operación original. Seis campos requeridos, SKU ASCII único por comercio incluido soft-delete, etiquetas descriptivas y mapa JSON objeto de strings acotado; sin precio/inventario/listing o venta.

Catalog posee product_variants y catalog_local_draft_variant_operations; dos tablas aditivas vacías y forward-only con FK propias/unique/CHECK, mapa validado también en PostgreSQL, diario cifrado/append-only y lookup propio. JSON {} se conserva en entrada/snapshot/JSONB; fingerprint usa pares ordenados de arrays/primitivas sin cambiar RequestFingerprint de Platform.

Claim → Merchant → Catalog → Product en transacción compartida, If-Match de catálogo y reautorización. Una alta incrementa en uno versiones/updated_at de catálogo y producto, preservando sus textos/identidad/relaciones; variante draft/version 1. SKU duplicado produce sku_conflict tras revisar versión; fallos parciales revierten filas/versiones/timestamps/diario/claim. Replay 201 original con create vigente y TTL 24h, sin read o reevaluar perfiles.

Tres capacidades independientes create/read_current/read con scope FB2 y recursos FBE/FBF/FBG del plan; sesión/CSRF/DP-026 y buckets separados. Autoría/procedencia 03A/03B se acredita en Catalog; primera alta reutiliza OwnedLocalDraftMerchantV1 de Marketplace. Lecturas actuales solo incluyen variantes propias con diario 03D; historial solo lee diario. Sin grants/usuarios positivos de desarrollo.

Una sentencia propia obtiene ambas revisiones/página. Cursor autenticado v1 ligado a actor/catalog/product/revisiones/limit/id interno y expiry original 15 minutos; toda revisión distinta exige reiniciar. Contratos/snapshots anteriores se conservan, cursores 03B se invalidan y perfiles actuales muestran el nuevo product.version.

Se aprueban exactamente IdentityLocalDraftVariantAccess, LocalDraftVariantResourceResolver y PlatformLocalDraftVariantWriter como consumidores adicionales de los mismos tipos públicos enumerados en el plan. ArchitectureTest pasa de diez a trece consumidores Catalog; no se reemplaza resolver global ni se añade SQL privado cruzado, ciclo, regla vertical Shared, módulo/dependencia o contrato externo.

Solo ficticio local/testing con guards previos a middleware/DI/SQL, incluso con route cache reutilizada. Diario audita este ejercicio sin efecto externo nuevo/evento/outbox sin consumidor; no extiende el criterio a operación real ni exime MFA sensible. Aplicación local de dos tablas vacías autorizada después de pruebas/preservación de 35 tablas anteriores. Commit/push/CI se solicitarán después del resultado terminado.
