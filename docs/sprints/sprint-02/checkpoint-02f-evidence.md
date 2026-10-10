# Evidencia de Checkpoint 02F — Fixtures transaccionales locales

Fecha: 10 de octubre de 2026. **Estado: checkpoint aprobado/publicado en 3c9448c37c3736a155cd794b3f9bd2861e557796; CI completa correcta.**

## Autorización y trazabilidad

El usuario aprobó el [plan 02F](checkpoint-02f-plan.md) mediante «aprobado, continuar.» el 10 de octubre de 2026 y pidió continuar durante la ejecución. [DP-031](../../architecture/decisions/pending-decisions.md#dp-031--primera-escritura-transaccional-de-fixtures-geográficos) registra la resolución. Maestro v1.6 §§33–34.1, 49–50, 55–56 y 58–60; DP-026 a DP-030; ADR-002/ADR-006. La aprobación permite implementar y validar, y aplicar una nueva tabla vacía localmente; el plan reserva commit/push/CI para aprobación final.

Base de este checkpoint: main / origin/main en `79d4d3d76c3a4c60075aabf596d3207be333d42a`, 02E publicado con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38084025841). La evidencia de 02E registra ahora esa publicación. El maestro conserva SHA-256 `85FEF02B0CBA9669EE37AAE4986BD7709634CB93987060435AF0A3759A802C2E`.

## Resultado

POST `/api/v1/marketplace/local-draft-fixtures`, local/testing, admite solo JSON con fixture_profile=synthetic-origin-a-v1 o synthetic-origin-b-v1. Sesión cookie, CSRF, capacidad create independiente de read, actor/scope/recurso/reloj de servidor y DP-026 íntegra. Gate antes de sesión/CSRF/DI fuera del entorno permitido, incluso con caché real reutilizada; bindings positivos ausentes. Rate limit propio 30/minuto por actor, no-store/private, correlación y errores sanitizados.

El callback de Marketplace crea/reutiliza país ZZ solo si name/currency coinciden, crea un mercado draft y tres zonas draft/fixture con ULIDs nuevos y geography(MultiPolygon,4326), y registra la operación en la misma transacción que el claim del puerto público Platform. Los WKT y prioridades permanecen exactamente los del fixture inline 02A. No cambia las sondas anteriores, ni activa mercados.

Idempotencia exacta de scope/actor/key, fingerprint profile/schema_version, expiry técnico 24 horas desde primer claim, sin extensión/purga/reuso automático. Payload distinto: 409 idempotency_mismatch; expiración: 409 idempotency_expired; país incompatible: 409 fixture_country_conflict. Se revalida acceso dentro del callback y antes de devolver el snapshot, también en replay. El replay retorna el mismo data y 201, con correlación de solicitud actual; conserva la correlación original en el historial.

Tabla privada marketplace_local_fixture_operations con FK restrict/market único, ULIDs y hashes válidos, schema_version=1 y perfil cerrado, snapshot jsonb, created_at UTC e índices. Trigger propio rechaza UPDATE/DELETE. Sin updated_at/soft delete, key/body crudos, PII o IDs internos en respuesta. Snapshot cerrado validado por DTO antes de persistir y al restaurar. Domain/Application puros; adaptadores usan únicamente contratos públicos Identity/Platform de la lista exacta en ArchitectureTest. Sin consultas privadas cruzadas ni sustitución de resolvers previos.

No hay evento público ni efecto externo/asíncrono de esta creación técnica; conforme al plan aprobado, no agrega outbox ni consumidor sin uso. Registro/auditoría append-only se confirma junto al conjunto.

## Validaciones ejecutadas

| Validación | Resultado |
|---|---|
| Pint | 191 archivos correctos |
| PHPStan nivel 8 | Sin errores |
| OpenAPI 3.0.3 / API 1.5.0 | Esquema oficial correcto; documento mal formado rechazado |
| Snapshot Marketplace v1 | Forma cerrada válida; extras, perfil/referencia inválidos y zonas repetidas rechazados por validador existente de Composer |
| Unit / Feature completas | 150 pruebas, 3011 aserciones |
| Integration completa en PostgreSQL/PostGIS | 57 pruebas, 1353 aserciones |
| Total | **207 pruebas, 4364 aserciones** |
| Fundación HTTP/DB | Correcta; cuatro tablas Marketplace vacías, sin bases temporales remanentes |
| Runtime como usuario FPM | Escritura/lectura/eliminación de archivos propios y liveness correctas |
| Puertas de calidad negativas | Pest/Pint/PHPStan rechazan fallos controlados con exit 1 |
| Revisión de alcance/diff/secretos/UTF-8 | 41 archivos previstos, diff correcto, sin secretos locales ni firmas de credencial, sin staging |
| Enlaces locales / maestro | 141 enlaces correctos; hash del maestro conservado |
| Servicios | api, nginx, postgres, redis, minio, mailpit, horizon y reverb saludables |

Comandos en Docker con .env.docker ignorado: `composer quality`, `composer test:integration`, `php tests/Support/verify-foundation.php`, `verify-runtime-permissions.php` y `verify-quality-gates.php`. Después de precisar el aislamiento por actor y las comprobaciones de tipos, longitudes, campos obligatorios y FK, se repitió Integration completa: 57 pruebas/1353 aserciones. Pint, PHPStan y lint del contrato final volvieron a pasar; arquitectura y contratos HTTP también se verificaron sobre el resultado final. La reordenación incidental de respuestas antiguas de OpenAPI se eliminó, verificando equivalencia semántica del contrato; cambios anteriores conservados.

Cobertura relevante:

- JSON crudo cerrado, claves ausentes/espacios/control/no ASCII/longitud, medios, entradas ajenas, permiso antes de validación/claim, errores sin reflexión, privacidad de body/key/SQL, correlación, no-store y limitador independiente por actor.
- DP-026 exacta: permiso read no concede create, deny/expiración/bloqueo/default deny, referencias de servidor y resolver global de Identity conservado. No-local deniega antes del directorio.
- Migración desde cero en bases propias: tabla vacía, índices/tipos, ULIDs/hashes/profile/schema/snapshot, unicidad/FK, append-only y down forward-only; segunda migrate sin cambios.
- Primer conjunto 1 país/1 mercado/3 zonas/1 operación/1 claim; siguientes reutilizan solo país. Snapshot/TTL/correlación original conservados en replay, aun cambiando el nombre del mercado. Aislamiento entre actores y scopes; mismatch/expiry/revocación no crean nuevas filas.
- Dos procesos independientes compiten con locks mantenidos durante el callback: misma clave produce una operación/conjunto; claves distintas producen dos conjuntos y un país compartido.
- Fallo inyectado en la zona B y antes de insertar el historial: rollback de todo, incluido país nuevo y claim; país preexistente conservado. Revalidación denegada dentro del callback revierte también el claim reservado.
- HTTP real sobre base temporal propia: CSRF, OTP/cookie, create/replay/mismatch, create sin read denegado, read concedido permite selected/outside/ambiguous y borde de hueco; deny, bloqueo, revocación y logout/cookie replay denegados sin nuevos conjuntos.
- Procesos production/staging con/sin caché local real: 404 y sin ejecutar sesión/CSRF ni resolver acceso/writer/store.

## Aplicación local aditiva

Después de probar la migración en bases temporales, un verificador temporal ignorado tomó conteos de todas las tablas existentes, ejecutó migrate y comparó: únicamente se agregó la nueva tabla vacía y un registro de migración. Los conteos previos se preservaron, incluidos Identity y Platform. Segunda ejecución: Nothing to migrate. El verificador se eliminó mediante su ruta literal propia.

Countries, markets, service_zones y marketplace_local_fixture_operations mantienen 0 registros. No se crearon usuarios/permisos/grants ni fixtures positivos en desarrollo. Fundación por Nginx verifica POST anónimo sin CSRF →419, no-store/correlación y ningún cambio de datos/claims; lectura persistida anónima →401. No hubo reset, rollback local ni eliminación de volúmenes.

## Archivos del checkpoint

- [apps/api/app/Modules/Marketplace/Application/Fixtures/CreateLocalDraftFixture.php](../../../apps/api/app/Modules/Marketplace/Application/Fixtures/CreateLocalDraftFixture.php)
- [apps/api/app/Modules/Marketplace/Application/Fixtures/LocalDraftFixtureAccess.php](../../../apps/api/app/Modules/Marketplace/Application/Fixtures/LocalDraftFixtureAccess.php)
- [apps/api/app/Modules/Marketplace/Application/Fixtures/LocalDraftFixtureStore.php](../../../apps/api/app/Modules/Marketplace/Application/Fixtures/LocalDraftFixtureStore.php)
- [apps/api/app/Modules/Marketplace/Application/Fixtures/LocalFixtureWriter.php](../../../apps/api/app/Modules/Marketplace/Application/Fixtures/LocalFixtureWriter.php)
- [apps/api/app/Modules/Marketplace/Domain/Fixtures/FixtureFailure.php](../../../apps/api/app/Modules/Marketplace/Domain/Fixtures/FixtureFailure.php)
- [apps/api/app/Modules/Marketplace/Domain/Fixtures/FixtureOperation.php](../../../apps/api/app/Modules/Marketplace/Domain/Fixtures/FixtureOperation.php)
- [apps/api/app/Modules/Marketplace/Domain/Fixtures/FixtureProfile.php](../../../apps/api/app/Modules/Marketplace/Domain/Fixtures/FixtureProfile.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/IdentityLocalDraftFixtureAccess.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/IdentityLocalDraftFixtureAccess.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/LocalDraftFixtureResourceResolver.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/LocalDraftFixtureResourceResolver.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/PlatformLocalFixtureWriter.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/PlatformLocalFixtureWriter.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/PostgresLocalDraftFixtureStore.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Fixtures/PostgresLocalDraftFixtureStore.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php)
- [apps/api/app/Modules/Marketplace/Interfaces/Http/LocalDraftFixtureController.php](../../../apps/api/app/Modules/Marketplace/Interfaces/Http/LocalDraftFixtureController.php)
- [apps/api/app/Shared/Http/ApiExceptionRenderer.php](../../../apps/api/app/Shared/Http/ApiExceptionRenderer.php)
- [apps/api/database/migrations/2026_10_10_000004_create_marketplace_local_fixture_operations.php](../../../apps/api/database/migrations/2026_10_10_000004_create_marketplace_local_fixture_operations.php)
- [apps/api/tests/Feature/ArchitectureTest.php](../../../apps/api/tests/Feature/ArchitectureTest.php)
- [apps/api/tests/Feature/LocalDraftFixtureAccessTest.php](../../../apps/api/tests/Feature/LocalDraftFixtureAccessTest.php)
- [apps/api/tests/Feature/LocalDraftFixtureHttpTest.php](../../../apps/api/tests/Feature/LocalDraftFixtureHttpTest.php)
- [apps/api/tests/Feature/OpenApiContractTest.php](../../../apps/api/tests/Feature/OpenApiContractTest.php)
- [apps/api/tests/Integration/LocalDraftFixturePostgresTest.php](../../../apps/api/tests/Integration/LocalDraftFixturePostgresTest.php)
- [apps/api/tests/Integration/LocalIdentityHttpTest.php](../../../apps/api/tests/Integration/LocalIdentityHttpTest.php)
- [apps/api/tests/Support/fixture-environment-worker.php](../../../apps/api/tests/Support/fixture-environment-worker.php)
- [apps/api/tests/Support/fixture-race-worker.php](../../../apps/api/tests/Support/fixture-race-worker.php)
- [apps/api/tests/Support/lint-openapi.php](../../../apps/api/tests/Support/lint-openapi.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [apps/api/tests/Unit/FixtureOperationTest.php](../../../apps/api/tests/Unit/FixtureOperationTest.php)
- [docs/README.md](../../../docs/README.md)
- [docs/api/README.md](../../../docs/api/README.md)
- [docs/api/openapi.yaml](../../../docs/api/openapi.yaml)
- [docs/api/schemas/README.md](../../../docs/api/schemas/README.md)
- [docs/api/schemas/marketplace-local-draft-fixture-operation.v1.json](../../../docs/api/schemas/marketplace-local-draft-fixture-operation.v1.json)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-002-data-identifiers-messaging.md](../../../docs/architecture/decisions/ADR-002-data-identifiers-messaging.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/specifications/marketplace-and-purchase.md](../../../docs/specifications/marketplace-and-purchase.md)
- [docs/sprints/sprint-02/README.md](../../../docs/sprints/sprint-02/README.md)
- [docs/sprints/sprint-02/checkpoint-02e-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02e-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02f-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02f-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02f-plan.md](../../../docs/sprints/sprint-02/checkpoint-02f-plan.md)

## Límites y riesgos pendientes

DP-001 sigue abierta para la operación real. No hay editor/importación, administración de grants, mapas reales, edición/eliminación ni activación. No se promete serialización frente a una futura administración concurrente de permisos; se verifica acceso vigente antes del claim, dentro del callback y antes de resolver la respuesta. Expiración/retención son exclusivamente técnicas locales. Una futura mutación con eventos o efectos externos exige contrato versionado/outbox aprobado.

La migración es forward-only; revertir datos requeriría un plan revisado. No se instalaron dependencias, generaron aplicaciones ni modificó infraestructura. La autorización final permite publicar exclusivamente este checkpoint y comprobar su CI; no autoriza operación real ni despliegue productivo.

## Aprobación y publicación autorizadas

Después de presentar la evidencia terminada y solicitar la aprobación de cierre/commit/push/CI, el usuario respondió «Apruebo y autorizo» el 10 de octubre de 2026. Esa respuesta aprueba 02F y su publicación con los 41 archivos del manifiesto. Se verificará la CI del mismo commit, incluidos build/migración, calidad/integración, reinicio/persistencia y limpieza. El resultado de publicación se comunicará al finalizar; este registro se incorpora antes del commit para evitar un commit posterior únicamente de contabilidad.

### Resultado de publicación verificado

02F se publicó en main / origin/main en `3c9448c37c3736a155cd794b3f9bd2861e557796`, con los 41 archivos aprobados, 1736 inserciones y 8 eliminaciones. [CI 38091316706](https://github.com/nromero96/traepe/actions/runs/38091316706) terminó correctamente sobre ese mismo SHA: run y job foundation completed/success, con build/migración, calidad/integración, reinicio/persistencia y limpieza completed/success. Repositorio limpio y ocho servicios locales saludables al cierre. Registro incorporado al preparar el siguiente checkpoint, sin commit adicional exclusivo de contabilidad.
