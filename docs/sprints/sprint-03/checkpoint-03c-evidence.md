# Evidencia de Checkpoint 03C — Edición de productos draft

Fecha: 11 de octubre de 2026. **Estado: plan completo aprobado mediante «aprobar y continuar»; implementado y validado localmente, migración aditiva vacía aplicada y datos anteriores preservados. Publicado en main b88add660b33b8a94c612ae56fe6275272634c90 mediante «autorizado»; CI completa correcta sobre ese SHA.**

## Base y aprobación

El usuario solicitó continuar después de [03B publicado](checkpoint-03b-evidence.md#publicación-confirmada). Base inicial y previa a publicación main/HEAD/origin/main/remote ad395112e4f261ea4a5450068957eab1cf76e8d2; working tree inicialmente limpio y staging vacío durante 03C. [CI 38115708725](https://github.com/nromero96/traepe/actions/runs/38115708725), job 114400068772, ambos SHA exactos y cuatro etapas completed/success, corroboradas antes de preparar 03C. Esa CI corresponde a 03B; después de presentar el resultado terminado, el usuario autorizó commit/push/CI de 03C mediante «autorizado».

Se completó primero la preparación documental: nueve archivos, 216 enlaces/anclas, maestro/secretos/LF/diff/base/servicios verificados, 34 tablas previas y once tablas Marketplace/Catalog vacías. [Plan completo](checkpoint-03c-plan.md) y [DP-037](../../architecture/decisions/pending-decisions.md#dp-037--edición-local-de-productos-draft) registrados antes del código. El usuario respondió «aprobar y continuar», autorizando todas sus reglas, implementación/pruebas y una tabla local aditiva vacía. La publicación se reservó para el resultado terminado conforme a AGENTS.md.

Fuente: maestro v1.6 §§9, 25, 29, 32–33, 36–37, 47–50, 52–60, 64, 72, 78–80, 90 y 95; [ADR-008/03C](../../architecture/decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03c), [especificación](../../specifications/catalog.md#edición-de-productos-draft-e-historial--03c), [modelo](../../architecture/data-model/README.md#diario-de-edición-de-productos-draft--03c) y [contrato](../../api/README.md#edición-de-producto-draft-e-historial--03c--openapi-190). Maestro SHA-256 conservado: 85FEF02B0CBA9669EE37AAE4986BD7709634CB93987060435AF0A3759A802C2E.

## Resultado implementado

PUT reemplaza exactamente nombre/descripción/marca del producto conceptual draft propio, conservando identidad, relaciones, estado, tipo NULL, deleted_at y created_at. GET consulta el snapshot original propio de cambio. Entrada requerida/cerrada, mismas reglas de texto/null de 03A/03B, referencias públicas ULID e If-Match fuerte de catálogo obligatorio; ningún bigint en público.

Dos capacidades independientes update/read de cambios, scope FB2 y recursos FBC/FBD del plan; sesión cookie/CSRF, DP-026, reautorización y dos buckets 30/minuto por actor separados de los anteriores. Solo catálogo propio con diario 03A y producto inicial/adicional con origen 03A/03B; primera escritura confirma Merchant/02I mediante contrato público existente. Recursos manuales/cruzados/ajenos/inexistentes/borrados y Merchant no disponible reciben 404 indistinguible. Guard local/testing anterior a sesión/CSRF/DI/SQL incluso con caché local reutilizada en nuevos procesos production/staging.

Una transacción serializa claim → Merchant → Catalog → Product, compatible con alta 03B. Incrementa catálogo/producto exactamente en uno, actualiza textos/timestamps UTC de servidor e inserta diario cifrado. no_changes rechaza valores idénticos sin escrituras; revisión antigua/máxima produce version_conflict. Fallos tras cada escritura revierten claim/textos/versiones/timestamps/diario. Igual clave/entrada devuelve original 200, sin read o reevaluación mutable, TTL 24h sin extensión/reuso. Distintas claves/ misma revisión dejan un éxito y un conflicto sin claim residual; se verificaron mismo producto, productos distintos y alta frente a edición con dos procesos reales.

Historial cerrado v1 antes/después cifrado con Crypt, hash/forma/metadata verificados y append-only; GET solo lee diario Catalog. Las altas originales 03A/03B permanecen originales tras edición o borrado de perfiles. Las consultas actuales conservan contrato/orden y muestran textos/versiones nuevos; cursores anteriores rechazan catalog_changed, incluida edición de producto en otra página.

Domain/Application puros, puertos propios, controlador delgado y tres consumidores Infrastructure específicos de los tipos públicos existentes Identity/Platform. Reutiliza el puente Marketplace; sin SQL privado cruzado, dependencia inversa/ciclo o reglas verticales en Shared. ArchitectureTest enumera los tres archivos/tipos nuevos. Solo cuatro módulos materializados. Sin evento/efecto externo nuevo que requiera outbox ni consumidor inventado.

## Migración local y preservación

2026_10_11_000009_create_local_draft_product_changes.php agrega exclusivamente catalog_local_draft_product_changes. FK simples/compuestas restrict propias, ULID/hashes válidos, schema_version=1, revisiones positivas/consecutivas y límites integer, unique catálogo/revisión y producto/revisión. Permite múltiples cambios por producto; índices cubren GET/FK sin índices preventivos o cambio de products. Sin updated_at/deleted_at o FK Identity; trigger rechaza UPDATE/DELETE y down es forward-only.

Aplicación local aprobada después de las suites completas: conteos y hashes de las 34 tablas anteriores idénticos, registros previos de migrations preservados, una tabla nueva vacía y un registro de migración. Total actual 35 tablas no-migrations; doce tablas Marketplace/Catalog vacías. Segunda ejecución solo de esa migración informa Nothing to migrate y conserva todo el estado. Sin fresh/reset/rollback o eliminación de volúmenes.

Un helper propio en storage/framework/cache, previamente confirmado ignorado y ausente, realizó la comparación; fue retirado por su ruta literal al terminar. Los positivos/carreras/instalaciones frescas fueron exclusivamente en bases temporales propias, eliminadas por las pruebas; verify-foundation confirmó que no queda ninguna. Sin datos, grants o usuarios positivos nuevos en desarrollo.

## Validaciones ejecutadas

| Verificación | Resultado |
|---|---|
| Pint | PASS, 296 archivos |
| PHPStan | PASS, nivel 8 |
| OpenAPI/JSON Schema oficiales | PASS, documento positivo y documento malformado rechazado; snapshots positivos/negativos anteriores y antes/después |
| Unit/Feature completas | 219 pruebas, 6572 aserciones; composer quality exit 0 |
| Integration completas | 110 pruebas, 2873 aserciones; composer test:integration -- --compact exit 0, 341.67 s |
| Total de suites completas | **329 pruebas, 9445 aserciones** |
| HTTP real focalizado | Cadena OTP → comercio → catálogo/alta → dos ediciones → historial/replay/cursor/revocación/logout; 444 aserciones |
| Concurrencia PostgreSQL | Dos procesos/locks reales, misma clave, claves distintas, productos distintos y edición/alta compitiendo |
| Rollback e integridad | Reversión tras cada escritura, constraints/FK/unique, máximos, append-only/forward-only y snapshots corruptos rechazados |
| Consultas y compatibilidad | GET histórico de una consulta propia; índice usado en EXPLAIN; todos los paths/schemas anteriores OpenAPI idénticos, dos paths/un schema adicionales, versión 1.9.0 |
| Foundation | PASS HTTP real, CSRF 419/sesión 401/privacy/correlación; doce tablas vacías, cuatro módulos y sin bases temporales remanentes |
| Runtime | PASS escritura/lectura/eliminación de archivos propios y liveness como www-data |
| Puertas negativas | Pest/Pint/PHPStan rechazan fallos controlados con exit 1; sus archivos propios fueron retirados |
| Migración local | PASS 34 tablas/conteos/hashes y migrations anteriores preservados, tabla nueva vacía y segunda ejecución sin cambios |
| Manifiesto y revisión | 44 archivos exactos: 27 implementación/pruebas y 17 documentos/contratos; revisión modular y de compatibilidad completada |
| UTF-8/LF, enlaces, secretos, maestro, diff | PASS: 373 enlaces/anclas locales, UTF-8 sin BOM/LF, credenciales locales ausentes, maestro original y git diff --check correcto |
| Git y entorno antes de publicar | main/HEAD/origin/main base ad395112e4f261ea4a5450068957eab1cf76e8d2; staging vacío, sin commit/push 03C y ocho servicios saludables |

Los totales suman solo las suites completas finales; no se suman reruns focalizados. La primera integración completa detectó una captura de excepción sin importación en la prueba nueva de guard: el adaptador había devuelto el 404 esperado. Se corrigió exclusivamente la prueba, pasó su regresión de nueve aserciones y se repitió la integración completa con éxito; código/contratos de ejecución permanecieron iguales. También se corrigió durante los focalizados el orden esperado de claves en restauración cerrada y se amplió el verificador de métodos/rutas para PUT. No quedan fallos pendientes.

## Manifiesto de archivos

- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductChangeAccess.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductChangeAccess.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductChangeStore.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductChangeStore.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductChangeWriter.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftProductChangeWriter.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftProductChange.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftProductChange.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/UpdateLocalDraftProduct.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/UpdateLocalDraftProduct.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductChangeInput.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductChangeInput.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductChangeOperation.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftProductChangeOperation.php)
- [apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php](../../../apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftProductChangeAccess.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftProductChangeAccess.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftProductChangeResourceResolver.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftProductChangeResourceResolver.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftProductChangeWriter.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftProductChangeWriter.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftProductChangeStore.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftProductChangeStore.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftProductChangeController.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftProductChangeController.php)
- [apps/api/database/migrations/2026_10_11_000009_create_local_draft_product_changes.php](../../../apps/api/database/migrations/2026_10_11_000009_create_local_draft_product_changes.php)
- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftProductChangeAccessTest.php](../../../apps/api/tests/Feature/LocalDraftProductChangeAccessTest.php)
- [apps/api/tests/Feature/LocalDraftProductChangeHttpTest.php](../../../apps/api/tests/Feature/LocalDraftProductChangeHttpTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/LocalDraftProductChangePostgresTest.php](../../../apps/api/tests/Integration/LocalDraftProductChangePostgresTest.php)
- [apps/api/tests/Integration/LocalDraftProductPostgresTest.php](../../../apps/api/tests/Integration/LocalDraftProductPostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/product-change-environment-worker.php](../../../apps/api/tests/Support/product-change-environment-worker.php)
- [apps/api/tests/Support/product-change-race-worker.php](../../../apps/api/tests/Support/product-change-race-worker.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/DraftProductChangeTest.php](../../../apps/api/tests/Unit/DraftProductChangeTest.php)
- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/schemas/catalog-local-draft-product-change-operation.v1.json](../../../docs/api/schemas/catalog-local-draft-product-change-operation.v1.json)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md](../../../docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/README.md](../../../docs/README.md)
- [docs/specifications/catalog.md](../../../docs/specifications/catalog.md)
- [docs/sprints/sprint-03/checkpoint-03a-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03a-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03a-plan.md](../../../docs/sprints/sprint-03/checkpoint-03a-plan.md)
- [docs/sprints/sprint-03/checkpoint-03b-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03b-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03b-plan.md](../../../docs/sprints/sprint-03/checkpoint-03b-plan.md)
- [docs/sprints/sprint-03/checkpoint-03c-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03c-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03c-plan.md](../../../docs/sprints/sprint-03/checkpoint-03c-plan.md)
- [docs/sprints/sprint-03/README.md](../../../docs/sprints/sprint-03/README.md)

## Límites y cierre

Solo ficticio local/testing; no borrado/restauración, catálogo renombrado/reasignación, categorías/tipo/restricciones, SKU/precio/stock/listings, publicación/activación, datos reales o frontend/dependencias nuevos. Consentimiento, MFA/administración sensible, retención y decisiones productivas DP-001/DP-002/DP-007/DP-012 y restantes siguen abiertas o vigentes con responsable/fecha preservados. No se avanzó a otro checkpoint.

La aprobación «aprobar y continuar» cubre el plan completo, código/pruebas y migración local vacía. Después de presentar el resultado terminado, el usuario respondió «autorizado» a la solicitud de commit/push/CI de 03C. Esa autorización explícita satisface [AGENTS.md](../../../AGENTS.md#restricciones), que dispone «No realizar commit, push, merge, publicación o despliegue sin aprobación explícita».

## Aprobación y publicación autorizadas

El 11 de octubre de 2026, tras presentar los 44 archivos y la validación completa de 329 pruebas/9445 aserciones, el usuario respondió «autorizado» a la solicitud de commit, push y CI de 03C. La autorización se registra antes del commit conforme a AGENTS.md:81. Comprende un commit del manifiesto revisado, push normal a origin/main y comprobación de Foundation CI sobre el SHA enviado.

La autorización se ejecutó con un commit del manifiesto y push normal; la verificación remota terminó correctamente. La CI de 03C se comprobó por su SHA exacto, sin usar la CI anterior de 03B como sustituto.

## Publicación confirmada

Commit b88add660b33b8a94c612ae56fe6275272634c90, padre ad395112e4f261ea4a5450068957eab1cf76e8d2: 44 archivos, 2438 inserciones y 20 eliminaciones. Push normal a origin/main autorizado mediante «autorizado». main/HEAD/origin/main/remote iguales y repositorio limpio al terminar; se conservan las 329 pruebas locales/9445 aserciones sin sumar reruns focalizados.

[Foundation CI 38120914021](https://github.com/nromero96/traepe/actions/runs/38120914021), job 114415279745: run y job completed/success, ambos SHA exactos b88add660b33b8a94c612ae56fe6275272634c90, rama main/evento push. Construcción/migración fresca, calidad/integración, reinicio/persistencia y limpieza: cuatro etapas completed/success, corroboradas al iniciar la continuidad solicitada por el usuario.

Entorno corroborado en esta continuidad: ocho servicios running/healthy, 35 tablas no-migrations y doce Marketplace/Catalog vacías; verify-foundation.php correcto. El usuario pidió «Continuar»: [plan completo 03D](checkpoint-03d-plan.md) preparado para variantes draft/colección/historial; DP-038 aprobada mediante «si apruebo» después de la preparación y antes de código o tablas. [Evidencia de preparación](checkpoint-03d-evidence.md).
