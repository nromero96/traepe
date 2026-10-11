# Evidencia de Checkpoint 02I — Alta y consulta de comercio con primera sucursal

Fecha: 10 de octubre de 2026. **Estado: implementado y validado localmente; commit/push/CI autorizados por el usuario, publicación en curso.**

Base main / origin/main a95999d4a35e31f6344b8434cfb7b273915de946, [02H publicado y CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38096356398). El usuario aprobó DP-034 y el [plan completo 02I](checkpoint-02i-plan.md) mediante «si apruebo», incluyendo implementación, pruebas y migración local vacía; commit/push/CI se reservan al cierre. Maestro v1.6 §§3.1, 9, 25, 32–36, 49–50, 55–60, 72 y 78–79; [ADR-002](../../architecture/decisions/ADR-002-data-identifiers-messaging.md), [ADR-007](../../architecture/decisions/ADR-007-marketplace-commercial-foundation.md), [especificación](../../specifications/marketplace-and-purchase.md) y [contrato API](../../api/README.md).

## Resultado implementado

POST crea Merchant y primera Branch draft en Market draft existente, con entrada cerrada y datos ficticios explícitos. IDs de servidor, nombres exactos validados, GeographicPoint antes del cast, timezone Etc/UTC, tax_id/risk_level=NULL. Cookie/CSRF, permisos create/read independientes y resolución server-side mediante contratos públicos Identity. GET devuelve snapshot original exclusivamente al creador con read vigente; otros actores/inexistente 404 indistinguible.

Idempotencia 24h por scope/actor/key usando puerto público Platform; callback reautoriza y bloquea el mercado. Claim, Merchant, Branch y diario cifrado se confirman juntos o revierten juntos. Replay conserva data/201, TTL y correlación original del diario, con correlación actual en respuesta; exige create vigente. Diario privado append-only con FK restrict, hashes/ULID/version válidos y snapshot Crypt cifrado, hash verificado y restauración cerrada. Sin evento público/efecto externo, outbox sin uso, dependencia nueva ni cambio de reglas Shared.

Rutas/bindings/guards solo local/testing, denegación anterior a sesión/DI/SQL incluso con caché local reutilizada; no-store/private y límites independientes de 30/minuto por actor. Errores/logs genéricos sin nombres, coordenadas, claves, cookies, SQL o datos privados Identity. OpenAPI 1.6.0 y [snapshot comercial v1](../../api/schemas/marketplace-local-draft-commerce-operation.v1.json), sin cambiar el contrato fixture v1.

## Validaciones

| Validación | Resultado |
|---|---|
| composer quality | PASS: Pint 223 archivos, PHPStan nivel 8 sin errores, OpenAPI oficial/snapshots v1 positivos y negativos, Unit/Feature |
| Unit/Feature | 176 pruebas, 3904 aserciones |
| composer test:integration | 79 pruebas, 1988 aserciones; PostgreSQL/PostGIS/Redis/colas/almacenamiento reales |
| Total sin sumar ejecuciones focalizadas | 255 pruebas, 5892 aserciones |
| Migración local aditiva 000006 | PASS: una tabla vacía y un registro; conteos y hashes de filas de las 29 tablas previas sin cambios |
| Segunda migrate local | Nothing to migrate; sin cambios |
| verify-foundation.php | PASS: HTTP real, CSRF/401, correlación/privacidad, siete tablas Marketplace vacías, Identity/grants/claims preservados, sin bases temporales remanentes |
| verify-runtime-permissions.php como www-data | PASS: escritura/lectura/limpieza propias y liveness |
| verify-quality-gates.php | PASS: Pest, Pint y PHPStan rechazan fallos controlados con exit 1; fixtures propios eliminados |
| Alcance/codificación/secretos/enlaces/master/diff | PASS: manifiesto exacto, UTF-8 sin BOM/LF, secretos ignorados no presentes, enlaces locales válidos, maestro SHA-256 intacto y git diff --check correcto |
| Estado antes de publicar | HEAD/origin/main a95999d4a35e31f6344b8434cfb7b273915de946; staging vacío, sin commit/push 02I |
| Servicios locales | Ocho servicios running/healthy |

Las pruebas de dominio incluyen forma cerrada, campos desconocidos/missing, nombres UTF-8 y límites, números/bools/strings/rangos/timezone, snapshot inmutable y fingerprint canónico con equivalencia numérica/signed zero sin normalizar nombres. HTTP comprueba autorización antes de entrada, capacidades independientes, límites por actor, contratos de respuesta/conflictos, errores/logs privados y cachés locales denegadas en production/staging antes de sesión/puertos.

PostgreSQL real verifica alta/replay/lectura propia, ownership 404, cifrado/hash/forma cerrada, TTL sin extensión, contextos FK/point/UTC, duplicados permitidos con claves distintas, aislamiento por actor, mismatch/expiry, ausencia de mercado, callback reautorizado, fallos parciales de Branch/diario con rollback total, restricciones/índices/append-only/forward-only y rechazo de snapshots corruptos. Dos procesos independientes con la misma clave producen un solo conjunto; claves distintas permiten los mismos nombres. Una base anterior con filas comerciales conserva hashes de todas las tablas al agregar solo el diario vacío; segunda ejecución sin cambios. La regresión de instalación 02G contempla su migración y la nueva migración dependiente, preservando filas anteriores.

El servidor HTTP real en una base/almacenamiento propios verifica OTP, cookie, CSRF ausente/inválido, create, replay con nueva correlación, read independiente/consulta original, inexistente, revocación/deny/bloqueo y logout/cookie replay. Canaries de nombres, teléfono, clave y token no aparecen en logs técnicos. Todos los casos positivos y fallos inyectados usan bases temporales propias eliminadas al terminar; no se ejecuta migrate:fresh/rollback ni se eliminan datos/volúmenes de desarrollo. El verificador temporal de migración local fue eliminado después del resultado correcto.

## Archivos modificados

43 archivos dentro del alcance aprobado. Componentes Domain/Application/Infrastructure/Interfaces con consumidor, provider y migración; pruebas y verificadores; contrato/snapshot; especificación, ADRs y evidencia. Sin dependencias/lockfiles, maestro, frontend, módulos futuros, configuración/secrets ni cambios ajenos.

- [apps/api/app/Modules/Marketplace/Application/Commerce/CreateLocalDraftCommerce.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/CreateLocalDraftCommerce.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/LocalDraftCommerceAccess.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/LocalDraftCommerceAccess.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/LocalDraftCommerceStore.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/LocalDraftCommerceStore.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/LocalDraftCommerceWriter.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/LocalDraftCommerceWriter.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/ReadLocalDraftCommerce.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/ReadLocalDraftCommerce.php)
- [apps/api/app/Modules/Marketplace/Domain/Commerce/DraftCommerceFailure.php](../../../apps/api/app/Modules/Marketplace/Domain/Commerce/DraftCommerceFailure.php)
- [apps/api/app/Modules/Marketplace/Domain/Commerce/DraftCommerceInput.php](../../../apps/api/app/Modules/Marketplace/Domain/Commerce/DraftCommerceInput.php)
- [apps/api/app/Modules/Marketplace/Domain/Commerce/DraftCommerceOperation.php](../../../apps/api/app/Modules/Marketplace/Domain/Commerce/DraftCommerceOperation.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Commerce/IdentityLocalDraftCommerceAccess.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Commerce/IdentityLocalDraftCommerceAccess.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Commerce/LocalDraftCommerceResourceResolver.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Commerce/LocalDraftCommerceResourceResolver.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PlatformLocalDraftCommerceWriter.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PlatformLocalDraftCommerceWriter.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PostgresLocalDraftCommerceStore.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PostgresLocalDraftCommerceStore.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php)
- [apps/api/app/Modules/Marketplace/Interfaces/Http/LocalDraftCommerceController.php](../../../apps/api/app/Modules/Marketplace/Interfaces/Http/LocalDraftCommerceController.php)
- [apps/api/database/migrations/2026_10_10_000006_create_marketplace_local_commerce_operations.php](../../../apps/api/database/migrations/2026_10_10_000006_create_marketplace_local_commerce_operations.php)
- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftCommerceAccessTest.php](../../../apps/api/tests/Feature/LocalDraftCommerceAccessTest.php)
- [apps/api/tests/Feature/LocalDraftCommerceHttpTest.php](../../../apps/api/tests/Feature/LocalDraftCommerceHttpTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/LocalDraftCommercePostgresTest.php](../../../apps/api/tests/Integration/LocalDraftCommercePostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/commerce-environment-worker.php](../../../apps/api/tests/Support/commerce-environment-worker.php)
- [apps/api/tests/Support/commerce-race-worker.php](../../../apps/api/tests/Support/commerce-race-worker.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/DraftCommerceTest.php](../../../apps/api/tests/Unit/DraftCommerceTest.php)
- [docs/README.md](../../../docs/README.md)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/schemas/README.md](../../../docs/api/schemas/README.md)
- [docs/api/schemas/marketplace-local-draft-commerce-operation.v1.json](../../../docs/api/schemas/marketplace-local-draft-commerce-operation.v1.json)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-002-data-identifiers-messaging.md](../../../docs/architecture/decisions/ADR-002-data-identifiers-messaging.md)
- [docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md](../../../docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/specifications/marketplace-and-purchase.md](../../../docs/specifications/marketplace-and-purchase.md)
- [docs/sprints/sprint-02/README.md](../../../docs/sprints/sprint-02/README.md)
- [docs/sprints/sprint-02/checkpoint-02h-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02h-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02i-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02i-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02i-plan.md](../../../docs/sprints/sprint-02/checkpoint-02i-plan.md)

## Límites

Solo ejercicio ficticio local/testing; desarrollo no recibe usuarios/grants/seeds/comercios positivos. Sin activación, registro real, fiscalidad/riesgo, catálogo/stock, frontend o dashboard administrativo sensible. MFA administrativa, retención/PII productiva, DP-001/DP-012 y demás decisiones productivas siguen pendientes. Este bloque no constituye MVP ni piloto operativo.

## Aprobación y publicación autorizadas

El usuario autorizó commit, push y CI de este resultado mediante «si autorizo» el 10 de octubre de 2026, después de presentar el cierre concreto de 43 archivos y 255 pruebas correctas. Cumple AGENTS.md: «No realizar commit, push, merge, publicación o despliegue sin aprobación explícita» y la reserva del plan 02I. Se publica únicamente el manifiesto indicado, sin despliegue productivo.

Antes del commit se verifican origen/base, staging, alcance exacto, secretos, enlaces, maestro y servicios. La CI debe corresponder al SHA publicado y completar build/migración, calidad/integración, reinicio/persistencia y limpieza. Este registro previo al commit no afirma una CI de 02I todavía; el SHA y run/job verificados se informarán en el cierre. La CI enlazada al comienzo pertenece exclusivamente a 02H.
