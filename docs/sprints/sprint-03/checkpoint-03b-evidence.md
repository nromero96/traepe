# Evidencia de Checkpoint 03B — Productos adicionales y contenido actual

Preparación/aprobación: 10 de octubre de 2026. Validación y autorización de publicación: 11 de octubre de 2026. **Estado: implementado, publicado en main ad395112e4f261ea4a5450068957eab1cf76e8d2 y CI completa correcta; migración aditiva vacía aplicada y datos previos preservados.**

Base publicada main/origin/main 0690cc0ec63493a163652ec68db52c184f700e26, [03A/CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38109718961), run 38109718961/job 114382575818 y cuatro etapas completed/success sobre ese SHA. [Plan 03B completo](checkpoint-03b-plan.md) aprobado mediante «Aprobar plan completo 03B (recomendado)»; [DP-036](../../architecture/decisions/pending-decisions.md#dp-036--productos-adicionales-y-consulta-del-catálogo-actual) resuelta antes de escribir código. Esa aprobación autorizó implementación/pruebas y una tabla/índice locales vacíos. Después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Autorizado».

Fuente: maestro v1.6 §§9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; [ADR-008](../../architecture/decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03b), [especificación](../../specifications/catalog.md), [modelo](../../architecture/data-model/README.md#diario-de-productos-adicionales-y-revisión-del-catálogo--03b) y [API](../../api/README.md#productos-adicionales-y-contenido-actual--03b--openapi-180).

## Resultado implementado

Cuatro rutas: perfil actual propio con ETag; colección paginada con limit/cursor/revisión; POST de producto adicional con If-Match; GET de snapshot histórico adicional propio. El catálogo debe ser draft/no borrado y pertenecer al creador 03A. Alta adicional confirma Merchant propio/02I por el contrato público existente; producto draft/version 1/type NULL y catálogo incrementado exactamente en uno. Los endpoints/snapshots originales 03A se conservan, incluido su historial versión 1.

Tres capacidades independientes, sesión cookie/CSRF/DP-026, recursos técnicos de servidor y rate limits 30/minuto. Perfil/colección comparten contenido; alta/historial tienen buckets separados, sin ampliar capacidades de 03A. Guard local/testing anterior a sesión/CSRF/DI/SQL aun con caché de rutas reutilizada. Sin usuarios/grants positivos en desarrollo o administración real.

Una transacción reserva claim, reautoriza, bloquea Merchant y Catalog, verifica versión, agrega producto/incremento/diario cifrado append-only. Replay conserva original con create vigente y TTL 24h; no revalida perfiles ni exige read. Claves distintas basadas en la misma revisión producen una alta y un conflicto sin claim residual. Rollback parcial conserva todo el estado anterior.

Perfil/página leen solo tablas Catalog propias, en una sentencia cada una. Keyset por bigint interno, limit+1, filtro draft/no borrados e índice parcial usado por la consulta. Cursor v1 autenticado/cifrado, actor/catálogo/limit/revisión, último id interno y expiración UTC en segundos Unix; 15 minutos desde primera página sin extensión. Manipulación/contexto, expiración y cambio de revisión rechazados; nada de bigint en claro o autoridad por token.

Domain/Application puros; tres consumidores adicionales concretos de los mismos contratos públicos Identity/Platform, puente Marketplace existente y ArchitectureTest estricto. DraftProductData concentra reglas de texto idénticas 03A/03B. Shared/dependencias/resolver global intactos. OpenAPI 1.8.0 y tres esquemas cerrados v1: operación adicional, perfil, página. Logs/errores sanitizados, no-store/private y correlación actual.

Migración 000008 aditiva/forward-only: catalog_local_draft_product_operations vacía y products_local_draft_page_index. PK/ULID/FK propias simples/compuestas, producto unique, revisión consecutiva/unique por catálogo, hashes/correlación/ciphertext y created_at UTC; UPDATE/DELETE denegados por trigger. Sin editar/borrar por API, SKU/precios/stock/listings/publicación/activación, frontend o dependencias nuevas.

## Validaciones

| Comprobación | Resultado |
|---|---|
| Pint | PASS: 277 archivos en estado final |
| PHPStan | PASS: nivel 8, sin errores |
| OpenAPI/snapshots | PASS: esquema oficial y casos positivos/negativos, incluidos operación/perfil/página nuevos |
| Unit/Feature | PASS: 207 pruebas / 5919 aserciones |
| Integration final completa | PASS: 100 pruebas / 2613 aserciones, PostgreSQL/PostGIS reales; suite completa terminó en 317 s |
| Total sin sumar ejecuciones focalizadas | 307 pruebas / 8532 aserciones |
| Cadena HTTP real focalizada | PASS: 337 aserciones, sesión/CSRF/alta/replay/perfil/página/historial/revocación/logout y canaries privados |
| Migración local 000008 / preservación | PASS: una tabla vacía/un índice/un registro nuevos; las 33 tablas anteriores conservan conteos/hashes y sus registros previos de migración permanecen intactos; segunda migrate Nothing to migrate |
| Fundación/runtime/puertas negativas | PASS: HTTP real/CSRF/401/correlación/privacidad, once tablas Marketplace/Catalog vacías, datos/grants/claims preservados y ninguna base temporal; www-data escribe/lee/limpia y liveness; Pest/Pint/PHPStan rechazan fallos controlados con exit 1 |
| Alcance/codificación/master/secretos/enlaces/diff | PASS: manifiesto exacto de 55 archivos (38 código/verificación, 17 documentación/contratos), 286 enlaces locales/anclas válidos, UTF-8 sin BOM/LF, maestro SHA-256 original y credenciales locales ausentes, git diff --check correcto |
| Git antes de publicar | main/HEAD/origin/main base 0690cc0, sin commit/push 03B; staging vacío |
| Servicios | PASS: ocho servicios locales running/healthy |

Pruebas cubren límites Unicode/textos/NULL, If-Match fuerte, forma cerrada e independencia de permisos, reautorización, ownership/404, cursores/manipulación/contexto/expiry/revisión, colección de 52 productos sin duplicados y lecturas de una sentencia sin escrituras privadas cruzadas. También cifrado/hash/restauración/revisión del diario, FK compuestas/unique/append-only, límite integer, rollback después de cada etapa, instalaciones aditivas aisladas, rutas cacheadas no locales y competencia de dos procesos independientes.

## Ajustes de verificación

Se corrigieron fixtures nuevos que consultaban scope en lugar del scope_hash privado de Platform y se separó la comparación de filas previas de migrations de la adición esperada. El verificador de esquema de respuestas ahora admite boolean para has_more.

La prueba histórica 02G de instalación debe excluir también 000008 al preparar una base anterior a 000005; después aplica cuatro migraciones pendientes y comprueba preservación. La prueba histórica 03A instala explícitamente hasta su migración 000007, para no ejecutar 000008 sin tablas anteriores. Son ajustes de escenarios de prueba, sin cambios de reglas aprobadas.

La repetición posterior superó los 300 segundos de Composer mientras seguía en ejecución. El script test:integration utiliza ahora Composer\\Config::disableProcessTimeout, ya disponible y usado en el proyecto, para completar la suite; la CI conserva su límite global de 30 minutos. No cambia dependencias o composer.lock. Se retiró exclusivamente la base temporal propia dejada por ese timeout, verificando su patrón y ausencia de conexiones activas antes de DROP sin FORCE; la base traepe permanece intacta.

El helper ignorado de migración se retiró después de verificar resultados. No se ejecutó fresh/reset/rollback ni se eliminaron volúmenes o datos de desarrollo. Las verificaciones de preservación capturaron el estado anterior y lo compararon después de la migración aprobada.

## Manifiesto exacto de 55 archivos

- [apps/api/composer.json](../../../apps/api/composer.json)
- [apps/api/app/Modules/Catalog/Application/Drafts/CreateLocalDraftProduct.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/CreateLocalDraftProduct.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ListLocalDraftProducts.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ListLocalDraftProducts.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductAccess.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductAccess.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductCursorCodec.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductCursorCodec.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductStore.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductStore.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductWriter.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductWriter.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftCatalogProfile.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftCatalogProfile.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftProduct.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftProduct.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogInput.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogInput.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogProfile.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogProfile.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductCursor.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductCursor.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductData.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductData.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductInput.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductInput.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductOperation.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductOperation.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductPage.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductPage.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductProfile.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductProfile.php)
- [apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php](../../../apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/CryptLocalDraftProductCursorCodec.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/CryptLocalDraftProductCursorCodec.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftProductAccess.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftProductAccess.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftProductResourceResolver.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftProductResourceResolver.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftProductWriter.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftProductWriter.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftProductStore.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftProductStore.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftProductController.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftProductController.php)
- [apps/api/database/migrations/2026_10_10_000008_create_local_draft_product_operations.php](../../../apps/api/database/migrations/2026_10_10_000008_create_local_draft_product_operations.php)
- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftProductAccessTest.php](../../../apps/api/tests/Feature/LocalDraftProductAccessTest.php)
- [apps/api/tests/Feature/LocalDraftProductHttpTest.php](../../../apps/api/tests/Feature/LocalDraftProductHttpTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/LocalDraftCatalogPostgresTest.php](../../../apps/api/tests/Integration/LocalDraftCatalogPostgresTest.php)
- [apps/api/tests/Integration/LocalDraftProductPostgresTest.php](../../../apps/api/tests/Integration/LocalDraftProductPostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/product-environment-worker.php](../../../apps/api/tests/Support/product-environment-worker.php)
- [apps/api/tests/Support/product-race-worker.php](../../../apps/api/tests/Support/product-race-worker.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/DraftProductTest.php](../../../apps/api/tests/Unit/DraftProductTest.php)
- [docs/README.md](../../../docs/README.md)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/schemas/catalog-local-draft-product-operation.v1.json](../../../docs/api/schemas/catalog-local-draft-product-operation.v1.json)
- [docs/api/schemas/catalog-local-draft-products-page.v1.json](../../../docs/api/schemas/catalog-local-draft-products-page.v1.json)
- [docs/api/schemas/catalog-local-draft-profile.v1.json](../../../docs/api/schemas/catalog-local-draft-profile.v1.json)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md](../../../docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/specifications/catalog.md](../../../docs/specifications/catalog.md)
- [docs/sprints/sprint-03/README.md](../../../docs/sprints/sprint-03/README.md)
- [docs/sprints/sprint-03/checkpoint-03a-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03a-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03a-plan.md](../../../docs/sprints/sprint-03/checkpoint-03a-plan.md)
- [docs/sprints/sprint-03/checkpoint-03b-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03b-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03b-plan.md](../../../docs/sprints/sprint-03/checkpoint-03b-plan.md)

## Preparación, cierre local y límites

La preparación inicial verificó siete documentos/134 enlaces y anclas, maestro intacto, secretos ausentes, base/remote y ocho servicios saludables. Después se recibió aprobación explícita de todo el plan y comenzó implementación. Las cifras de 03A (282/7190) permanecen como historia de ese checkpoint; no se suman ejecuciones focalizadas a los totales de 03B.

Solo datos ficticios en bases temporales propias; desarrollo vacío. MFA sensible real, retención y decisiones productivas DP-001/DP-002/DP-007/DP-012 conservan responsable/fecha y permanecen abiertas. Futuras ediciones del agregado deberán incrementar revisión para mantener la paginación. No se avanzó a otro checkpoint.

## Aprobación y publicación autorizadas

El 11 de octubre de 2026, tras presentar los 55 archivos y la validación completa de 307 pruebas/8532 aserciones, el usuario respondió «Autorizado» a la solicitud de commit, push y CI de 03B. La autorización se registra antes del commit conforme a AGENTS.md:81. Comprende un commit del manifiesto revisado, push normal a origin/main y comprobación de Foundation CI sobre el SHA enviado.

Antes del commit se registró la publicación en curso y el requisito de cierre remoto: run/job completed/success y las cuatro etapas obligatorias correctas —Build and migrate fresh stack, Blocking quality and integration checks, Restart and verify persistence y Remove disposable resources—. Los conteos locales anteriores corresponden a la validación previa a publicar. La comprobación posterior queda registrada a continuación.

## Publicación confirmada

Commit ad395112e4f261ea4a5450068957eab1cf76e8d2, padre 0690cc0ec63493a163652ec68db52c184f700e26: 55 archivos, 3306 inserciones y 29 eliminaciones. Push normal a origin/main autorizado mediante «Autorizado»; main/HEAD/origin/main/remote iguales y repositorio limpio al terminar. Se conservan las 307 pruebas locales/8532 aserciones y verificaciones anteriores, sin sumar ejecuciones focalizadas.

[CI 38115708725 completa correcta](https://github.com/nromero96/traepe/actions/runs/38115708725), job 114400068772: run/job completed/success, ambos head_sha ad395112e4f261ea4a5450068957eab1cf76e8d2 y las cuatro etapas obligatorias completed/success. Incluye construcción/migraciones frescas, calidad/integración, reinicio/persistencia y limpieza de recursos desechables.

Resultado corroborado al iniciar la continuidad solicitada por el usuario: ocho servicios locales running/healthy, 34 tablas no-migrations y once tablas Marketplace/Catalog vacías. [03C](checkpoint-03c-plan.md) prepara edición de productos draft e historial antes/después; DP-037 fue aprobada mediante «aprobar y continuar» después de esta preparación; ese bloque quedó implementado, validado y publicado en main b88add660b33b8a94c612ae56fe6275272634c90 mediante «autorizado», con migración vacía aplicada y [CI completa correcta](checkpoint-03c-evidence.md#publicación-confirmada). La continuidad solicitada prepara [03D](checkpoint-03d-plan.md) con DP-038 aprobada mediante «si apruebo» después de la preparación y antes de implementación.
