# Checkpoint 01F — Sesiones de Identity por HTTP real

Fecha: 10 de octubre de 2026. Continuación solicitada después de 01E aprobado/publicado en `48d544d3a1b5ac2cf8eb75cb924a64188b5ecd3d` y validado en [GitHub Actions](https://github.com/nromero96/traepe/actions/runs/38046835597). Fuentes: maestro v1.6 §§35, 60, 64 y 71; alcance local y políticas DP-025/DP-026 aprobados.

## Alcance y aceptación

Completar la verificación del flujo aprobado con peticiones HTTP y cookies reales. Las pruebas anteriores conservan su cobertura de dominio, middleware y PostgreSQL; 01F agrega un transporte que inicia una aplicación nueva en cada request, sin actingAs ni desactivar middleware.

- CSRF ausente/alterado devuelve 419 antes de crear un desafío. Login correcto rota el ID de sesión y el token CSRF; el token anterior no permite logout y la cookie anterior no autentica.
- Sesión de archivo cifrada, cookie HttpOnly/SameSite=lax y persistencia tras reiniciar el proceso HTTP de prueba. No establece una política productiva ni cambia la duración configurada.
- Logout elimina la sesión autenticada, rota el ID y rechaza la cookie copiada al consultar me y la sonda; un OTP consumido no abre otra sesión. Auditoría y consentimiento se relacionan con la correlación del HTTP real.
- Estado blocked se vuelve a consultar; sesiones expiradas y cookies alteradas se rechazan. Los cambios de estado y mtime pertenecen exclusivamente a fixtures de prueba.
- Si falla la escritura de auditoría en logout, se devuelve 500 sanitizado y la cookie autenticada queda revocada. La restricción que provoca el fallo existe solo en la base temporal del test.
- Logs del proceso sin celular/nombre ficticios, OTP, tokens CSRF ni cookies de sesión; las aserciones no imprimen esos valores ni cuerpos de respuesta.

## Aislamiento

LocalIdentityHttpTest hereda PostgresTestCase: cada caso crea/migra una base `traepe_00e_test_<random>` y la elimina al terminar. Solo se descifra el código desde esa base para el fixture; no se crea un bypass de entrega OTP ni se utiliza la consola interactiva de desarrollo.

El servidor PHP HTTP se ejecuta dentro del contenedor api y escucha únicamente en 127.0.0.1 con un puerto efímero. Usa el router ya instalado de Laravel, el kernel y middleware reales, y Guzzle/Symfony Process ya presentes en el lockfile. No se instala una dependencia ni se genera una aplicación.

Sesiones, caché de límite y logs viven en un directorio temporal único mediante LARAVEL_STORAGE_PATH. APP_CONFIG_CACHE/APP_ROUTES_CACHE apuntan a archivos propios ausentes; no se lee ni reemplaza caché del runtime. El proceso se detiene antes de eliminar datos temporales; la eliminación verifica ruta/prefijo y no sigue enlaces simbólicos. No modifica usuarios, sesiones, Redis ni volúmenes de desarrollo.

## Validación

- `composer quality` dentro de Docker: Pint, 136 archivos correctos; PHPStan nivel 8, sin errores; esquema oficial OpenAPI 3.0 válido y documento malformado rechazado. Unit/Feature: 50 pruebas, 1415 aserciones.
- `composer test:integration`: 28 pruebas y 504 aserciones, incluidas las tres nuevas por HTTP real. Total: 78 pruebas, 1919 aserciones. Regresiones de 01A–01E y la fundación correctas.
- Rotación, cifrado del archivo de sesión, reinicio del proceso, CSRF real, replay de OTP/cookie, expiración/bloqueo, correlación y fallo de auditoría en logout verificados. No fue necesario modificar comportamiento de aplicación.
- `verify-foundation.php`: Nginx liveness/readiness/correlación, acceso anónimo a Horizon rechazado, límites modulares, ausencia de tablas comerciales, logs sanitizados y ausencia de bases temporales correctos. Ocho servicios saludables.
- Verificación separada: no quedan directorios temporales `traepe_identity_http_*`. Procesos de prueba detenidos y bases aisladas eliminadas.
- Diff completo revisado, `git diff --check` correcto, archivos del cambio en LF y escaneo sin patrones de claves privadas/tokens ni coincidencias con secretos locales. Solo se incluyen los cinco archivos listados abajo.
- HEAD y origin/main conservan `48d544d3a1b5ac2cf8eb75cb924a64188b5ecd3d`. Sin commit/push ni CI remoto de 01F; el CI enlazado al inicio corresponde a 01E.

## Archivos y límites

Cinco archivos del checkpoint:

- Nuevos: `apps/api/tests/Integration/LocalIdentityHttpTest.php` y `docs/sprints/sprint-01/checkpoint-01f-evidence.md`.
- Modificados: `docs/README.md`, `docs/sprints/sprint-01/README.md` y `docs/sprints/sprint-01/local-runbook.md`.

Sin cambios de comportamiento de aplicación, rutas, OpenAPI, migraciones ni dependencias. Durante la revisión apareció el archivo temporal de bloqueo de Word `docs/master/~$cumento_Maestro_Funcional_trae_pe_v1_6.docx`; se conserva fuera del alcance, sin modificarlo ni incluirlo en el checkpoint.

El transporte usa PHP HTTP dentro de Docker; no sustituye el entorno oficial Nginx/PHP-FPM. La verificación de fundación comprueba por separado Nginx y readiness del stack. El reinicio probado aquí es del proceso HTTP temporal; la persistencia del stack se verifica en CI.

Identity local no equivale a Identity productivo: proveedores, términos legales, MFA sensible, dispositivos de confianza, mutación de roles/grants y resolvers comerciales siguen fuera del alcance. No se hace commit/push de 01F sin aprobación explícita.

## Aprobación y publicación autorizada — 10 de octubre de 2026

El usuario respondió «Apruebo y autorizo» a la aprobación de 01F, commit, push y validación en GitHub Actions. La autorización cubre las pruebas HTTP y los cinco archivos documentados. Los resultados anteriores describen la validación local previa a publicar; el resultado remoto se verificará para el SHA publicado antes de declarar el checkpoint verde en CI.
