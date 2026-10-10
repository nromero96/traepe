# Checkpoint 01D — Integración HTTP de autorización local

Fecha: 10 de octubre de 2026. Continuación autorizada tras 01C aprobado/publicado. Origen: maestro v1.6 §§2.1, 35 y 60, sesión Sanctum aprobada en 01A y políticas DP-026 aprobadas en 01B. Integra únicamente un recurso técnico ficticio de desarrollo; preparado para aprobación/publicación del checkpoint.

## Resultado

GET /api/v1/identity/local-authorization-probe conecta sesión Sanctum, resolución del actor autenticado, AuthorizationDirectory PostgreSQL, ResourceContextResolver local y PermissionService. Anónimo: 401; autenticado sin concesión exacta: 403; fixture autorizado: 200 con recurso ULID y allowed=true. El código de rechazo público es genérico y no revela reglas ni motivos internos.

La capacidad identity.local.probe, el scope plataforma ficticio y el recurso son constantes del servidor dentro del caso de uso. Query/body/headers del cliente no eligen actor, capacidad, recurso, scope o tiempo. AuthenticatedActorDirectory traduce exclusivamente el ID obtenido de la sesión a ULID desde tablas propias. El controlador obtiene el instante UTC del reloj del servidor y no expone IDs internos ni PII.

LocalProbeResourceResolver reconoce únicamente ese recurso ficticio; no es un resolver comercial ni define la identidad real de una plataforma. El gate de entorno es middleware anterior al controlador: fuera de local/testing devuelve 404 incluso si existe una ruta local cacheada y el resolver no está registrado. El controlador y resolver conservan comprobación de entorno adicional. Límite reutilizado: 30 requests por minuto.

No se crean roles/capacidades/grants en desarrollo, ni hay endpoint para emitirlos. Los casos positivos se prueban únicamente mediante fixtures en bases propias de integración. El recurso no ejecuta mutaciones ni devuelve información de un usuario/comercio; no habilita acciones sensibles, MFA o privilegios administrativos.

## Validación

- Pint: 133 archivos correctos. PHPStan nivel 8: sin errores. OpenAPI 3.0 válido; versión 1.2.0 con la ruta, respuestas y esquema cookie local.
- Unit/Feature: 45 pruebas y 1263 aserciones. Integración PostgreSQL: 25 pruebas y 207 aserciones. Total: 70 pruebas, 1470 aserciones.
- Siete pruebas HTTP nuevas: anónimo/sin grant; concesión exacta; deny/expiración/bloqueo revalidados; actor/scope/recurso/tiempo del cliente ignorados; guard de producción con ruta cacheada; guard previo a servicios no registrados; límite 30/31.
- La prueba de rate limit detectó contadores compartidos entre fixtures con IDs internos repetidos. PostgresTestCase ahora usa cache.limiter=array solo en el contenedor de cada test y descarta su singleton previo; no cambia runtime ni purga Redis. Suite completa pasó después del ajuste, incluida la regresión de login/logout de 01A.
- HTTP real vía Nginx sin sesión: 401. verify-foundation pasó tras terminar la suite; bases temporales eliminadas, salud/readiness y logs sanitizados correctos.

## Archivos

Nuevos: AuthenticatedActorDirectory y LocalAuthorizationProbe en Application/Authorization; LocalProbeResourceResolver en Infrastructure/Authorization; LocalAuthorizationProbeController y LocalIdentityEnvironment en Interfaces/Http; LocalAuthorizationProbeTest en Integration; esta evidencia.

Modificados: PostgresAuthorizationDirectory, IdentityServiceProvider, OpenApiContractTest, PostgresTestCase, docs/api/openapi.yaml, índice y runbook del Sprint 01. Sin migraciones, dependencias, lockfiles ni datos productivos nuevos.

## Límites

El recurso y su scope son fixtures públicos fijos, no IDs de usuarios ni plataforma real. La autorización de acciones comerciales necesita resolver del módulo dueño, matriz de capacidades, revalidación transaccional y requisitos de MFA/auditoría del maestro. No se puede reutilizar este resolver para autorizar otros recursos.

No se hizo commit/push ni despliegue. GitHub Actions verde previo corresponde a 01C/4fe405c; 01D aún no se ha validado remotamente.
## Aprobación y publicación autorizada — 10 de octubre de 2026

El usuario respondió «Apruebo y autorizo» a la aprobación de 01D, commit/push y validación en GitHub Actions. La autorización cubre la integración HTTP local, contratos, pruebas y documentación. El resultado remoto se verificará para el SHA publicado antes de declararlo verde.
