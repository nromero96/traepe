# Evidencia del Checkpoint 02E — Diagnóstico persistido HTTP autorizado

Fecha: 10 de octubre de 2026. **Estado: implementado y validado localmente; checkpoint, commit, push y CI aprobados por el usuario.**

## Autorización y trazabilidad

El usuario respondió «aprobar y continuar» al [plan revisable de 02E](checkpoint-02e-plan.md) antes de implementar. DP-030 quedó resuelta para ese alcance local: sesión cookie existente, permiso técnico exacto, scope/recurso fijos del servidor, aislamiento por mercado y ningún acceso positivo preconcedido en desarrollo. La aprobación inicial no autorizó commit/push/CI. Después de presentar esta evidencia terminada, el usuario respondió «continuar, aprobado» a la aprobación del checkpoint y a su publicación; véase el registro siguiente.

Maestro v1.6 §§32–34.1 y 58–60, DP-026 a DP-030 y ADR-006. SHA-256 del maestro sin cambios: `85FEF02B0CBA9669EE37AAE4986BD7709634CB93987060435AF0A3759A802C2E`. Base main `34148c043335b6c33d540a0f696ccd4a7435d2d2`, correspondiente a 02D; [CI correcta de esa base](https://github.com/nromero96/traepe/actions/runs/38080736037). Las cinco modificaciones documentales que prepararon el plan se conservaron y completaron dentro de este checkpoint.

## Implementación y operación

GET `/api/v1/marketplace/local-persisted-coverage-probe` existe solo en local/testing. Query market_public_id, longitude y latitude; ULID mayúsculo y punto WGS84 finito con límites aprobados. Middleware de entorno, web/auth:web y limitador propio 30/minuto por actor. auth:web acepta exclusivamente la cookie de sesión del flujo Identity existente; bearer/basic no habilitan acceso. No crea tokens ni mecanismo alternativo.

LocalPersistedCoverageAccess define el puerto Application y las referencias técnicas aprobadas. IdentityLocalPersistedCoverageAccess solicita a los contratos públicos de Identity el actor y sus reglas, y delega en PermissionService/DP-026. LocalPersistedCoverageResourceResolver resuelve únicamente recurso `marketplace.local.persisted_coverage` / `01ARZ3NDEKTSV4RRFFQ69G5FB3` en scope platform `01ARZ3NDEKTSV4RRFFQ69G5FB2`; capacidad `marketplace.local.persisted_coverage.read`. Su composición específica conserva el binding de la sonda Identity anterior. No consulta tablas privadas de Identity desde Marketplace ni modifica Shared.

El permiso autoriza este diagnóstico técnico para cualquier mercado draft indicado por ULID; cada consulta geográfica queda limitada al mercado solicitado. No constituye autorización comercial por mercado. Sin sesión: 401; permiso ausente, capacidad/scope incorrectos, actor bloqueado, deny vigente o expiración: 403 antes de validar la entrada o consultar Marketplace. El cliente no determina actor, scope, recurso, capacidad o reloj. El adaptador deniega otros entornos antes de consultar el directorio Identity.

El controlador valida entrada después de autorizar y resuelve LocalPersistedCoverageProbe después de validar. Reutiliza sin cambios la lectura de 02D: markets/service_zones propias, draft/fixture, geography nativa, bordes incluidos, máxima prioridad y rechazo de empate. 200 devuelve recurso local_persisted_coverage_probe, ID técnico local-persisted-coverage-v1, status y zone_id nullable, más correlación. Los cuatro estados técnicos son market_not_found/outside/selected/ambiguous; selected no habilita servicio. Respuestas locales no-store/private, errores genéricos y 429 con Retry-After.

Desarrollo conserva countries/markets/service_zones vacías. No agrega usuarios, permisos, seeds, migraciones, roles ni comandos de asignación. Pruebas positivas únicamente en bases temporales propias, desde migraciones limpias; sus fixtures y procesos HTTP se retiran al terminar. Las lecturas conservan datos de negocio/grants; sesión y limitador pueden actualizar metadatos técnicos existentes. No se modifican OTP, credencial Horizon, comandos 02A/02D ni sonda HTTP 02B.

Ejemplo de consulta para un entorno de prueba con sesión y permiso concedidos en su base temporal:

```text
GET /api/v1/marketplace/local-persisted-coverage-probe?market_public_id=<ULID>&longitude=0.5&latitude=0.5
```

La instalación normal de desarrollo responde 401 sin cookie y no proporciona acceso positivo preconfigurado. No hay un procedimiento de asignación manual en desarrollo como parte de este checkpoint.

## Archivos del cambio

25 archivos; ocho PHP nuevos y dos documentos nuevos. Los restantes son cambios en registros, pruebas, contrato y documentación aplicables.

| Área | Archivos |
|---|---|
| Application | `apps/api/app/Modules/Marketplace/Application/Coverage/LocalPersistedCoverageAccess.php` |
| Infrastructure | `apps/api/app/Modules/Marketplace/Infrastructure/Coverage/IdentityLocalPersistedCoverageAccess.php`, `LocalPersistedCoverageResourceResolver.php`; `apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php` |
| Interfaces | `apps/api/app/Modules/Marketplace/Interfaces/Http/LocalPersistedCoverageProbeController.php` |
| Feature | `apps/api/tests/Feature/LocalPersistedCoverageAccessTest.php`, `LocalPersistedCoverageHttpTest.php`, `ArchitectureTest.php`, `OpenApiContractTest.php` |
| Integration | `apps/api/tests/Integration/LocalPersistedCoverageHttpPostgisTest.php`, `LocalIdentityHttpTest.php` |
| Support | `apps/api/tests/Support/persisted-coverage-environment-worker.php`, `verify-foundation.php` |
| API | `docs/api/openapi.yaml`, `docs/api/README.md` |
| Arquitectura/especificación | `docs/architecture/README.md`, `docs/architecture/security/README.md`, `docs/architecture/decisions/pending-decisions.md`, `docs/specifications/marketplace-and-purchase.md` |
| Estado/evidencia | `apps/api/README.md`, `docs/README.md`, `docs/sprints/sprint-02/README.md`, `checkpoint-02d-evidence.md`, `checkpoint-02e-plan.md`, este documento |

Sin dependencias nuevas, cambios de imágenes/Compose/env ni alteraciones de tablas. La arquitectura permite siete tipos públicos de Identity exclusivamente desde los dos adaptadores Infrastructure aprobados; cualquier otra dependencia entre módulos continúa denegada, incluida infraestructura privada y acceso desde Domain/Application/Interfaces.

## Validaciones ejecutadas

Todos los comandos PHP/Composer se ejecutaron dentro de Docker con `docker compose --env-file .env.docker exec -T api ...`.

| Validación | Resultado |
|---|---|
| Pint selectivo y `composer quality` | Correcto; Pint verifica 172 archivos |
| PHPStan nivel 8 | Sin errores |
| Contrato OpenAPI 1.4.0 | Esquema oficial correcto y documento inválido rechazado |
| Unit/Feature completo | 116 pruebas, 2453 aserciones, correcto |
| `composer test:integration` completo | 48 pruebas, 1025 aserciones, correcto |
| Pruebas específicas de HTTP/autorización/contrato | 27 pruebas, 1619 aserciones, correcto; incluidas en el total completo |
| Integración específica de cobertura y sesión HTTP | 6 pruebas, 420 aserciones, correcto; incluidas en el total completo |
| `tests/Support/verify-quality-gates.php` | Pest, Pint y PHPStan rechazan su fallo controlado con exit 1 |
| `tests/Support/verify-foundation.php` | Fundación correcta; petición real Nginx devuelve 401/correlación/no-store; conteos de usuarios/permisos conservados; tablas geográficas vacías y sin bases temporales |
| `tests/Support/verify-runtime-permissions.php` | FPM escribe/lee/retira archivos propios y liveness correcto |
| Salud Docker | Ocho servicios saludables; sin reset ni eliminación de volúmenes |
| Revisión documental/diff/secretos | Alcance cerrado, UTF-8/LF, enlaces locales y `git diff --check`; sin credenciales locales ni firmas de secretos |

Total sin contar repeticiones: **164 pruebas y 3478 aserciones**. Se agregan 27 pruebas: 24 Feature/contrato y tres Integration. Las pruebas no requieren orden de ejecución; cada base real y su limitador se aíslan.

La validación específica incluye ausencia de sesión con bearer/basic, autorización previa a validación/consulta, formato/rangos/no finitos, cuatro resultados cerrados, correlación, privacidad y límite independiente por actor. Adapter tests verifican deny, expiración exacta y recurso/scope fijos sin reemplazar el resolver anterior. La integración real prueba capacidad/scope incorrectos, expiración, deny/bloqueo, manipulación del contexto, aislamiento entre mercados, hueco/bordes/puntos próximos, conservación de datos y ausencia de consultas geográficas en rechazos.

Un proceso HTTP independiente en local usa CSRF, OTP y cookie cifrada reales contra una base temporal: 401 sin sesión, 403 sin permiso, 200 con permiso, 403 tras bloqueo y 401 tras logout o replay de la cookie anterior. Pruebas de logs usan canarios sin datos reales y verifican ausencia de entrada, cabeceras y detalles de SQL.

Procesos nuevos production/staging, con caché real creada en local y también sin ella, reciben 404 para entrada ausente, inválida o válida; no ejecutan el middleware de sesión ni resuelven puertos de autorización/cobertura. Laravel construye middleware durante terminate incluso tras un rechazo; el instrumento mide ejecución de StartSession.handle, sin confundir construcción técnica con inicio/acceso de sesión.

El primer pase completo detectó la prohibición anterior de todo import entre módulos. Se concretó la lista exacta de contratos/archivos autorizados por DP-030 en ArchitectureTest, manteniendo prohibidas otras dependencias y tablas privadas, y se repitió la calidad completa correctamente. No se debilitó PHPStan ni se modificó una regla de negocio para resolver la prueba.

## Riesgos y pendientes

DP-001 continúa abierta: no hay mercados ni cobertura operativos. Scope/recurso/capacidad son fixtures locales, sin grants en desarrollo y sin modelo administrativo productivo. El diagnóstico no implementa reglas vigentes, horarios, elegibilidad de checkout, tarifas, ETA ni proveedores. Datos y estados draft/fixture de 02C permanecen intactos.

La CI de la base 02D es correcta. El usuario aprobó publicar 02E; el commit/push y su comprobación remota se ejecutan bajo esa autorización. La aprobación no autoriza despliegue, merge ni avance automático a otro checkpoint.

## Aprobación y publicación autorizadas

El 10 de octubre de 2026 el usuario respondió «continuar, aprobado» a la pregunta que presentaba 02E terminado, sus 25 archivos, 164 pruebas con 3478 aserciones y las validaciones de formato/análisis/contrato/secretos. Aprobó explícitamente el checkpoint y autorizó crear el commit, hacer push normal a main y comprobar GitHub Actions para ese commit, incluidos calidad, integración, reinicio/persistencia y limpieza. Se conserva el alcance local, las tablas geográficas vacías y DP-001 abierta.

La publicación usa un commit/push normales desde main, tras verificar el alcance exacto y la ausencia de divergencia. La CI deberá completar sus fases requeridas para el SHA publicado; su resultado remoto se informa al cerrar la publicación.

## Resultado de publicación

Publicado en `79d4d3d76c3a4c60075aabf596d3207be333d42a` mediante commit/push normales de los 25 archivos aprobados: 1025 líneas agregadas y 8 retiradas. HEAD, origin/main y main remoto coincidieron y el repositorio quedó limpio al cerrar 02E. [GitHub Actions 38084025841](https://github.com/nromero96/traepe/actions/runs/38084025841) terminó completed/success para ese SHA y su job; construcción/migraciones, calidad/integración, reinicio/persistencia y limpieza completaron correctamente. El estado y las cuatro fases se volvieron a verificar al preparar 02F; ocho servicios locales saludables. La continuación posterior no autoriza silenciosamente escrituras ni datos operativos.
