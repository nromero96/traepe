# Evidencia de Checkpoint 03E — Edición de variantes draft e historial

Fecha: 11 de octubre de 2026. **Estado: implementado y validado localmente. Plan completo aprobado mediante «Si apruebo» antes del código; migración local vacía/CHECK ampliado aplicada. Commit/push/CI de 03E autorizados mediante «si autorizo» después del resultado terminado; publicación en curso.**

## Fuente, aprobación y base

Se leyó primero [docs/README.md](../../README.md) y directamente el maestro v1.6, incluidas sus tablas y §§37, 52, 58, 60, 64 y 90 durante esta implementación. SHA-256 intacto: 85FEF02B0CBA9669EE37AAE4986BD7709634CB93987060435AF0A3759A802C2E. Especificación/modelo/API/seguridad/ADR-008/DP-039 se actualizaron después de recibir la aprobación y antes del código.

[Plan completo](checkpoint-03e-plan.md) y [DP-039 resuelta](../../architecture/decisions/pending-decisions.md#dp-039--edición-local-de-variantes-draft): «Si apruebo» autoriza todas las reglas, cinco campos/SKU inmutable local, permisos, revisiones, diario, colección/cursor v2, código/pruebas y una migración local aditiva vacía con ampliación del CHECK. Commit/push/CI quedaron reservados para presentar el resultado terminado.

Base main/HEAD/origin/main/remote 268bae47233697f659be7600a4335f5bff702c2c, working tree inicialmente limpio. [Cierre confirmado 03D](checkpoint-03d-evidence.md#publicación-confirmada): commit inicial 52a0850b6b7918ed66738ac1e6ff842ff3d934c4, corrección solo de formato 268bae47233697f659be7600a4335f5bff702c2c y [Foundation CI 38161448476](https://github.com/nromero96/traepe/actions/runs/38161448476)/job 114535038415, run/job SHA exactos y cuatro etapas correctas. Esa CI corresponde exclusivamente a 03D.

## Resultado implementado

PUT /api/v1/catalog/local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants/{variantPublicId} reemplaza cinco campos requeridos name/size/unit/barcode/attributes de una variante draft propia con procedencia 03D. SKU no se admite en la entrada y se conserva del perfil bloqueado. If-Match fuerte de catálogo y clave idempotente exacta; estado/identidades/relaciones/created_at/textos de padres se conservan.

Claim → Merchant → Catalog → Product → Variant en una conexión/transacción. Catálogo/producto/variante incrementan exactamente una revisión. Versión antigua/máxima precede a no_changes; igualdad canónica, errores después de cada escritura o fallo en cualquiera de las reautorizaciones revierten campos/revisiones/timestamps, diario y claim. La comprobación final de permiso y restauración del resultado se realizan antes de confirmar. No se promete serialización con futura administración de grants.

Diario propio catalog_local_draft_variant_changes: cifrado/hash, snapshot original antes/después v1, tres pares de revisión/metadata, FK compuestas restrict, ULIDs/hashes/unique, created_at UTC y trigger append-only. GET /api/v1/catalog/local-draft-variant-change-operations/{operationPublicId} exige read independiente/autoría y consulta solo ese diario; no reconstruye perfiles. Replay 200 requiere update vigente, conserva el original y TTL de 24 horas sin extensión/reuso, incluso tras nuevas ediciones o borrado de perfiles.

Dos capacidades independientes catalog.local.draft_variant.update y catalog.local.draft_variant_change.read, recursos técnicos propios FBH/FBJ y scope FB2 completos del plan. Permisos anteriores no conceden estas rutas; read_current/FBF existente sigue protegiendo la colección. Cookie/CSRF, DP-026, buckets 30/minuto separados, correlación actual, no-store/private y errores/logs sanitizados.

Colección actual de variantes y cursor evolucionan expresamente a v2, con revisión real y meta.schema_version=2 incluso vacía; DTO actual separado del de creación original v1. Keyset/una sentencia/índice existente, contexto/TTL original 15 minutos y errores de reinicio comprobados. No se esconden filas editadas; cambios fuera de página/producto y mutaciones 03B/C/D/E invalidan cursores anteriores. Los cursores v1 se rechazan 422 y se reinicia la consulta.

OpenAPI 2.0.0 declara la incompatibilidad del único GET previo que cambia. Comparación semántica con la base: 22 paths anteriores conservados, únicamente ese GET evolucionado, dos paths nuevos, 29 componentes anteriores intactos y once archivos de esquemas anteriores idénticos. Página v1 queda histórica; altas/historias/replays 03D conservan el snapshot original v1/variante versión 1. Nuevos [cambio v1](../../api/schemas/catalog-local-draft-variant-change-operation.v1.json) y [página v2](../../api/schemas/catalog-local-draft-variants-page.v2.json).

ArchitectureTest enumera dieciséis consumidores Catalog (trece previos más tres aprobados), mismos tipos públicos Identity/Platform; puente Marketplace existente. Domain/Application puros, cuatro módulos materializados, sin SQL privado cruzado/ciclos/reglas verticales en Shared/dependencias nuevas.

## Validaciones ejecutadas

| Comprobación | Resultado |
|---|---|
| Suite completa Unit/Feature | PASS: 247 pruebas, 9249 aserciones; 84.33 s |
| Suite completa Integration real | PASS: 133 pruebas, 3733 aserciones; 428.91 s |
| Total, sin sumar focos/reruns | **380 pruebas / 12982 aserciones** |
| Pint completo | PASS: 343 archivos, caché nueva pint-03e-final-20261011-d.json |
| PHPStan | PASS: nivel 8, sin errores |
| OpenAPI oficial + JSON Schema | PASS: documento válido/negativo; todos los esquemas previos y cambio v1/página v2, positivos/negativos |
| Compatibilidad con la base | PASS: solo un GET previo cambia; demás operaciones/componentes/esquemas previos intactos |
| HTTP real | PASS dentro de Integration: OTP/comercio/catálogo/productos/variantes/ediciones sucesivas/colección v2/historias/replay/CSRF/revocación/logout/logs |
| PostgreSQL/races | PASS dentro de Integration: misma clave/replay/mismatch, claves distintas, variantes/productos/catálogos distintos y carreras con 03B/C/D/E |
| Rollback/restauración | PASS: todos los puntos de escritura, ambas reautorizaciones, tres revisiones/max/no_changes/cifrado/hash/forma/metadata/mapa/append-only |
| Migración fresca/aditiva/idempotente | PASS en DB propias: precondición CHECK exacta, filas anteriores conservadas, CHECK ampliado y segunda ejecución sin cambios |
| Foundation local | PASS: HTTP/dependencias/privacidad, rechazos anónimos preservan datos/grants/claims, quince tablas Marketplace/Catalog vacías y sin DB temporales |

Durante los focos se corrigió el alcance de la transacción para incluir la última reautorización y restauración. También se adaptaron los validadores/mocks de colección a v2 y el comprobador de patrones OpenAPI al delimitador presente en barcode ASCII. Todos los fallos detectados quedaron resueltos antes de las suites completas; tras corregir dos mensajes genéricos que aún nombraban producto, el foco HTTP 03E volvió a pasar (6/151), junto con Pint completo/PHPStan. Estos reruns no se suman al total.

## Migración local aplicada y preservación

Se ejecutó exclusivamente 2026_10_11_000011_create_local_draft_variant_changes.php después de las suites completas, mediante helper propio ignorado eliminado al terminar. Sin fresh/reset/rollback/seeds, creación de usuarios/grants/datos positivos o eliminación de volúmenes.

Prueba estructurada nativa PostgreSQL: counts/hash de todas las filas de las 37 tablas no-migrations anteriores idénticos y filas migrations previas preservadas; exactamente una tabla nueva vacía y una fila de migración. Ahora 38 tablas no-migrations y quince Marketplace/Catalog vacías. Solo product_variants_version_check cambió de CHECK ((version = 1)) a CHECK ((version >= 1)); todos los demás constraints anteriores permanecen idénticos. Segundo migrate: Nothing to migrate, ninguna fila/hash cambió. Ocho servicios running/healthy, verify-foundation correcto y cero DB temporales propias remanentes.

## Límites y riesgos pendientes

Solo ficticio local/testing, datos positivos en DB temporales propias. La colección actual v2 requiere reiniciar cursores v1; no se habilita un rollout de producción. Migración forward-only; un down destructivo exige plan de datos revisado. Sin cambio/reuso de SKU, borrado/restauración/reasignación, precio/stock/listings, venta/discovery/activación, frontend/dependencias/datos reales o excepción MFA. El diario local sin nuevo efecto externo no decide excepciones de outbox para operación real. DP-001/DP-002/DP-007/DP-012 y demás decisiones productivas conservan responsables/fechas y permanecen abiertas.

## Aprobación y publicación autorizadas

Después de presentar el resultado completo de 51 archivos, 380 pruebas/12982 aserciones y preservación de la migración local, el usuario autorizó crear el commit, hacer push normal a origin/main y verificar Foundation CI mediante **«si autorizo»**, el 11 de octubre de 2026.

[AGENTS.md](../../../AGENTS.md#restricciones) exige «No realizar commit, push, merge, publicación o despliegue sin aprobación explícita». La autorización de 03E se recibió sobre este resultado terminado y no reutiliza la publicación de 03D. Base al aprobar: main/HEAD/origin/main/remote 268bae47233697f659be7600a4335f5bff702c2c, staging vacío.

Publicación en curso: commit del manifiesto exacto, push normal y comprobación del SHA en main/remoto. Foundation CI debe corresponder a ese SHA/main/evento push, con run/job completed/success y build/migración, calidad/integración, reinicio/persistencia y limpieza correctos. Los conteos de las suites se comprobarán en los logs; aún no se declara CI de 03E correcta.

## Manifiesto exacto

51 archivos respecto de la base 268bae47233697f659be7600a4335f5bff702c2c: 21 de aplicación/migración, 14 de pruebas/soporte y 16 de documentación/contratos. Incluye la reconciliación documental del cierre 03D ya publicada; no agrega cambios funcionales a aquel bloque.

### Aplicación y migración

- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantChangeAccess.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantChangeAccess.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantChangeStore.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantChangeStore.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantChangeWriter.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantChangeWriter.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftVariantChange.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftVariantChange.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/UpdateLocalDraftVariant.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/UpdateLocalDraftVariant.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantChangeInput.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantChangeInput.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantChangeOperation.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantChangeOperation.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantCurrentProfile.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantCurrentProfile.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantCursor.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantCursor.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantData.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantData.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantEditableData.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantEditableData.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantPage.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantPage.php)
- [apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php](../../../apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftVariantChangeAccess.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftVariantChangeAccess.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftVariantChangeResourceResolver.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftVariantChangeResourceResolver.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftVariantChangeWriter.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftVariantChangeWriter.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftVariantChangeStore.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftVariantChangeStore.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftVariantStore.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftVariantStore.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftVariantChangeController.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftVariantChangeController.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftVariantController.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftVariantController.php)
- [apps/api/database/migrations/2026_10_11_000011_create_local_draft_variant_changes.php](../../../apps/api/database/migrations/2026_10_11_000011_create_local_draft_variant_changes.php)

### Pruebas y soporte

- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftVariantChangeAccessTest.php](../../../apps/api/tests/Feature/LocalDraftVariantChangeAccessTest.php)
- [apps/api/tests/Feature/LocalDraftVariantChangeHttpTest.php](../../../apps/api/tests/Feature/LocalDraftVariantChangeHttpTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/LocalDraftVariantChangePostgresTest.php](../../../apps/api/tests/Integration/LocalDraftVariantChangePostgresTest.php)
- [apps/api/tests/Integration/LocalDraftVariantPostgresTest.php](../../../apps/api/tests/Integration/LocalDraftVariantPostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/variant-change-environment-worker.php](../../../apps/api/tests/Support/variant-change-environment-worker.php)
- [apps/api/tests/Support/variant-change-race-worker.php](../../../apps/api/tests/Support/variant-change-race-worker.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/DraftVariantChangeTest.php](../../../apps/api/tests/Unit/DraftVariantChangeTest.php)
- [apps/api/tests/Unit/DraftVariantTest.php](../../../apps/api/tests/Unit/DraftVariantTest.php)

### Documentación y contratos

- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/schemas/catalog-local-draft-variant-change-operation.v1.json](../../../docs/api/schemas/catalog-local-draft-variant-change-operation.v1.json)
- [docs/api/schemas/catalog-local-draft-variants-page.v2.json](../../../docs/api/schemas/catalog-local-draft-variants-page.v2.json)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md](../../../docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/README.md](../../../docs/README.md)
- [docs/specifications/catalog.md](../../../docs/specifications/catalog.md)
- [docs/sprints/sprint-03/checkpoint-03d-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03d-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03d-plan.md](../../../docs/sprints/sprint-03/checkpoint-03d-plan.md)
- [docs/sprints/sprint-03/checkpoint-03e-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03e-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03e-plan.md](../../../docs/sprints/sprint-03/checkpoint-03e-plan.md)
- [docs/sprints/sprint-03/README.md](../../../docs/sprints/sprint-03/README.md)
