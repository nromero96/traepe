# Checkpoint 01E — Protección de entorno de Identity local

Fecha: 10 de octubre de 2026. Continuación solicitada tras 01D aprobado, publicado en `5f0f4f8` y validado en [GitHub Actions](https://github.com/nromero96/traepe/actions/runs/38045795191). Origen: maestro v1.6 §§35, 60 y 71, DP-025 y alcance exclusivo de desarrollo aprobado en 01A. Este checkpoint corrige la aplicación de una restricción existente; no define autenticación productiva.

## Problema y alcance

El registro de rutas de 01A solo ocurre en local/testing. Sin embargo, una caché de rutas creada en local puede volver a cargar OTP, me y logout en otro entorno. Los adaptadores OTP protegen solicitud/verificación, pero el registro condicional no protege por sí solo las rutas ni me/logout. Las pruebas reprodujeron respuestas 419, en lugar de deshabilitar el acceso antes de CSRF.

Las cuatro rutas de 01A incorporan LocalIdentityEnvironment antes de web, autenticación y throttle, reutilizando el gate de 01D. Cualquier entorno distinto de local/testing devuelve 404 sanitizado, aun con una sesión autenticada en memoria o rutas cacheadas. Domain/Application y las políticas aprobadas no cambian.

## Aceptación

- OTP/request, OTP/verify, me y logout quedan deshabilitados en production/staging antes de autenticación, CSRF, validación y acceso al puerto LocalOtp.
- Una caché real creada en local se carga en procesos nuevos de production/staging; las cinco rutas Identity permanecen registradas pero devuelven 404. Un proceso production sin caché no registra esas rutas.
- La prueba utiliza una ruta APP_ROUTES_CACHE y APP_CONFIG_CACHE temporales exclusivas; no reemplaza ni elimina caché del runtime. Solo elimina su propio archivo y directorio vacío.
- Se preservan las regresiones locales OTP, consentimiento, sesión, bloqueo, logout, concurrencia y autorización de 01A–01D.
- OpenAPI documenta 404 para las cuatro rutas; formato, análisis, contrato y suites disponibles deben pasar.

## Validación

- Las cinco pruebas de regresión fallaron antes de la corrección, incluida la caché real en un proceso separado; todas pasan con el gate. Los mocks comprueban que ningún método de LocalOtp se ejecuta fuera de local/testing.
- `composer quality` dentro de Docker: Pint, 135 archivos correctos; PHPStan nivel 8, sin errores; esquema oficial OpenAPI 3.0 válido y documento malformado rechazado; Unit/Feature, 50 pruebas y 1415 aserciones.
- `composer test:integration`: PostgreSQL real en bases aisladas, 25 pruebas y 207 aserciones. Total: 75 pruebas, 1622 aserciones. Las regresiones de registro, consentimiento, sesión, replay, bloqueo, concurrencia, directorio y sonda pasan.
- `verify-foundation.php`: HTTP liveness/readiness/correlación reales, protección de Horizon, errores sanitizados, límites modulares, logs seguros y ausencia de bases temporales, correctos. Ocho servicios saludables.
- Diff completo revisado, incluidos los archivos nuevos; `git diff --check` correcto y archivos del cambio normalizados a LF. Escaneo de los ocho archivos sin patrones de claves privadas/tokens ni coincidencias con los secretos locales, sin imprimir sus valores.
- HEAD y origin/main conservan `5f0f4f8ce754578835583ec801ef77978b603469`; cambios locales preparados, sin commit/push de 01E ni validación remota nueva. El CI enlazado al inicio corresponde a 01D.

## Archivos y límites

Ocho archivos del cambio:

- `apps/api/app/Modules/Identity/Infrastructure/IdentityServiceProvider.php`: agrega el middleware a las rutas de autenticación.
- `apps/api/tests/Feature/LocalIdentityEnvironmentTest.php` y `apps/api/tests/Support/identity-environment-worker.php`: prueban la frontera de entorno y el arranque desde caché real.
- `docs/api/openapi.yaml`: contrato 1.2.1 con los rechazos 404 documentados.
- `docs/README.md`, `docs/sprints/sprint-01/README.md` y `docs/sprints/sprint-01/local-runbook.md`: índice vigente, cierre publicado de 01B–01D y operación del gate.
- `docs/sprints/sprint-01/checkpoint-01e-evidence.md`: trazabilidad, aceptación, resultados y límites.

Sin migraciones, dependencias, credenciales, usuarios, concesiones ni proveedores nuevos. No habilita producción, MFA, dashboards ni asignación de privilegios. Se conserva la restricción de no hacer commit/push hasta aprobación explícita de este checkpoint.

## Aprobación y publicación autorizada — 10 de octubre de 2026

El usuario respondió «Apruebo y autorizo» a la aprobación de 01E, commit, push y validación en GitHub Actions. La autorización cubre la protección de entorno, sus regresiones, contrato y documentación. Los resultados anteriores describen la validación local previa a publicar; el resultado remoto se verificará para el SHA publicado antes de declarar el checkpoint verde en CI.
