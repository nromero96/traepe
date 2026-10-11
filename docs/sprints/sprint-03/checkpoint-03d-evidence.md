# Evidencia de Checkpoint 03D — Variantes draft

Fecha: 11 de octubre de 2026. **Estado: plan completo aprobado mediante «si apruebo»; implementado y validado localmente. Migración aditiva de dos tablas vacías aplicada y las 35 anteriores preservadas. Commit/push/CI autorizados mediante «Autorizado y continuar»; publicación en curso.**

## Base y aprobación

El usuario pidió «Continuar» tras [03C publicado](checkpoint-03c-evidence.md#publicación-confirmada). Base inicial/final main/HEAD/origin/main/remote b88add660b33b8a94c612ae56fe6275272634c90; working tree inicialmente limpio y staging vacío durante 03D. [Foundation CI 38120914021](https://github.com/nromero96/traepe/actions/runs/38120914021), job 114415279745: run y job completed/success, ambos SHA exactos y cuatro etapas correctas corroboradas al preparar 03D. Esa CI corresponde a 03C, no valida una publicación de 03D.

Se verificó primero la preparación: nueve documentos/246 enlaces-anclas, maestro/secretos/UTF-8/LF/diff/base/servicios, 35 tablas y doce Marketplace/Catalog vacías. [DP-038](../../architecture/decisions/pending-decisions.md#dp-038--variantes-draft-locales) y [plan completo](checkpoint-03d-plan.md) registrados antes del código. El usuario respondió «si apruebo», autorizando todas las reglas, implementación/pruebas y aplicación local aditiva de dos tablas vacías; después pidió «continua». La aprobación se documentó en DP-038/ADR-008/especificación/modelo/API/seguridad antes del código. Publicación reservada para el resultado terminado.

Maestro leído directamente con tablas §§9, 25, 32–37, 49–50, 52–53, 56–60, 64, 72, 78–79, 90, 95 y 112, y secciones relevantes revalidadas tras aprobación. Hash original: 85FEF02B0CBA9669EE37AAE4986BD7709634CB93987060435AF0A3759A802C2E. [ADR-008/03D](../../architecture/decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03d), [especificación](../../specifications/catalog.md#variantes-draft-y-consulta-por-producto--03d), [modelo](../../architecture/data-model/README.md#variantes-draft-y-diario-de-alta--03d), [API](../../api/README.md#variantes-draft-y-colección-local--03d--openapi-1100) y [seguridad](../../architecture/security/README.md#capacidades-de-variantes-locales--03d).

## Resultado implementado

POST /local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants crea una variante draft propia y devuelve su operación original 201, también en replay. GET del mismo path recorre su colección actual; GET /local-draft-variant-operations/{operationPublicId} consulta el snapshot original propio. Tres capacidades/buckets independientes; sesión cookie, CSRF de mutación, DP-026, reautorización y scope/recursos del plan, sin ampliar permisos anteriores.

Entrada cerrada con seis campos: name, sku, size, unit, barcode y attributes. SKU ASCII exacto, único por comercio incluyendo soft-deleted; etiquetas opacas sin conversión/importes y mapa JSON objeto 0–20 pares de strings/claves acotados. {} se conserva en input/snapshot/JSONB y [] se rechaza. Domain valida tipos/límites/Unicode/C0/DEL/referencias; PostgreSQL añade CHECK/function immutable, incluida comprobación de bytes ASCII para SKU/barcode/claves. Fingerprint usa pares ordenados de primitivas/arrays, sin cambiar el contrato Platform que rechaza objetos PHP.

Catalog acredita propiedad/procedencia 03A/03B y padres draft/no borrados/type NULL; primera alta confirma Merchant propio/02I mediante contrato público existente. Recursos ajenos/manuales/cruzados/ausentes/no elegibles reciben 404 indistinguible. Lectura actual incluye solo variantes draft/no borradas propias con diario 03D. Guards anteriores a sesión/CSRF/DI/SQL, también en nuevos procesos production/staging con ruta cacheada local.

Una transacción serializa claim → Merchant → Catalog → Product. Compara If-Match, máximos de ambas versiones y luego SKU; revisión antigua prevalece. Inserta variante versión 1, incrementa catálogo/producto +1 y updated_at UTC conservando textos/identidad/relaciones, e inserta diario cifrado junto al claim. Todos los fallos parciales revierten filas/versiones/timestamps/diario/claim. TTL 24h original, sin extensión/reuso; replay requiere create vigente sin read o reevaluar perfiles y conserva snapshot/código 201 con correlación actual.

Carreras reales de dos procesos: misma clave/entrada deja una operación/replay; distintas claves/misma revisión (mismo producto o productos distintos), alta 03B o edición 03C frente a variante dejan un éxito/conflicto. Mismo SKU y comercio en catálogos diferentes deja un éxito/sku_conflict; distintos SKU en catálogos distintos completan ambos. Bloqueos PostgreSQL, sin Redis/memoria como autoridad ni claims residuales.

Colección keyset con revisión de catálogo/producto y página en una sentencia propia, limit+1 e índice consumido. Cursor Crypt autenticado v1 ligado a actor/padres/revisiones/limit/id interno/expiry UTC, TTL original 15 minutos. Manipulación/contexto/expiry/revisión rechazan; altas/ediciones del catálogo obligan a reiniciar páginas. Cursores de productos 03B se invalidan también; sus perfiles muestran product.version nuevo sin cambiar el contrato. Historial/replay leen solo diario propio, cifrado/hash/forma/mapa/referencias/revisiones contra metadata, sin reconstruir perfiles. Snapshots 03A/03B/03C siguen originales.

Domain/Application puros, puertos propios, controlador delgado, tres consumidores Infrastructure adicionales enumerados por ArchitectureTest y puente Marketplace reutilizado. Trece consumidores Catalog de los mismos tipos públicos aprobados, cuatro módulos materializados; sin SQL privado cruzado/ciclo, Shared vertical, contrato externo o dependencia nueva. Diario audita el ejercicio ficticio sin evento/efecto externo nuevo que requiera outbox o consumidor inventado.

[OpenAPI 1.10.0](../../api/openapi.yaml) añade dos paths/tres operaciones, tres componentes y dos esquemas v1 cerrados de [operación](../../api/schemas/catalog-local-draft-variant-operation.v1.json)/[página](../../api/schemas/catalog-local-draft-variants-page.v1.json). Todos los paths/componentes previos comparados semánticamente con HEAD 03C: sin cambios. OpenAPI 3.0 expresa el mapa de valores acotado y describe su patrón de claves; JSON Schema v1/servidor/DB validan también esas claves. Referencias y aritmética se verifican en Domain/DB además del esquema estructural. No bigint, actor, hashes, claves, ciphertext o tiempos técnicos en público; no-store/private y correlación.

## Persistencia local

2026_10_11_000010_create_local_draft_variants.php agrega exclusivamente product_variants y catalog_local_draft_variant_operations. FK propias compuestas/restrict, unique SKU por comercio, índices keyset/FK, pares de versiones positivos/consecutivos/unique, hashes/ULIDs/ciphertext, timestamps UTC y trigger append-only del diario. down rechaza rollback sin plan revisado; sin cambios a constraints/filas anteriores.

Aplicación local después de suites completas: 35 tablas anteriores conservadas por conteo y hash de todas sus filas; filas previas de migrations iguales. Exactamente dos tablas vacías/una fila de migración nuevas, 37 tablas no-migrations totales, catorce Marketplace/Catalog vacías. Segunda ejecución explícita: Nothing to migrate, sin cambios. Helper propio ignorado validado dentro del workspace y retirado al terminar. Sin fresh/reset/rollback de desarrollo, seeds, grants/usuarios positivos o eliminación de volúmenes.

## Validaciones finales

| Comprobación | Resultado |
|---|---|
| Pint | PASS, 322 archivos |
| PHPStan | PASS, nivel 8 |
| Contratos | PASS, esquema oficial OpenAPI y esquemas v1 positivos/negativos, mapa/Unicode/SKU/revisión/cursor/forma cerrada |
| Unit/Feature completas | 233 pruebas / 7958 aserciones, exit 0 |
| Integration completa | 122 pruebas / 3302 aserciones, exit 0; 885.62 s |
| Total de suites completas | 355 pruebas / 11260 aserciones |
| Flujo HTTP real focal | 1 prueba / 646 aserciones; incluido en Integration, no sumado otra vez |
| Concurrencia y rollback | Dos procesos reales, todos los escenarios aprobados; falla tras cada escritura revierte el estado completo |
| Migración local | 35 tablas previas preservadas, dos nuevas vacías/una fila migrations, segundo migrate sin cambios |
| verify-foundation.php | PASS, HTTP/dependencias/anonimato/CSRF/privacidad, catorce tablas vacías y datos/grants/claims preservados; sin DB temporal remanente |
| Revisión de alcance | 51 archivos: 34 implementación/pruebas y 17 documentación/contratos; compatibilidad/límites modulares revisados |
| Revisión documental y secretos | PASS: manifiesto exacto de 51 archivos, 395 enlaces/anclas locales, UTF-8 sin BOM/LF, sin credenciales locales, maestro intacto y git diff --check |
| Git y servicios antes de publicar | main/HEAD/origin/main/remote base 03C, staging vacío, sin commit/push 03D; ocho servicios running/healthy |

Totales solo de suites completas finales, sin sumar focalizados/reruns. Se corrigieron en desarrollo el harness HTTP aislado (entorno testing/cache-limiter array, conservando CSRF real) y la conversión del componente nuevo de colección que omitía id; regresiones/contratos/suites completas posteriores correctos. PHPStan/Pest encontraron cachés generadas por root sin escritura para www-data: se corrigió únicamente su propietario dentro de las carpetas de caché ignoradas y se repitieron los checks con ese usuario. No dependencias instaladas ni fallos pendientes.

## Manifiesto de archivos

- [apps/api/app/Modules/Catalog/Application/Drafts/CreateLocalDraftVariant.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/CreateLocalDraftVariant.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ListLocalDraftVariants.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ListLocalDraftVariants.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantAccess.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantAccess.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantCursorCodec.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantCursorCodec.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantStore.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantStore.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantWriter.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/LocalDraftVariantWriter.php)
- [apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftVariant.php](../../../apps/api/app/Modules/Catalog/Application/Drafts/ReadLocalDraftVariant.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantCursor.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantCursor.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantData.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantData.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantInput.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantInput.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantOperation.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantOperation.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantPage.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantPage.php)
- [apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantProfile.php](../../../apps/api/app/Modules/Catalog/Domain/Drafts/DraftVariantProfile.php)
- [apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php](../../../apps/api/app/Modules/Catalog/Infrastructure/CatalogServiceProvider.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/CryptLocalDraftVariantCursorCodec.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/CryptLocalDraftVariantCursorCodec.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftVariantAccess.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/IdentityLocalDraftVariantAccess.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftVariantResourceResolver.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/LocalDraftVariantResourceResolver.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftVariantWriter.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PlatformLocalDraftVariantWriter.php)
- [apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftVariantStore.php](../../../apps/api/app/Modules/Catalog/Infrastructure/Drafts/PostgresLocalDraftVariantStore.php)
- [apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftVariantController.php](../../../apps/api/app/Modules/Catalog/Interfaces/Http/LocalDraftVariantController.php)
- [apps/api/database/migrations/2026_10_11_000010_create_local_draft_variants.php](../../../apps/api/database/migrations/2026_10_11_000010_create_local_draft_variants.php)
- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftVariantAccessTest.php](../../../apps/api/tests/Feature/LocalDraftVariantAccessTest.php)
- [apps/api/tests/Feature/LocalDraftVariantHttpTest.php](../../../apps/api/tests/Feature/LocalDraftVariantHttpTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/LocalDraftProductChangePostgresTest.php](../../../apps/api/tests/Integration/LocalDraftProductChangePostgresTest.php)
- [apps/api/tests/Integration/LocalDraftVariantPostgresTest.php](../../../apps/api/tests/Integration/LocalDraftVariantPostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/variant-environment-worker.php](../../../apps/api/tests/Support/variant-environment-worker.php)
- [apps/api/tests/Support/variant-race-worker.php](../../../apps/api/tests/Support/variant-race-worker.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/DraftVariantTest.php](../../../apps/api/tests/Unit/DraftVariantTest.php)
- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/schemas/catalog-local-draft-variant-operation.v1.json](../../../docs/api/schemas/catalog-local-draft-variant-operation.v1.json)
- [docs/api/schemas/catalog-local-draft-variants-page.v1.json](../../../docs/api/schemas/catalog-local-draft-variants-page.v1.json)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md](../../../docs/architecture/decisions/ADR-008-catalog-local-draft-foundation.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/README.md](../../../docs/README.md)
- [docs/specifications/catalog.md](../../../docs/specifications/catalog.md)
- [docs/sprints/sprint-03/checkpoint-03b-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03b-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03c-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03c-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03c-plan.md](../../../docs/sprints/sprint-03/checkpoint-03c-plan.md)
- [docs/sprints/sprint-03/checkpoint-03d-evidence.md](../../../docs/sprints/sprint-03/checkpoint-03d-evidence.md)
- [docs/sprints/sprint-03/checkpoint-03d-plan.md](../../../docs/sprints/sprint-03/checkpoint-03d-plan.md)
- [docs/sprints/sprint-03/README.md](../../../docs/sprints/sprint-03/README.md)

## Límites y cierre

Solo ficticio local/testing, datos positivos exclusivamente en bases temporales propias y desarrollo vacío. Sin edición/borrado de variante, SKU activado, precio/stock/listing, disponibilidad/venta, categorías/tipos/restricciones, frontend/dependencias o datos reales. DP-001/DP-002/DP-007/DP-012 y demás productivas conservan responsables/fechas y siguen abiertas; no exención de MFA sensible real. No se avanzó a otro checkpoint.

«si apruebo» cubrió el plan completo, implementación/pruebas y migración local vacía; la publicación quedó reservada al resultado terminado. [AGENTS.md](../../../AGENTS.md#restricciones) dispone «No realizar commit, push, merge, publicación o despliegue sin aprobación explícita».

## Aprobación y publicación autorizadas

Después de presentar la evidencia terminada y solicitar commit, push a origin/main y comprobación de Foundation CI del SHA exacto sobre estos 51 archivos, el usuario respondió **«Autorizado y continuar» el 11 de octubre de 2026**. Autoriza publicar el resultado completo sin alterar el alcance ficticio local ni las decisiones productivas pendientes.

Publicación inicial: commit 52a0850b6b7918ed66738ac1e6ff842ff3d934c4, manifiesto exacto de 51 archivos, 3786 inserciones/18 eliminaciones y padre b88add660b33b8a94c612ae56fe6275272634c90; push normal a origin/main, sin force/reset, referencias coincidentes y working tree limpio. El cierre exige también run/job Foundation CI del SHA final publicado completed/success, con build/migración, calidad/integración, reinicio/persistencia y limpieza correctos. La CI de 03C no sustituye esta validación.

## Corrección de formato detectada por CI

[Foundation CI inicial 38160766560](https://github.com/nromero96/traepe/actions/runs/38160766560), job 114533007109, del SHA exacto 52a0850b6b7918ed66738ac1e6ff842ff3d934c4: build/migración y limpieza correctos; Check falló en Pint por dos archivos, por lo que no ejecutó análisis/suites ni reinicio. El check local previo había informado PASS; una nueva caché de Pint reprodujo los errores de espaciado en LocalDraftVariantPostgresTest.php y variant-race-worker.php.

Se corrigió exclusivamente el formato de ambos archivos. Pint completo con nueva caché: 322 archivos correctos; PHPStan nivel 8 y git diff --check correctos. Sin cambios de comportamiento, reglas, dependencias, migraciones o datos; los totales de suites completas locales siguen siendo 355/11260 y no se suman reruns. Se publica la corrección con esta evidencia dentro de la autorización de cierre recibida, y se exige una nueva Foundation CI completa del SHA final antes de declarar cerrado 03D.
