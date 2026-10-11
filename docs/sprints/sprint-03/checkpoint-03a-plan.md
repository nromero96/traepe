# Plan de Checkpoint 03A — Catálogo y primer producto conceptual en borrador

Fecha: 10 de octubre de 2026. **Estado: plan completo aprobado mediante «Aprobar plan completo 03A (recomendado)»; implementación y validación local completas; publicación autorizada mediante «Si autorizo»; commit/push/CI en curso.**

Base main/origin/main 0ab976d25c7e1c3993bc712ab437b5257ee4eed0, [02I publicado con CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38098790155). Se comprobaron SHA, run/job y los cuatro pasos obligatorios. El usuario pidió continuar con la recomendación de catálogo. La preparación inicial no modificó código/esquema/datos; tras la aprobación explícita se implementa el alcance de este plan.

## Resultado propuesto

Implementar el alta y consulta de un catálogo del comercio con su primer producto conceptual. La transacción crea ambos borradores y su auditoría; un reintento autorizado devuelve el resultado original. GET permite consultar el snapshot al creador con permiso read independiente. Es un flujo backend de principio a fin, con contrato API y pruebas HTTP reales, que se conecta al comercio creado en 02I.

Maestro v1.6 §§3.1, 9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; ADR-002/ADR-007 y DP-026/DP-034. §37 distingue producto conceptual de variante vendible y publicación por sucursal: 03A incorpora catálogo y producto conceptual. Precios, unidades/SKU, stock, categorías, restricciones operativas, listings, disponibilidad, medios y venta se concretarán posteriormente. Ningún producto de este bloque es publicable o vendible.

Solo ejercicio local/testing con datos ficticios, sin administración sensible real, excepción a MFA, membresías o acceso operativo. Sin frontend/dependencias nuevas ni resolución de DP-001/DP-002/DP-007/DP-012. La aprobación recibida abarca las reglas completas de este documento, implementación, pruebas y aplicación local de tres tablas vacías; la publicación queda reservada al cierre.

## Entrada y resultado

POST /api/v1/catalog/local-draft-catalogs exige JSON cerrado, con exactamente merchant_public_id, catalog y product:

    {
      "merchant_public_id": "01ARZ3NDEKTSV4RRFFQ69G5FAZ",
      "catalog": {"name": "Synthetic Catalog"},
      "product": {
        "name": "Synthetic Concept Product",
        "description": "Synthetic description",
        "brand": null
      }
    }

El ULID es ilustrativo; debe identificar un comercio draft creado en 02I por el actor autenticado. Catalog.name/Product.name: strings UTF-8 de 1–255 caracteres, no vacíos tras btrim ASCII y sin U+0000–001F/U+007F. Se preservan exactamente, sin trim, cambio de mayúsculas ni unicidad de nombre. Description requerida, null o string UTF-8 de 0–4000 caracteres; permite LF, rechaza los demás controles U+0000–001F y U+007F, no interpreta HTML. Brand requerida, null o string con las mismas reglas de nombre. Orden de propiedades indiferente.

Cliente no proporciona IDs nuevos, estado, versión, tipo, restricciones, precios, stock, deleted_at, usuario o configuración. Servidor genera ULIDs y fija draft/version=1. Product.product_type persiste exclusivamente NULL; no se interpreta como tipo general o ausencia favorable de restricciones. No categoría, variante, precio, moneda o publicación implícitos.

Respuesta 201: data.type=local_draft_catalog_operation, data.id=operation ULID, attributes v1 cerrados con schema_version=1, merchant_public_id, catalog {public_id,name,status:draft,version:1}, product {public_id,catalog_public_id,name,description,brand,status:draft,version:1}. Sin bigint, actor, hashes técnicos, configuración Identity o evaluación comercial. GET /api/v1/catalog/local-draft-catalogs/{operationPublicId} devuelve el mismo data original 200 exclusivamente al creador con read vigente. Otro actor e inexistente dan 404 indistinguible tras autorización.

Respuestas con correlación actual y no-store/private. Contrato OpenAPI 1.7.0 y snapshot de catálogo v1 nuevo; conserva los contratos 02F/02I existentes. Errores estables/sanitizados: 401 sesión, 403 permiso, 419 CSRF, 415 medio, 422 formato, 404 merchant_not_available o not_found, 409 idempotency_mismatch/expired, 429 tasa y 500 genérico. Merchant inexistente, ajeno o no elegible produce exactamente el mismo rechazo merchant_not_available/404; no revela qué referencia existe. Sin entrada, nombres, descripción, clave, SQL o cookies en errores/logs.

## Acceso y referencia al comercio

Sesión cookie Identity y CSRF en POST; capacidades catalog.local.draft_catalog.create/read independientes. Scope platform técnico 01ARZ3NDEKTSV4RRFFQ69G5FB2; recurso create de tipo igual a capacidad y ULID 01ARZ3NDEKTSV4RRFFQ69G5FB7; read equivalente con 01ARZ3NDEKTSV4RRFFQ69G5FB8. Contextos resueltos por servidor, DP-026 íntegra: actor activo, deny por defecto/prevalencia, scope exacto, expiración UTC y recurso coincidente. No grants automáticos, comodines, herencia o resolver global reemplazado.

CREATE requiere además que el comercio pertenezca al actor como creador de la operación ficticia 02I y continúe draft. No transforma esa autoría en membresía o permiso comercial real. Un contrato público versionado de Marketplace, OwnedLocalDraftMerchantV1, recibe merchant/actor public ULID y confirma/bloquea la referencia dentro de la transacción. Su resultado es únicamente booleano; no devuelve bigint, perfil comercial o datos Identity.

Marketplace consulta y bloquea sus propias filas merchants y marketplace_local_commerce_operations: merchant público exacto, draft y actor creador exacto. No acepta un Merchant insertado manualmente sin operación 02I ni reutiliza la coincidencia diagnóstica 02H como autorización. Catalog utiliza un adaptador de ese puerto público; no consulta ni hace joins sobre tablas privadas Marketplace.

GET exige read vigente y actor propietario del diario Catalog, sin consultar claims o perfiles actuales. POST/replay exige create vigente; no exige read para devolver su propia respuesta. El callback revalida el mismo actor/capacidad antes de confirmar la referencia Marketplace. Replay conserva snapshot/TTL sin reconstruir perfiles; no introduce nuevas escrituras ni revalidación comercial adicional del snapshot original. No se promete serialización con una futura administración concurrente de grants.

Provider, rutas y puertos registrados solo local/testing; guards anteriores a sesión, CSRF, DI y SQL, incluidos procesos nuevos que reutilizan una caché de rutas local en production/staging. Limitadores create/read independientes de 30/minuto por actor verificado, separados de OTP/Marketplace. Sin autoridad por APP_ENV ni administración sensible; una operación administrativa real seguirá requiriendo MFA conforme a §60.

## Propiedad e integridad de datos

Catalog es dueño privado de catalogs, products y catalog_local_draft_operations. Una migración aditiva/transaccional/forward-only agrega únicamente esas tres tablas vacías. Bigint interno y ULID público único/válido, fechas UTC, constraints e índices para consultas/FK.

Catalogs: id, public_id, merchant_public_id char(26) requerido/indexado, name varchar(255) requerido, status solo draft, version>=1 default 1, created_at/updated_at timestamptz requeridos/default de base, deleted_at timestamptz nullable. Referencia de integridad FK ON DELETE RESTRICT a la clave pública unique merchants.public_id; no almacena bigint Marketplace. Sin unique de merchant ni nombres: un comercio puede tener varios catálogos.

Products: id, public_id, catalog_id bigint FK restrict/index a catalogs, name varchar(255), description text nullable, brand varchar(255) nullable, product_type varchar(40) exclusivamente NULL, status solo draft, version>=1 default 1, timestamps UTC y deleted_at nullable. Catalog/name requeridos; mismas reglas de texto de entrada mediante CHECK de longitud/blanco/control cuando correspondan. Sin SKU, precio, moneda, stock, categoría, restricción favorable o unicidad de nombre.

Deleted_at contempla soft delete conforme a §49, sin endpoint de borrado/edición/restauración en 03A ni purga/retención automática. Si no es NULL, no puede ser anterior a created_at. Alta siempre lo conserva NULL. Una consulta al diario devuelve la instantánea original, no el estado mutable del catálogo/producto; no sirve de discovery o disponibilidad.

Catalog_local_draft_operations: id bigint, public_id ULID unique, catalog_id/product_id bigint FK restrict unique, merchant_public_id/actor_public_id ULID, schema_version=1, key_hash/request_hash/response_hash SHA-256, correlation_id ULID, response_snapshot_ciphertext text requerido/no vacío, created_at UTC/index. Sin updated_at/deleted_at ni FK a Identity. Índices de merchant/actor/created_at. Las FK compuestas (catalog_id,merchant_public_id) y (product_id,catalog_id) preservan el alcance del snapshot referenciado, usando unique correspondientes en catalogs/products. Trigger propio rechaza UPDATE/DELETE; down de la migración rechaza destrucción.

La FK de Catalog a la clave pública de Merchant fue aprobada explícitamente como contrato de integridad en el monolito, para cumplir §§49/56.1 y evitar referencias huérfanas. No autoriza lecturas privadas cruzadas ni una dependencia de Catalog sobre Infrastructure Marketplace. La referencia pública y su comprobación runtime permanecen bajo el contrato del dueño. Extraer bases/microservicios requeriría reemplazar ese contrato/FK mediante una decisión posterior.

## Composición modular aprobada

La tabla §52.1 menciona Catalog → Marketplace/Shared; no concreta cómo Catalog adopta actor/permisos e idempotencia transversales. DP-035 aprobó una ampliación técnica explícita, registrada en ADR-008: únicamente adaptadores Infrastructure Catalog podrán consumir los contratos públicos Application/DTO de Identity y el puerto IdempotencyStore/objetos de fingerprint/fallos de Platform. Es la misma composición técnica usada por Marketplace, sin consultas privadas, dependencias inversas/ciclos ni reglas de Identity/Platform copiadas a Catalog.

Domain y Application Catalog conservan objetos/puertos propios puros, sin Laravel ni referencias a módulos ajenos. Infrastructure implementa acceso Identity, referencia pública Marketplace, escritura Platform y persistencia/cifrado propios. Interfaces contiene validación/controladores delgados y guard local propio. El nuevo provider tiene consumidores concretos; no carpetas vacías/modelos sin uso ni nuevas reglas verticales en Shared.

ArchitectureTest admitirá solo los archivos y tipos públicos efectivamente usados de esos contratos, manteniendo las prohibiciones de capas y acceso privado. Se actualizarán bootstrap/providers y verificadores para cuatro módulos realmente usados, tres tablas Catalog nuevas vacías y ausencia de Ordering/Pricing/inventario/venta. El ADR no modifica reglas de negocio productivas ni habilita permisos reales.

## Transacción e idempotencia

Idempotency-Key ASCII !–~ de 1–255 bytes, sin espacios/control/normalización; scope catalog.local_draft_catalog.create.v1:<FB2>:<FB7>, actor identity.user:<ULID verificado>. Fingerprint canónico schema_version=1 más entrada validada exacta, incluyendo null/textos; no floats. Vigencia 24h UTC desde primer claim, sin extensión/purga/reuso.

El puerto público Platform reserva y bloquea la clave; el callback compartido reautoriza, confirma/bloquea Merchant propio mediante Marketplace y crea Catalog/Product/diario. Todos usan la misma conexión/transacción PostgreSQL: un fallo en referencia, producto, diario o reautorización revierte todo, incluido claim. Competencia por una clave crea un solo conjunto; claves distintas pueden repetir nombres. No se escribe o modifica Marketplace.

Snapshot v1 completo cifrado con Crypt y clave ignorada de Laravel; hash de plaintext verificado y restauración de forma cerrada. Platform almacena únicamente hashes y referencia pública; no nombres, descripción, brand, petición o respuesta. Correlación original inmutable en diario, correlación actual en respuesta. Sin evento público/efecto externo en este ejercicio, no se agrega outbox/consumidor sin uso. No define retención ni tratamiento de datos reales.

## Pruebas, aplicación local y cierre

Unit: ULIDs/nombres/description/brand/estados/forma cerrada, límites UTF-8, inmutabilidad, fingerprint y casos create/read/reautorización. Feature/Contract: sesión/CSRF, acceso previo a entrada, capabilities independientes, ownership/404, límites separados/actor, respuestas/snapshot/errores/no-store/correlación y logs sin canaries; rutas cacheadas denegadas fuera de local antes de sesión/puertos.

Integration PostgreSQL real: catálogo/producto/diario, texto/NULL/defaults/soft-delete/checks/FK compuestas/índices, contrato Marketplace con comercio propio/ajeno/ausente/no originado en 02I, bloqueo y propiedad, cifrado/integridad/append-only, TTL/replay/mismatch/expiry, fallos parciales y callback con rollback total; dos procesos concurrentes para una clave y claves distintas; preservación de datos previos y segunda migrate sin cambios. HTTP real encadena OTP → 02I → 03A → GET, verifica CSRF/permisos/deny/bloqueo/logout/replay y secretos ausentes de logs.

Regresiones completas 00–02I, límites modulares, Pint/PHPStan/OpenAPI/Unit/Feature/Integration y verificadores foundation/runtime/puertas negativas. Positivos y fallos solo en bases/almacenamientos temporales propios, eliminados al terminar.

Después de superar las pruebas, aplicar localmente solo tres tablas vacías/un registro de migración. Comparar conteos/hashes de todas las tablas previas; datos Identity/Marketplace/Platform intactos, segunda migrate Nothing to migrate y diez tablas de Marketplace/Catalog vacías. Sin migrate:fresh/rollback local, seeds, grants/usuarios nuevos, borrado de datos o volúmenes. Ocho servicios saludables.

Entregar evidencia y manifiesto exacto de código utilizado, contratos, migración, tests/verificadores, OpenAPI/snapshot, ADR-008 y documentación antes de solicitar commit/push/CI. La aprobación de implementación no autoriza publicación. Decisiones productivas abiertas y sus responsables/fechas se conservan.

## Preparación verificada antes de aprobación

Esta revisión modifica únicamente seis documentos: docs/README.md, pending-decisions.md, README de Sprint 02, evidencia de 02I, README de Sprint 03 y este plan. Maestro leído con sus tablas y hash intacto; base/CI 02I revalidadas; enlaces locales, UTF-8/LF, secretos y git diff --check correctos; staging vacío, ocho servicios saludables y verify-foundation.php correcto. No se materializó Catalog ni se creó tabla, grant, dependencia o dato positivo nuevo. Esa preparación quedó aprobada mediante «Aprobar plan completo 03A (recomendado)»; la implementación/validación continúa con DP-035 resuelta. [Evidencia del resultado](checkpoint-03a-evidence.md).

Implementación y validación local completas. El usuario autorizó commit/push/CI del resultado revisado mediante «Si autorizo» el 10 de octubre de 2026; [registro](checkpoint-03a-evidence.md#aprobación-y-publicación-autorizadas). Esta autorización de cierre es posterior e independiente de la aprobación inicial del plan.
