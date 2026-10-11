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
