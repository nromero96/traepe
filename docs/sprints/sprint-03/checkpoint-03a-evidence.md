# Evidencia de Checkpoint 03A — Catálogo y primer producto conceptual

Fecha: 10 de octubre de 2026. **Estado: implementado y validado localmente; publicación autorizada mediante «Si autorizo»; commit/push/CI en curso.**

Base main/origin/main 0ab976d25c7e1c3993bc712ab437b5257ee4eed0, [02I publicado con CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38098790155). DP-035 y el [plan completo 03A](checkpoint-03a-plan.md) fueron aprobados mediante «Aprobar plan completo 03A (recomendado)». Autoriza implementación/pruebas y tres tablas vacías locales; commit/push/CI quedan reservados al cierre.

Fuente: maestro v1.6 §§3.1, 9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; [ADR-008](../../architecture/decisions/ADR-008-catalog-local-draft-foundation.md), [especificación](../../specifications/catalog.md) y [contrato API](../../api/README.md).

## Resultado implementado

POST crea catálogo y primer producto conceptual draft/version 1 para Merchant draft propio originado en 02I. JSON cerrado con referencias/textos exactos y description/brand nullable; product_type exclusivamente NULL. GET devuelve el snapshot original del creador con read independiente, aun si cambian perfiles/deleted_at. Sin SKU, precios, stock, publicación por sucursal, frontend/dependencias, activación o datos reales.

Sesión cookie/CSRF, capacidades independientes y scope/recurso técnicos del plan; DP-026 sin resolver global reemplazado. Marketplace posee su SQL y bloqueo mediante OwnedLocalDraftMerchantV1 bool; Catalog usa el puente público sin consultar tablas privadas externas. La FK restrict pública del Merchant y las FK compuestas del diario garantizan integridad. Domain/Application puros; cuatro adaptadores específicos consumen solo contratos públicos aprobados en ADR-008/ArchitectureTest.

Claim Platform, callback reautorizado, bloqueo Merchant y catálogo/producto/diario se confirman o revierten juntos. TTL 24h desde primer claim sin extensión/purga/reuso. Replay autorizado conserva el resultado, sin reevaluar el comercio; GET no consulta claims ni reconstruye perfiles. El snapshot cifrado se verifica por hash y forma cerrada; diario append-only con correlación original, respuesta con correlación actual. No eventos/efectos externos/outbox sin uso.

Solo local/testing; guard antes de sesión/CSRF/DI/SQL incluso con caché local reutilizada. Límites separados de 30/minuto por actor, no-store/private, errores/logs sanitizados sin nombres/descripción/brand/claves/cookies/SQL. OpenAPI 1.7.0 y [snapshot v1](../../api/schemas/catalog-local-draft-operation.v1.json), conservando contratos anteriores.

## Validaciones

| Validación | Resultado |
|---|---|
| composer quality | PASS: Pint 249 archivos, PHPStan nivel 8 sin errores, lint oficial OpenAPI/snapshots positivos y negativos |
| Unit/Feature | PASS: 193 pruebas, 4821 aserciones |
| composer test:integration | PASS: 89 pruebas, 2369 aserciones; PostgreSQL/PostGIS reales y HTTP OTP → comercio → catálogo → GET |
| Total sin sumar ejecuciones focalizadas | 282 pruebas, 7190 aserciones |
| Migración local aditiva 000007 | PASS: tres tablas vacías y un registro; las 30 tablas anteriores conservan conteos/hashes y los registros de migración anteriores permanecen intactos |
| Segunda migrate local | Nothing to migrate, sin cambios |
| Runtime www-data / puertas negativas | PASS: escritura/lectura/limpieza propias y liveness; Pest/Pint/PHPStan rechazan fallos controlados con exit 1 |
| verify-foundation.php | PASS: HTTP real/CSRF/401, correlación/privacidad, diez tablas Marketplace/Catalog vacías, Identity/grants/claims preservados y ninguna base temporal remanente |
| Alcance/codificación/secretos/enlaces/master/diff | PASS: 57 archivos exactos, 335 enlaces locales, UTF-8 sin BOM/LF, maestro SHA-256 intacto, secretos locales ausentes y git diff --check correcto |
| Git | main/HEAD/origin/main 0ab976d25c7e1c3993bc712ab437b5257ee4eed0; staging vacío, sin commit/push 03A |
| Servicios | Ocho servicios locales running/healthy |

Unit/Feature cubren formato/tipos cerrados, ULID estricto, UTF-8/C0/DEL/límites y LF, NULL frente a vacío, preservación de espacios, inmutabilidad, restauración y relación catálogo/producto, fingerprint/callback, permisos independientes, controles de entorno, límites separados por actor, contratos, corrupción/fallos genéricos y logs privados.

Integration verifica propiedad real del diario 02I, bloqueo parametrizado y bool público, cifrado/hash/forma cerrada, original después de cambiar perfiles/soft-delete, lectura ajena/ausente, replay sin nueva consulta comercial, TTL/mismatch/expiry/aislamiento, duplicados con claves distintas, rollback parcial producto/diario y revocación antes de escribir. FK pública/compuestas, controles de datos, append-only/forward-only, dos procesos concurrentes y aplicación aditiva preservando todas las filas anteriores. HTTP real comprueba cookies/CSRF, permisos/deny/bloqueo/revocación, repetición y logout sin canaries privados en logs.

Se corrigieron dos fixtures de prueba durante la validación: deleted_at utiliza created_at del servidor para probar el límite inclusivo sin depender de diferencias de reloj entre contenedores; las comprobaciones de ausencia de SQL vacían previamente el registro de consultas. Las reglas e implementación no se relajaron.

## Comandos y aplicación local

Composer y PHP se ejecutaron exclusivamente con docker compose --env-file .env.docker exec -T api: composer quality, composer test:integration, php tests/Support/verify-foundation.php y php tests/Support/verify-quality-gates.php. El verificador runtime se ejecutó como www-data. Las pruebas focalizadas se usaron para diagnosticar fallos, sin sumarlas al total.

Un helper temporal ignorado verificó entorno local/base traepe y exactamente la migración 000007 pendiente, tomó conteos/hashes de todas las tablas previas y confirmó solo tres tablas nuevas vacías/un registro de migración. Segunda migrate sin cambios; registros previos y diez tablas vacías verificados. Helpers propios eliminados. No hubo migrate:fresh/rollback local, seeds, usuarios/grants nuevos, borrado de datos o volúmenes. Las bases/fixtures positivos se crearon únicamente en pruebas temporales propias y se eliminaron.

## Manifiesto exacto

57 archivos, todos del alcance aprobado. Los helpers de migración y las bases/almacenamientos de prueba son temporales e ignorados; no forman parte del commit.

- [README.md](../../../README.md)
- [apps/api/README.md](../../../apps/api/README.md)
- [apps/api/app/Modules/Catalog/Application/Drafts/CreateLocalDraftCatalog.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/CreateLocalDraftCatalog.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftCatalogAccess.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftCatalogAccess.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftCatalogStore.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftCatalogStore.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftCatalogWriter.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftCatalogWriter.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftMerchantSource.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftMerchantSource.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftCatalog.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftCatalog.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogFailure.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogFailure.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogInput.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogInput.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogOperation.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftCatalogOperation.php)
- [apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php](../../../apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftCatalogAccess.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftCatalogAccess.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftCatalogResourceResolver.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftCatalogResourceResolver.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/MarketplaceLocalDraftMerchantSource.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/MarketplaceLocalDraftMerchantSource.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftCatalogWriter.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftCatalogWriter.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftCatalogStore.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftCatalogStore.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalCatalogEnvironment.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalCatalogEnvironment.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftCatalogController.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftCatalogController.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/OwnedLocalDraftMerchantV1.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/OwnedLocalDraftMerchantV1.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PostgresOwnedLocalDraftMerchantV1.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PostgresOwnedLocalDraftMerchantV1.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php)
- [apps/api/bootstrap/providers.php](../../../apps/api/bootstrap/providers.php)
- [apps/api/database/migrations/2026_10_10_000007_create_local_draft_catalog_foundation.php](../../../apps/api/database/migrations/2026_10_10_000007_create_local_draft_catalog_foundation.php)
- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftCatalogAccessTest.php](../../../apps/api/tests/Feature/LocalDraftCatalogAccessTest.php)
- [apps/api/tests/Feature/LocalDraftCatalogHttpTest.php](../../../apps/api/tests/Feature/LocalDraftCatalogHttpTest.php)
- [apps/api/tests/Feature/ModuleScaffoldingTest.php](../../../apps/api/tests/Feature/ModuleScaffoldingTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/LocalDraftCatalogPostgresTest.php](../../../apps/api/tests/Integration/LocalDraftCatalogPostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/catalog-environment-worker.php](../../../apps/api/tests/Support/catalog-environment-worker.php)
- [apps/api/tests/Support/catalog-race-worker.php](../../../apps/api/tests/Support/catalog-race-worker.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/DraftCatalogTest.php](../../../apps/api/tests/Unit/DraftCatalogTest.php)
- [docs/README.md](../../../docs/README.md)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/schemas/README.md](../../../docs/api/schemas/README.md)
- [docs/api/schemas/catalog-local-draft-operation.v1.json](../../../docs/api/schemas/catalog-local-draft-operation.v1.json)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-002-data-identifiers-messaging.md](../../../docs/architecture/decisions/ADR-002-data-identifiers-messaging.md)
- [docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md](../../../docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md)
- [docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md](../../../docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md)
- [docs/architecture/decisions/README.md](../../../docs/architecture/decisions/README.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/specifications/README.md](../../../docs/specifications/README.md)
- [docs/specifications/catalog.md](../../../docs/specifications/catalog.md)
- [docs/sprints/sprint-02/README.md](../../../docs/sprints/sprint-02/README.md)
- [docs/sprints/sprint-02/checkpoint-02i-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02i-evidence.md)
- [docs/sprints/sprint-03/README.md](../../../docs/sprints/sprint-03/README.md)
- [docs/sprints/sprint-03/checkpoint-03a-plan.md](../../../docs/sprints/sprint-03/checkpoint-03a-plan.md)
- [docs/sprints/sprint-03/checkpoint-03a-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03a-evidence.md)

## Riesgos y publicación

Este resultado es ficticio local/testing; no habilita productos vendibles o catálogo público ni define categorías/restricciones favorables. MFA de administración sensible, retención productiva y decisiones abiertas DP-001/DP-002/DP-007/DP-012 conservan responsables y revisión. Las restricciones draft/NULL requieren futuras decisiones/migraciones compatibles para operar.

Implementación aprobada mediante «Aprobar plan completo 03A (recomendado)». Después de revisar el resultado terminado, el usuario autorizó commit/push/CI de 03A mediante «Si autorizo». Se registra la autorización antes del commit conforme a AGENTS.md:81; publicación y verificación en curso. No se avanza a otro checkpoint.

## Aprobación y publicación autorizadas

El usuario respondió «Si autorizo» el 10 de octubre de 2026 a la solicitud de commit, push y validación en CI de 03A. La aprobación llega después de presentar el bloque completo: 57 archivos, 282 pruebas/7190 aserciones, calidad/contratos correctos, migración aditiva vacía con datos anteriores preservados y ocho servicios saludables.

Antes de publicar se verifica main/HEAD/origin/main sobre la base 0ab976d25c7e1c3993bc712ab437b5257ee4eed0, staging inicialmente vacío y manifiesto exacto, master/secretos/enlaces/codificación/diff. Se crea un único commit de este alcance y se realiza push normal a origin/main; sin force, merge, rebase o descarte de cambios. La CI debe completar correctamente build/migración, calidad/integración, reinicio/persistencia y limpieza sobre el SHA enviado. Su resultado se reportará al terminar la ejecución, sin anticipar éxito remoto.
